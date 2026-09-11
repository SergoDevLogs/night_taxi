#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 9: слои, шаги, стабильность ==="
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

echo "→ Коробка L"
req -X POST "$BASE/boxes" \
  -d '{"name":"L","inner_length":600,"inner_width":400,"inner_height":350,"max_weight":20000,"available_quantity":2}' >/dev/null

echo "→ Товары: 3 разных"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BIG","name":"Большой","length":400,"width":300,"height":200,"weight":1000}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-MED","name":"Средний","length":300,"width":200,"height":150,"weight":500}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-SMALL","name":"Мелкий","length":200,"width":150,"height":100,"weight":200}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-9"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":3,"quantity":1}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "→ Layers:"
echo "$RESULT" | jq '(.data // .) | .boxes[0].layers | map({index, z, count: (.items | length)})'

echo "→ Steps (первые 3):"
echo "$RESULT" | jq -r '(.data // .) | .boxes[0].steps[:3][] | "  step=\(.step) sku=\(.sku) hint=\(.position_hint // "—") support=\(.support_ratio) warn=\(.stability_warning // "—")"'

echo "→ Проверки:"

LAYERS_COUNT=$(echo "$RESULT" | jq '(.data // .) | .boxes[0].layers | length')
STEPS_COUNT=$(echo "$RESULT" | jq '(.data // .) | .boxes[0].steps | length')

# 1. Есть хотя бы один слой
[ "$LAYERS_COUNT" -ge 1 ] || { echo "❌ layers пуст"; exit 1; }

# 2. Число шагов = число placements
[ "$STEPS_COUNT" -eq 3 ] || { echo "❌ steps ≠ 3 (получено $STEPS_COUNT)"; exit 1; }

# 3. Каждый шаг имеет текст
echo "$RESULT" | jq -e '(.data // .) | .boxes[0].steps | all(.text | length > 0)' >/dev/null \
  || { echo "❌ Не у всех шагов есть text"; exit 1; }

# 4. Первый шаг — на дне (z=0, on_floor=true)
FIRST_ON_FLOOR=$(echo "$RESULT" | jq '(.data // .) | .boxes[0].steps[0].on_floor')
[ "$FIRST_ON_FLOOR" = "true" ] || { echo "❌ Первый шаг не на дне"; exit 1; }

# 5. support_ratio в диапазоне 0..1 у всех
echo "$RESULT" | jq -e '(.data // .) | .boxes[0].steps | all(.support_ratio >= 0 and .support_ratio <= 1)' >/dev/null \
  || { echo "❌ support_ratio вне [0,1]"; exit 1; }

# 6. НОВОЕ: у приподнятых предметов (z > 0) support_ratio >= 0.7
ELEVATED=$(echo "$RESULT" | jq '(.data // .) | [.boxes[0].steps[] | select(.coordinates.z > 0)] | length')
if [ "$ELEVATED" -gt 0 ]; then
    LOW_SUPPORT=$(echo "$RESULT" | jq '(.data // .) | [.boxes[0].steps[] | select(.coordinates.z > 0 and .support_ratio < 0.7)] | length')
    [ "$LOW_SUPPORT" -eq 0 ] || { echo "❌ Приподнятый предмет имеет support_ratio < 0.7 (Packvium не должен был разместить)"; exit 1; }
    echo "✅ Все приподнятые предметы имеют support_ratio >= 0.7"
else
    echo "→ Приподнятых предметов нет — все на дне, проверка тривиальна"
fi

echo "✅ Слои: $LAYERS_COUNT, Шаги: $STEPS_COUNT, у всех есть text"
echo "✅ Сценарий 9 пройден"
