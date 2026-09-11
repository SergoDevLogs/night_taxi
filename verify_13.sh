#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 13: человекочитаемые причины ==="
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
  -d '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":8000,"available_quantity":2}' >/dev/null

echo "→ Товар-гигант (TV 1200×800×200) — не влезет никуда"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-TV","name":"Телевизор","length":1200,"width":800,"height":200,"weight":8000}' >/dev/null

echo "→ Мелкий товар — упакуется"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-13"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":4}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "→ Unpacked items:"
echo "$RESULT" | jq '(.data // .) | .unpacked_items[] | {
  sku: .product.sku,
  reason,
  reason_message
}'

echo "→ Проверки:"

UNPACKED_COUNT=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')
[ "$UNPACKED_COUNT" -ge 1 ] || { echo "❌ Ожидался хотя бы один неупакованный"; exit 1; }

# reason_message непустой
HAS_MESSAGE=$(echo "$RESULT" | jq '(.data // .) | [.unpacked_items[] | select(.reason_message | length > 0)] | length')
[ "$HAS_MESSAGE" -ge 1 ] || { echo "❌ reason_message пуст"; exit 1; }

# reason_context непустой
HAS_CONTEXT=$(echo "$RESULT" | jq '(.data // .) | [.unpacked_items[] | select(.reason_context != null)] | length')
[ "$HAS_CONTEXT" -ge 1 ] || { echo "❌ reason_context пуст"; exit 1; }

# Для TV должен быть reason = no_compatible_container_dimensions
TV_REASON=$(echo "$RESULT" | jq -r '(.data // .) | .unpacked_items[] | select(.product.sku == "SKU-TV") | .reason')
[ "$TV_REASON" = "no_compatible_container_dimensions" ] || { echo "❌ Неверная причина для TV: $TV_REASON"; exit 1; }

# В reason_message должна быть фраза про самую большую коробку
TV_MSG=$(echo "$RESULT" | jq -r '(.data // .) | .unpacked_items[] | select(.product.sku == "SKU-TV") | .reason_message')
echo "$TV_MSG" | grep -q "Самая большая коробка" \
  || { echo "❌ reason_message не упоминает самую большую коробку: $TV_MSG"; exit 1; }

# reason_context содержит biggest_box
HAS_BIGGEST=$(echo "$RESULT" | jq '(.data // .) | [.unpacked_items[] | select(.product.sku == "SKU-TV") | select(.reason_context.biggest_box != null)] | length')
[ "$HAS_BIGGEST" -ge 1 ] || { echo "❌ reason_context не содержит biggest_box"; exit 1; }

echo "✅ reason_message и reason_context корректны"
echo "✅ Сценарий 13 пройден"
