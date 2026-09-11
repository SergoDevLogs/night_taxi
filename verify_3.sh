#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 3: есть неупакованный предмет (TV) ==="
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

echo "→ Коробки: M, XL"
req -X POST "$BASE/boxes" \
  -d '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":8000,"available_quantity":3}' >/dev/null
req -X POST "$BASE/boxes" \
  -d '{"name":"XL","inner_length":800,"inner_width":600,"inner_height":500,"max_weight":25000,"available_quantity":2}' >/dev/null

echo "→ Товары"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-LAPTOP","name":"Ноутбук","length":350,"width":250,"height":40,"weight":2000}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-TV","name":"Телевизор","length":1200,"width":800,"height":200,"weight":8000}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-3"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":4}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":3,"quantity":1}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "$RESULT" | jq '(.data // .) | {
  boxes: (.boxes | length),
  total_content: ([.boxes[].contents[]] | length),
  unpacked: (.unpacked_items | length),
  unpacked_skus: [.unpacked_items[].product.sku],
  unpacked_reasons: [.unpacked_items[].reason]
}'

BOXES=$(echo "$RESULT" | jq '(.data // .) | (.boxes | length)')
CONTENT=$(echo "$RESULT" | jq '(.data // .) | ([.boxes[].contents[]] | length)')
UNPACKED=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')
HAS_TV=$(echo "$RESULT" | jq '(.data // .) | [.unpacked_items[].product.sku] | any(. == "SKU-TV")')

echo "→ Проверки"
[ "$BOXES" -ge 1 ]     || { echo "❌ boxes < 1"; exit 1; }
[ "$CONTENT" -eq 5 ]   || { echo "❌ contents ≠ 5 (получено $CONTENT)"; exit 1; }
[ "$UNPACKED" -eq 1 ]  || { echo "❌ unpacked ≠ 1 (получено $UNPACKED)"; exit 1; }
[ "$HAS_TV" = "true" ] || { echo "❌ SKU-TV не в unpacked_items"; exit 1; }

echo "✅ Сценарий 3 пройден"
