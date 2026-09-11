#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 11: raw route для LLM ==="
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

echo "→ Коробка M"
req -X POST "$BASE/boxes" \
  -d '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":10000,"available_quantity":3}' >/dev/null

echo "→ Товары"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-MUG","name":"Кружка","length":100,"width":100,"height":120,"weight":400}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-11"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":4}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":4}' >/dev/null

echo "→ Упаковка"
req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}' >/dev/null

echo "→ Raw route"
RAW=$(req "$BASE/orders/1/packing/raw")

echo "$RAW" | jq '{
  order: .order.number,
  summary: .summary,
  boxes_count: (.boxes | length),
  steps_in_first_box: (.boxes[0].steps | length),
  layers_in_first_box: (.boxes[0].layers | length)
}'

echo "→ Проверки:"

echo "$RAW" | jq -e '.order'   >/dev/null || { echo "❌ Нет .order"; exit 1; }
echo "$RAW" | jq -e '.summary' >/dev/null || { echo "❌ Нет .summary"; exit 1; }
echo "$RAW" | jq -e '.boxes'   >/dev/null || { echo "❌ Нет .boxes"; exit 1; }

if echo "$RAW" | jq -e '.data' >/dev/null 2>&1; then
    echo "❌ Есть обёртка .data — это не raw"; exit 1
fi

BOXES_COUNT=$(echo "$RAW" | jq '.summary.boxes_count')
[ "$BOXES_COUNT" -ge 1 ] || { echo "❌ boxes_count < 1"; exit 1; }

LAYERS=$(echo "$RAW" | jq '.boxes[0].layers | length')
STEPS=$(echo "$RAW" | jq '.boxes[0].steps | length')
[ "$LAYERS" -ge 1 ] || { echo "❌ Нет layers"; exit 1; }
[ "$STEPS" -ge 1 ]  || { echo "❌ Нет steps"; exit 1; }

echo "$RAW" | jq -e '.boxes[0].steps[0].text | length > 0' >/dev/null \
  || { echo "❌ Нет text в steps[0]"; exit 1; }

echo "✅ Raw route корректен ($BOXES_COUNT коробок, $STEPS шагов, $LAYERS слоёв)"
echo "✅ Сценарий 11 пройден"
