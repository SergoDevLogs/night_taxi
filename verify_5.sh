#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 5: ограничение по весу ==="
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

echo "→ Коробка M с max_weight 1500 г (доступно 2)"
req -X POST "$BASE/boxes" \
  -d '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":1500,"available_quantity":2}' >/dev/null

echo "→ Товары: два тяжёлых по 1000 г"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-HEAVY-A","name":"Тяжёлый A","length":200,"width":200,"height":100,"weight":1000}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-HEAVY-B","name":"Тяжёлый B","length":200,"width":200,"height":100,"weight":1000}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-5"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null

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
UNPACKED=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')

echo "→ Проверки"
# Каждая коробка не может весить больше 1500 г.
# Один товар = 1000 г, два = 2000 г > 1500. Значит, в одну коробку влезет только один.
# Ожидаем 2 коробки по 1 товару каждая, unpacked = 0.
[ "$BOXES" -eq 2 ]    || { echo "❌ Ожидалось 2 коробки (получено $BOXES)"; exit 1; }
[ "$UNPACKED" -eq 0 ] || { echo "❌ unpacked ≠ 0 (получено $UNPACKED)"; exit 1; }

echo "✅ Сценарий 5 пройден"
