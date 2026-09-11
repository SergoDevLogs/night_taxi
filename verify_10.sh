#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 10: пред-проверка стабильности ==="
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

echo "→ Коробка узкая: 250×250×600 — на дне только бутылка"
req -X POST "$BASE/boxes" \
  -d '{"name":"NARROW","inner_length":250,"inner_width":250,"inner_height":600,"max_weight":30000,"available_quantity":2}' >/dev/null

echo "→ Бутылка 200×200×300"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOTTLE","name":"Бутылка","length":200,"width":200,"height":300,"weight":500}' >/dev/null

echo "→ Мелкий 100×100×100"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-SMALL","name":"Мелкий","length":100,"width":100,"height":100,"weight":100}' >/dev/null

echo "→ Заказ: 1 бутылка + 1 мелкий"
req -X POST "$BASE/orders" -d '{"number":"ORD-10"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "→ Все шаги:"
echo "$RESULT" | jq -r '(.data // .) | .boxes[0].steps[]? | "  step=\(.step) sku=\(.sku) z=\(.coordinates.z) support=\(.support_ratio) warn=\(.stability_warning // "—")"'

echo "→ Проверки:"

# Если мелкий приподнят — его support_ratio должен быть >= 0.7 (Packvium гарантировал)
ELEVATED=$(echo "$RESULT" | jq '(.data // .) | [.boxes[0].steps[]? | select(.coordinates.z > 0)] | length')
if [ "$ELEVATED" -gt 0 ]; then
    LOW=$(echo "$RESULT" | jq '(.data // .) | [.boxes[0].steps[]? | select(.coordinates.z > 0 and .support_ratio < 0.7)] | length')
    [ "$LOW" -eq 0 ] || { echo "❌ Packvium разместил предмет с support_ratio < 0.7 (должен был отсеять)"; exit 1; }
    echo "✅ Все приподнятые предметы имеют support_ratio >= 0.7"
else
    echo "→ Приподнятых нет: Packvium не смог обеспечить опору, предмет ушёл в unpacked"
    UNPACKED=$(echo "$RESULT" | jq '(.data // .) | (.unpacked_items | length)')
    [ "$UNPACKED" -ge 1 ] || { echo "❌ Нет ни приподнятых, ни неупакованных — что-то не так"; exit 1; }
    echo "✅ Предмет корректно ушёл в unpacked_items ($UNPACKED)"
fi

echo "✅ Сценарий 10 пройден"
