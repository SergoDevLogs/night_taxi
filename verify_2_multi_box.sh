#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 2: несколько коробок ==="
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

echo "→ Коробки: S, M, L"
req -X POST "$BASE/boxes" \
  -d '{"name":"S","inner_length":300,"inner_width":200,"inner_height":150,"max_weight":5000,"available_quantity":3}' >/dev/null
req -X POST "$BASE/boxes" \
  -d '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":8000,"available_quantity":3}' >/dev/null
req -X POST "$BASE/boxes" \
  -d '{"name":"L","inner_length":600,"inner_width":400,"inner_height":350,"max_weight":15000,"available_quantity":3}' >/dev/null

echo "→ Товары"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-LAPTOP","name":"Ноутбук","length":350,"width":250,"height":40,"weight":2000}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-TEDDY","name":"Мишка","length":500,"width":400,"height":300,"weight":300}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-PAN","name":"Сковорода","length":300,"width":300,"height":100,"weight":1200}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-2"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":4}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":3,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":4,"quantity":1}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "$RESULT" | jq '(.data // .) | {
  boxes: (.boxes | length),
  total_content: ([.boxes[].contents[]] | length),
  unpacked: (.unpacked_items | length),
  box_ids: [.boxes[].box.name]
}'

BOXES=$(echo "$RESULT" | jq '(.data // .) | (.boxes | length)')
CONTENT=$(echo "$RESULT" | jq '(.data // .) | ([.boxes[].contents[]] | length)')
UNPACKED=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')

echo "→ Проверки"
[ "$BOXES" -ge 2 ]    || { echo "❌ Ожидалось ≥ 2 коробок (получено $BOXES)"; exit 1; }
[ "$CONTENT" -eq 7 ]  || { echo "❌ contents ≠ 7 (получено $CONTENT)"; exit 1; }
[ "$UNPACKED" -eq 0 ] || { echo "❌ unpacked ≠ 0 (получено $UNPACKED)"; exit 1; }

echo "✅ Сценарий 2 пройден"
