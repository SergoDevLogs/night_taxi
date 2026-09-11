#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 1: простой (book ×4) ==="
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

echo "→ Коробка S (доступно 5)"
req -X POST "$BASE/boxes" \
  -d '{"name":"S","inner_length":300,"inner_width":200,"inner_height":150,"max_weight":5000,"available_quantity":5}' >/dev/null

echo "→ Товар: книга"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' >/dev/null

echo "→ Заказ + позиция (book ×4)"
req -X POST "$BASE/orders" -d '{"number":"ORD-1"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":4}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "$RESULT" | jq '(.data // .) | {
  boxes: (.boxes | length),
  total_content: ([.boxes[].contents[]] | length),
  unpacked: (.unpacked_items | length)
}'

BOXES=$(echo "$RESULT" | jq '(.data // .) | (.boxes | length)')
CONTENT=$(echo "$RESULT" | jq '(.data // .) | ([.boxes[].contents[]] | length)')
UNPACKED=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')

echo "→ Проверки"
[ "$BOXES" -ge 1 ]    || { echo "❌ boxes < 1"; exit 1; }
[ "$CONTENT" -eq 4 ]  || { echo "❌ contents ≠ 4 (получено $CONTENT)"; exit 1; }
[ "$UNPACKED" -eq 0 ] || { echo "❌ unpacked ≠ 0 (получено $UNPACKED)"; exit 1; }

echo "✅ Сценарий 1 пройден"
