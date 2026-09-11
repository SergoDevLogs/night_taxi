<?php

namespace App\Services;

use App\Models\Box;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackingExportService
{
    public function __construct(
        private readonly ReasonTranslator $translator,
    ) {}

    public function csv(Order $order): StreamedResponse
    {
        $order->load([
            'packingBoxes.box',
            'packingBoxes.contents.product',
            'unpackedItems.product',
        ]);

        $filename = 'packing-' . $order->number . '.csv';

        return response()->streamDownload(function () use ($order) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $totalBoxes  = $order->packingBoxes->count();
            $totalItems  = $order->packingBoxes->flatMap(fn ($b) => $b->contents)->sum('quantity');
            $totalWeight = $order->packingBoxes->flatMap(fn ($b) => $b->contents)
                ->sum(fn ($c) => ($c->product?->weight ?? 0) * $c->quantity);

            fputcsv($out, ['# Заказ', $order->number]);
            fputcsv($out, ['# Коробок', $totalBoxes]);
            fputcsv($out, ['# Единиц товара', $totalItems]);
            fputcsv($out, ['# Общий вес, г', $totalWeight]);
            fputcsv($out, ['# Неупакованных позиций', $order->unpackedItems->count()]);
            fputcsv($out, []);

            fputcsv($out, [
                'box_order_index', 'box_name', 'position', 'sku',
                'product_name', 'orientation', 'x', 'y', 'z', 'weight',
            ]);

            foreach ($order->packingBoxes as $box) {
                foreach ($box->contents as $c) {
                    fputcsv($out, [
                        $box->order_index,
                        $box->box?->name,
                        $c->position,
                        $c->product?->sku,
                        $c->product?->name,
                        $c->orientation->value,
                        $c->x,
                        $c->y,
                        $c->z,
                        $c->product?->weight,
                    ]);
                }
            }

            if ($order->unpackedItems->isNotEmpty()) {
                fputcsv($out, []);
                fputcsv($out, ['# Неупакованные позиции']);
                fputcsv($out, ['sku', 'product_name', 'quantity', 'reason', 'reason_message']);

                $boxes = Box::all()->all();

                foreach ($order->unpackedItems as $u) {
                    $message = $u->product
                        ? $this->translator->translate($u->reason, $u->product, $boxes)['message']
                        : $u->reason;

                    fputcsv($out, [
                        $u->product?->sku,
                        $u->product?->name,
                        $u->quantity,
                        $u->reason,
                        $message,
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Order $order): \Illuminate\Http\Response
    {
        $order->load([
            'packingBoxes.box',
            'packingBoxes.contents.product',
            'unpackedItems.product',
        ]);

        $boxesData = [];
        foreach ($order->packingBoxes as $box) {
            $itemsVolume = 0;
            $totalWeight = 0;
            foreach ($box->contents as $c) {
                $p = $c->product;
                if (! $p) {
                    continue;
                }
                $itemsVolume += ($p->length * $p->width * $p->height) * $c->quantity;
                $totalWeight += $p->weight * $c->quantity;
            }

            $boxVolume = $box->box?->volume ?? 0;
            $fillRatio = $boxVolume > 0 ? round($itemsVolume / $boxVolume, 4) : 0.0;

            $boxesData[] = [
                'box'          => $box,
                'fill_ratio'   => $fillRatio,
                'total_weight' => $totalWeight,
            ];
        }

        // Формируем reason_message для каждой неупакованной позиции
        $boxes = Box::all()->all();
        $unpackedData = $order->unpackedItems->map(function ($u) use ($boxes) {
            $message = $u->product
                ? $this->translator->translate($u->reason, $u->product, $boxes)['message']
                : $u->reason;

            return [
                'item'    => $u,
                'message' => $message,
            ];
        });

        $pdf = Pdf::loadView('packing.pdf', [
            'order'        => $order,
            'boxesData'    => $boxesData,
            'unpackedData' => $unpackedData,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('packing-' . $order->number . '.pdf');
    }
}
