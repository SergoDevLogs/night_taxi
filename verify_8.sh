#!/usr/bin/env bash
set -euo pipefail

BASE=http://127.0.0.1/api
H_JSON="Content-Type: application/json"
H_ACC="Accept: application/json"

echo "=== Сценарий 8: метрики и экспорт ==="
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
  -d '{"name":"M","inner_length":400,"inner_width":300,"inner_height":250,"max_weight":10000,"available_quantity":3}' >/dev/null

echo "→ Товары"
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-BOOK","name":"Книга","length":210,"width":140,"height":30,"weight":500}' >/dev/null
req -X POST "$BASE/products" \
  -d '{"sku":"SKU-MUG","name":"Кружка","length":100,"width":100,"height":120,"weight":400}' >/dev/null

echo "→ Заказ + позиции"
req -X POST "$BASE/orders" -d '{"number":"ORD-8"}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":1,"quantity":4}' >/dev/null
req -X POST "$BASE/orders/1/items" -d '{"product_id":2,"quantity":4}' >/dev/null

echo "→ Упаковка"
RESULT=$(req -X POST "$BASE/orders/1/pack" -d '{"generate_instruction":false}')

echo "→ Метрики:"
echo "$RESULT" | jq '(.data // .) | {
  boxes_count,
  average_fill_ratio,
  weighted_fill_ratio,
  total_weight,
  boxes: [.boxes[] | {box: .box.name, fill_ratio, total_weight}]
}'

# ─── Проверки ────────────────────────────────────────────────────────────
BOXES=$(echo "$RESULT" | jq '(.data // .) | .boxes_count')
AVG=$(echo "$RESULT" | jq '(.data // .) | .average_fill_ratio')
WAVG=$(echo "$RESULT" | jq '(.data // .) | .weighted_fill_ratio')
TOTAL_W=$(echo "$RESULT" | jq '(.data // .) | .total_weight')
FILL_FIRST=$(echo "$RESULT" | jq '(.data // .) | .boxes[0].fill_ratio')
WEIGHT_FIRST=$(echo "$RESULT" | jq '(.data // .) | .boxes[0].total_weight')

echo "→ Проверки:"

# boxes_count > 0
[ "$BOXES" -ge 1 ] || { echo "❌ boxes_count < 1"; exit 1; }

# fill_ratio в диапазоне 0..1
awk -v v="$FILL_FIRST" 'BEGIN { if (v < 0 || v > 1) exit 1 }' \
  || { echo "❌ fill_ratio вне [0,1]: $FILL_FIRST"; exit 1; }

# average_fill_ratio в диапазоне 0..1
awk -v v="$AVG" 'BEGIN { if (v < 0 || v > 1) exit 1 }' \
  || { echo "❌ average_fill_ratio вне [0,1]: $AVG"; exit 1; }

# weighted_fill_ratio в диапазоне 0..1
awk -v v="$WAVG" 'BEGIN { if (v < 0 || v > 1) exit 1 }' \
  || { echo "❌ weighted_fill_ratio вне [0,1]: $WAVG"; exit 1; }

# total_weight = сумма per-box
CALC_W=$(echo "$RESULT" | jq '(.data // .) | [.boxes[].total_weight] | add')
[ "$TOTAL_W" -eq "$CALC_W" ] \
  || { echo "❌ total_weight ≠ сумме per-box ($TOTAL_W ≠ $CALC_W)"; exit 1; }

# total_weight = 4*500 + 4*400 = 3600
[ "$TOTAL_W" -eq 3600 ] \
  || { echo "❌ total_weight ≠ 3600 (получено $TOTAL_W)"; exit 1; }

echo "✅ Метрики корректны"

# ─── CSV ─────────────────────────────────────────────────────────────────
echo "→ CSV-экспорт"
CSV=$(curl -s -f -H "$H_AUTH" -H "$H_ACC" "$BASE/orders/1/packing.csv")
CSV_LINES=$(echo "$CSV" | wc -l)
[ "$CSV_LINES" -ge 10 ] || { echo "❌ CSV слишком короткий ($CSV_LINES строк)"; exit 1; }
echo "$CSV" | grep -q "SKU-BOOK" || { echo "❌ CSV: нет SKU-BOOK"; exit 1; }
echo "$CSV" | grep -q "SKU-MUG"  || { echo "❌ CSV: нет SKU-MUG"; exit 1; }
echo "✅ CSV корректен ($CSV_LINES строк)"

# ─── PDF ─────────────────────────────────────────────────────────────────
echo "→ PDF-экспорт"
PDF_FILE=/tmp/packing-test.pdf
curl -s -f -H "$H_AUTH" -H "$H_ACC" "$BASE/orders/1/packing.pdf" -o "$PDF_FILE"
PDF_SIZE=$(stat -c%s "$PDF_FILE")
[ "$PDF_SIZE" -gt 1000 ] || { echo "❌ PDF слишком маленький ($PDF_SIZE байт)"; exit 1; }
# Проверка сигнатуры PDF: %PDF-
head -c 5 "$PDF_FILE" | grep -q "%PDF-" || { echo "❌ Файл не PDF"; exit 1; }
echo "✅ PDF корректен ($PDF_SIZE байт)"

rm -f "$PDF_FILE"

echo ""
echo "✅ Сценарий 8 пройден"
