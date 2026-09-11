<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Инструкция по упаковке {{ $order->number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        h1 { font-size: 16px; margin: 0 0 8px 0; }
        .meta { margin-bottom: 12px; color: #555; }
        .meta div { margin: 2px 0; }

        .box-block { margin-top: 14px; page-break-inside: avoid; }
        .box-header {
            background: #eaeaea;
            padding: 6px 8px;
            border-left: 3px solid #444;
        }
        .box-header .title { font-weight: bold; font-size: 12px; }
        .box-header .metrics { color: #555; margin-top: 2px; }

        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; font-weight: bold; }

        .layer-title {
            background: #f7f7f7;
            font-weight: bold;
            padding: 3px 6px;
            border-left: 3px solid #888;
            margin-top: 6px;
        }
        .step-num { font-weight: bold; width: 24px; }
        .step-sku { color: #666; font-family: monospace; font-size: 9px; }
        .hint { color: #444; font-style: italic; }
        .coords { color: #666; font-family: monospace; font-size: 9px; }
        .warning {
            color: #a00;
            background: #fff2f2;
            padding: 2px 4px;
            border-left: 2px solid #a00;
            font-size: 9px;
        }
        .unpacked {
            margin-top: 16px;
            padding: 8px;
            background: #fbeaea;
            border: 1px solid #e0a0a0;
        }
        .unpacked h2 { color: #a00; font-size: 13px; margin: 0 0 6px 0; }
        .unpacked .reason-code { color: #888; font-family: monospace; font-size: 9px; }
        .unpacked .reason-msg { color: #222; }
        .footer { margin-top: 20px; font-size: 9px; color: #888; text-align: center; }
    </style>
</head>
<body>

    <h1>Инструкция по упаковке заказа {{ $order->number }}</h1>

    <div class="meta">
        <div><strong>Дата:</strong> {{ $order->created_at?->format('d.m.Y H:i') }}</div>
        <div><strong>Коробок:</strong> {{ count($boxesData) }}</div>
        <div><strong>Общий вес:</strong> {{ number_format(collect($boxesData)->sum('total_weight'), 0, '.', ' ') }} г</div>
        <div><strong>Среднее заполнение:</strong> {{ round(collect($boxesData)->avg('fill_ratio') * 100) }}%</div>
        <div><strong>Неупакованных позиций:</strong> {{ $order->unpackedItems->count() }}</div>
    </div>

    @foreach ($boxesData as $i => $data)
        @php
            $box = $data['box'];
            $sortedContents = $box->contents->sortBy('position')->values();
        @endphp

        <div class="box-block">
            <div class="box-header">
                <div class="title">
                    Коробка {{ $i + 1 }} из {{ count($boxesData) }} — {{ $box->box?->name }}
                </div>
                <div class="metrics">
                    Габариты:
                    {{ $box->box?->inner_length }} × {{ $box->box?->inner_width }} × {{ $box->box?->inner_height }} мм ·
                    Заполнение: {{ round($data['fill_ratio'] * 100) }}% ·
                    Вес: {{ number_format($data['total_weight'], 0, '.', ' ') }} г ·
                    Предметов: {{ $sortedContents->count() }}
                </div>
            </div>

            @php
                $byZ = $sortedContents->groupBy('z')->sortKeys();
            @endphp

            @foreach ($byZ as $z => $itemsInLayer)
                <div class="layer-title">Слой Z = {{ $z }} мм</div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:24px;">#</th>
                            <th>Товар</th>
                            <th style="width:70px;">SKU</th>
                            <th style="width:60px;">Ориент.</th>
                            <th style="width:70px;">Координаты</th>
                            <th>Подсказка</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itemsInLayer->sortBy('position') as $c)
                            <tr>
                                <td class="step-num">{{ $c->position }}</td>
                                <td>
                                    <strong>{{ $c->product?->name }}</strong>
                                    @if ($c->stability_warning)
                                        <div class="warning">⚠ {{ $c->stability_warning }}</div>
                                    @endif
                                </td>
                                <td class="step-sku">{{ $c->product?->sku }}</td>
                                <td>{{ $c->orientation->value }}</td>
                                <td class="coords">X={{ $c->x }}<br>Y={{ $c->y }}<br>Z={{ $c->z }}</td>
                                <td>
                                    <span class="hint">{{ $c->position_hint ?? '—' }}</span>
                                    @if (!empty($c->supported_by))
                                        <div style="font-size:9px;color:#555;margin-top:2px;">
                                            Опора:
                                            @foreach ($c->supported_by as $s)
                                                {{ $s['product_name'] ?? $s['sku'] }}@if(!$loop->last), @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    @endforeach

    @if ($unpackedData->isNotEmpty())
        <div class="unpacked">
            <h2>Неупакованные позиции</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">SKU</th>
                        <th>Название</th>
                        <th style="width:50px;">Кол-во</th>
                        <th>Объяснение</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unpackedData as $row)
                        <tr>
                            <td>{{ $row['item']->product?->sku }}</td>
                            <td>{{ $row['item']->product?->name }}</td>
                            <td>{{ $row['item']->quantity }}</td>
                            <td>
                                <div class="reason-msg">{{ $row['message'] }}</div>
                                <div class="reason-code">[{{ $row['item']->reason }}]</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        Сгенерировано сервисом упаковки заказов · {{ now()->format('d.m.Y H:i') }}
    </div>

</body>
</html>
