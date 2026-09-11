#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "→ Сброс БД"
php artisan migrate:fresh --force >/dev/null

echo "→ Создание пользователя и токена"
TOKEN=$(php artisan tinker --execute="
    \$u = \App\Models\User::factory()->create();
    echo \$u->createToken('smoke')->plainTextToken;
" | tail -n 1)

if [ -z "$TOKEN" ]; then
    echo "❌ Не удалось создать токен"
    exit 1
fi

H_AUTH="Authorization: Bearer $TOKEN"

req() { curl -s -f -H "$H_AUTH" -H "$H_JSON" -H "$H_ACC" "$@"; }

echo "→ Коробки"
for b in \
  '{"name":"S","inner_length":300,"inner_width":200,"inner_height":150,"max_weight":3000}' \
  '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":8000}' \
  '{"name":"L","inner_length":600,"inner_width":400,"inner_height":350,"max_weight":15000}' \
  '{"name":"XL","inner_length":800,"inner_width":600,"inner_height":500,"max_weight":25000}' \
  '{"name":"FLAT","inner_length":700,"inner_width":500,"inner_height":150,"max_weight":10000}'
do
  req -X POST "$BASE/boxes" -d "$b" >/dev/null
done

echo "→ Товары"
for p in \
  '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' \
  '{"sku":"SKU-LAPTOP","name":"Ноутбук","length":350,"width":250,"height":40,"weight":2000}' \
  '{"sku":"SKU-MUG","name":"Кружка","length":100,"width":100,"height":120,"weight":400}' \
  '{"sku":"SKU-TSHIRT","name":"Футболка","length":300,"width":250,"height":50,"weight":200}' \
  '{"sku":"SKU-SHOES","name":"Кроссовки","length":350,"width":250,"height":150,"weight":900}' \
  '{"sku":"SKU-MONITOR","name":"Монитор","length":600,"width":400,"height":100,"weight":4000}' \
  '{"sku":"SKU-KEYBOARD","name":"Клавиатура","length":450,"width":150,"height":40,"weight":800}' \
  '{"sku":"SKU-VASE","name":"Ваза","length":200,"width":200,"height":400,"weight":1500}' \
  '{"sku":"SKU-PAN","name":"Сковорода","length":300,"width":300,"height":100,"weight":1200}' \
  '{"sku":"SKU-TEDDY","name":"Мишка","length":500,"width":400,"height":300,"weight":300}' \
  '{"sku":"SKU-LAMP","name":"Лампа","length":250,"width":250,"height":500,"weight":1800}' \
  '{"sku":"SKU-TV","name":"Телевизор","length":1200,"width":800,"height":200,"weight":8000}'
do
  req -X POST "$BASE/products" -d "$p" >/dev/null
done

echo "→ Заказ"
req -X POST "$BASE/orders" -d '{"number":"ORD-SMOKE-1"}' >/dev/null

echo "→ Позиции"
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":4}'  >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":1}'  >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":3,"quantity":6}'  >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":12,"quantity":1}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "$RESULT" | jq '(.data // .) | {boxes: (.boxes|length), unpacked: (.unpacked_items|length)}'

if [ "$(echo "$RESULT" | jq '(.data // .) | .unpacked_items | length')" -lt 1 ]; then
  echo "❌ Ожидался хотя бы один неупакованный предмет (tv)"
  exit 1
fi

echo "✅ Smoke-тест пройден"
