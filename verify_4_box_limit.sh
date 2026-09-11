#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 4: не хватает коробок ==="
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

echo "→ Коробка M (доступно ТОЛЬКО 1)"
req -X POST "$BASE/boxes" \
  -d '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":8000,"available_quantity":1}' >/dev/null

echo "→ Товары: мишка и сковорода (вдвоём в одну M не влезут)"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-TEDDY","name":"Мишка","length":500,"width":400,"height":300,"weight":300}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-PAN","name":"Сковорода","length":300,"width":300,"height":100,"weight":1200}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-4"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "$RESULT" | jq '(.data // .) | {
  boxes: (.boxes | length),
  total_content: ([.boxes[].contents[]] | length),
  unpacked: (.unpacked_items | length),
  unpacked_skus: [.unpacked_items[].product.sku]
}'

BOXES=$(echo "$RESULT" | jq '(.data // .) | (.boxes | length)')
UNPACKED=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')

echo "→ Проверки"
[ "$BOXES" -eq 1 ]    || { echo "❌ Ожидалась ровно 1 коробка (получено $BOXES)"; exit 1; }
[ "$UNPACKED" -ge 1 ] || { echo "❌ Ожидалось ≥ 1 неупакованного (получено $UNPACKED)"; exit 1; }

echo "✅ Сценарий 4 пройден"
