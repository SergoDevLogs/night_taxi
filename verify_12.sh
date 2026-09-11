#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 12: PDF с слоями и подсказками ==="
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

echo "→ Коробка L"
req -X POST "$BASE/boxes" \
  -d '{"name":"L","inner_length":600,"inner_width":400,"inner_height":350,"max_weight":20000,"available_quantity":2}' >/dev/null

echo "→ Товары"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BIG","name":"Большой","length":400,"width":300,"height":200,"weight":1000}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-SMALL","name":"Мелкий","length":200,"width":150,"height":100,"weight":200}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-12"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":1}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}' >/dev/null

echo "→ Упаковка"
req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}' >/dev/null

echo "→ PDF"
PDF_FILE=/tmp/packing-12.pdf
curl -s -f -H "$H_AUTH" -H "$H_ACC" "$BASE/orders/1/packing.pdf" -o "$PDF_FILE"

PDF_SIZE=$(stat -c%s "$PDF_FILE")
[ "$PDF_SIZE" -gt 1000 ] || { echo "❌ PDF слишком маленький ($PDF_SIZE байт)"; exit 1; }
head -c 5 "$PDF_FILE" | grep -q "%PDF-" || { echo "❌ Не PDF"; exit 1; }

echo "✅ PDF корректен ($PDF_SIZE байт)"
rm -f "$PDF_FILE"
echo "✅ Сценарий 12 пройден"
