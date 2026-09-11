#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 7: сортировка «сначала крупные» внутри слоя ==="
echo "→ Сброс БД"
php artisan migrate:fresh --force >/dev/null

echo "→ Токен"
TOKEN=$(php artisan tinker --execute="
    \$u = \App\Models\User::factory()->create();
    echo \$u->createToken('smoke')->plainTextToken;
" | tail -n 1)
[ -z "$TOKEN" ] && { echo "❌ Токен не создан"; exit 1; }
H_AUTH="Authorization: Bearer $TOKEN"

req() { curl -s -f -H "$H_AUTH" -H "$H_JSON" -H "$H_ACC" "$@"; }

echo "→ Коробка L (600×400×350)"
req -X POST "$BASE/boxes" \
  -d '{"name":"L","inner_length":600,"inner_width":400,"inner_height":350,"max_weight":20000,"available_quantity":2}' >/dev/null

echo "→ Товары разного объёма, все кладутся на дно (Z=0)"
# Крупный: 400×300×200 = 24 000 000 мм³
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BIG","name":"Большой","length":400,"width":300,"height":200,"weight":1000}' >/dev/null
# Средний: 300×200×150 = 9 000 000 мм³
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-MED","name":"Средний","length":300,"width":200,"height":150,"weight":500}' >/dev/null
# Мелкий: 200×150×100 = 3 000 000 мм³
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-SMALL","name":"Мелкий","length":200,"width":150,"height":100,"weight":200}' >/dev/null

echo "→ Заказ + позиции (по 1 каждого)"
req -X POST "$BASE/orders" -d '{"number":"ORD-7"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":3,"quantity":1}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "→ Полный список placements (по порядку из API):"
echo "$RESULT" | jq -r '(.data // .) | .boxes[0].contents[] | "  pos=\(.position) sku=\(.product.sku) z=\(.z) x=\(.x) y=\(.y)"'

echo ""
echo "→ Проверки:"

# Собираем массив: z, volume, sku — в порядке position
PLACEMENTS=$(echo "$RESULT" | jq -c '(.data // .) | .boxes[0].contents | sort_by(.position)')

# Проверка 1: сортировка по Z (снизу вверх)
echo "$PLACEMENTS" | jq -r '
  [.[].z] as $z |
  if ($z == ($z | sort)) then "✅ Z монотонно возрастает (снизу вверх)"
  else "❌ Z не отсортирован: \($z)" end
'

# Проверка 2: внутри каждого слоя — по объёму (убывание)
echo "$PLACEMENTS" | jq -r '
  group_by(.z) |
  map(
    . as $layer |
    [$layer[] | {sku: .product.sku, vol: (.product.length * .product.width * .product.height)}] as $items |
    [$items[].vol] as $vols |
    if ($vols == ($vols | sort | reverse)) then
      "✅ Слой Z=\($layer[0].z): объёмы по убыванию \($vols)"
    else
      "❌ Слой Z=\($layer[0].z): объёмы НЕ по убыванию \($vols)"
    end
  ) | .[]
'

echo ""

# Жёсткая проверка через bash: извлекаем Z и volume, проверяем монотонность
VIOLATIONS=0
PREV_Z=""
PREV_VOL=""
while IFS=$'\t' read -r pos sku z length width height; do
  vol=$((length * width * height))
  if [ -n "$PREV_Z" ]; then
    if [ "$z" -lt "$PREV_Z" ]; then
      echo "❌ Нарушение Z-порядка: pos=$pos z=$z < prev_z=$PREV_Z"
      VIOLATIONS=$((VIOLATIONS + 1))
    elif [ "$z" -eq "$PREV_Z" ] && [ "$vol" -gt "$PREV_VOL" ]; then
      echo "❌ Нарушение объёмного порядка в слое Z=$z: pos=$pos vol=$vol > prev_vol=$PREV_VOL"
      VIOLATIONS=$((VIOLATIONS + 1))
    fi
  fi
  PREV_Z="$z"
  PREV_VOL="$vol"
done < <(echo "$RESULT" | jq -r '(.data // .) | .boxes[0].contents | sort_by(.position) | .[] | "\(.position)\t\(.product.sku)\t\(.z)\t\(.product.length)\t\(.product.width)\t\(.product.height)"')

if [ "$VIOLATIONS" -eq 0 ]; then
  echo "✅ Сортировка корректна: Z возрастает, внутри слоя — крупные первыми"
else
  echo "❌ Найдено нарушений: $VIOLATIONS"
  exit 1
fi

echo ""
echo "✅ Сценарий 7 пройден"
