#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 15: альтернативы через разные objective ==="
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

echo "→ Коробки: Tall (400×300×500) и Wide (600×500×200)"
req -X POST "$BASE/boxes" \
  -d '{"name":"Tall","inner_length":400,"inner_width":300,"inner_height":500,"max_weight":20000,"available_quantity":10}' >/dev/null
req -X POST "$BASE/boxes" \
  -d '{"name":"Wide","inner_length":600,"inner_width":500,"inner_height":200,"max_weight":20000,"available_quantity":10}' >/dev/null

echo "→ 6 предметов 350×250×150"
for i in 1 2 3 4 5 6; do
  req -X POST "$BASE/products" \
    -d "{\"sku\":\"SKU-BIG-$i\",\"name\":\"Большой $i\",\"length\":350,\"width\":250,\"height\":150,\"weight\":1500}" >/dev/null
done

echo "→ 4 предмета 200×200×100"
for i in 1 2 3 4; do
  req -X POST "$BASE/products" \
    -d "{\"sku\":\"SKU-SMALL-$i\",\"name\":\"Мелкий $i\",\"length\":200,\"width\":200,\"height\":100,\"weight\":300}" >/dev/null
done

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-15"}' >/dev/null
for i in 1 2 3 4 5 6; do
  req -X POST "$BASE/orders/1/items" -d "{\"product_id\":$i,\"quantity\":1}" >/dev/null
done
for i in 7 8 9 10; do
  req -X POST "$BASE/orders/1/items" -d "{\"product_id\":$i,\"quantity\":1}" >/dev/null
done

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo ""
echo "→ Основной результат:"
echo "$RESULT" | jq '(.data // .) | {
  boxes_count,
  average_fill_ratio,
  weighted_fill_ratio,
  boxes: [.boxes[] | {box: .box.name, fill_ratio, count: (.contents | length)}]
}'

echo ""
echo "→ Альтернативы:"
echo "$RESULT" | jq '(.data // .) | .alternatives[]? | {
  objective,
  boxes_count,
  average_fill_ratio,
  weighted_fill_ratio,
  boxes_summary: [.boxes_summary[] | {box_name, fill_ratio, items_count}]
}'

echo ""
echo "→ Проверки:"

ALTS_COUNT=$(echo "$RESULT" | jq '(.data // .) | (.alternatives | length)')

if [ "$ALTS_COUNT" -eq 0 ]; then
    echo "⚠ Альтернатив нет — все objective дали одинаковый результат"
    echo "✅ Сценарий 15 пройден (тривиально)"
    exit 0
fi

echo "→ Найдено альтернатив: $ALTS_COUNT"

VALID=$(echo "$RESULT" | jq '(.data // .) | [.alternatives[] | select(.boxes_count >= 1)] | length')
[ "$VALID" -eq "$ALTS_COUNT" ] || { echo "❌ Некоторые альтернативы без коробок"; exit 1; }

echo "$RESULT" | jq -e '(.data // .) | .alternatives | all(.average_fill_ratio >= 0 and .average_fill_ratio <= 1)' >/dev/null \
  || { echo "❌ average_fill_ratio вне [0,1]"; exit 1; }

# У каждой альтернативы есть objective
HAS_OBJ=$(echo "$RESULT" | jq '(.data // .) | [.alternatives[] | select(.objective | length > 0)] | length')
[ "$HAS_OBJ" -eq "$ALTS_COUNT" ] || { echo "❌ Не у всех альтернатив есть objective"; exit 1; }

echo "✅ Альтернативы корректны ($ALTS_COUNT шт.)"
echo "✅ Сценарий 15 пройден"
