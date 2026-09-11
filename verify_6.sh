#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 6: плотность укладки ==="
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

echo "→ Коробки: S (400×300×250) и L (800×600×500)"
req -X POST "$BASE/boxes" \
  -d '{"name":"S","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":10000,"available_quantity":3}' >/dev/null
req -X POST "$BASE/boxes" \
  -d '{"name":"L","inner_length":800,"inner_width":600,"inner_height":500,"max_weight":25000,"available_quantity":3}' >/dev/null

echo "→ Товары: 4 книги 210×140×30, 4 кружки 100×100×120"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-MUG","name":"Кружка","length":100,"width":100,"height":120,"weight":400}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-6"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":4}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":4}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "$RESULT" | jq '(.data // .) | {
  boxes: (.boxes | length),
  total_content: ([.boxes[].contents[]] | length),
  box_names: [.boxes[].box.name],
  unpacked: (.unpacked_items | length)
}'

BOXES=$(echo "$RESULT" | jq '(.data // .) | (.boxes | length)')
UNPACKED=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')

echo "→ Проверки"
# 8 мелких предметов должны уместиться в 1 маленькую коробку S.
# Если Packvium выбрал S — плотность оптимальная.
# Если L — плотность не оптимальная (выбрал большую без причины).
[ "$UNPACKED" -eq 0 ] || { echo "❌ unpacked ≠ 0 (получено $UNPACKED)"; exit 1; }
[ "$BOXES" -eq 1 ]    || { echo "❌ Ожидалась 1 коробка (получено $BOXES)"; exit 1; }

BOX_NAME=$(echo "$RESULT" | jq -r '(.data // .) | .boxes[0].box.name')
[ "$BOX_NAME" = "S" ] || { echo "❌ Ожидалась коробка S (получена $BOX_NAME)"; exit 1; }

echo "✅ Сценарий 6 пройден (выбрана коробка S — минимальная достаточная)"
