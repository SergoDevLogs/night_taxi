<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Orientation;
use App\Models\Box;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class PackingPersistenceService
{
    public function __construct(
        private readonly PackviumService $packvium,
    ) {}

    public function pack(Order $order): array
    {
        $raw = $this->packvium->calculate($order);

        DB::transaction(function () use ($order, $raw) {
            $order->packingBoxes()->delete();
            $order->unpackedItems()->delete();

            // ─── SKU (без #N) → product_id ────────────────────────────────
            $skus = collect($raw['containers'])
                ->flatMap(fn ($c) => collect($c['placements'])->pluck('item_id'))
                ->merge(collect($raw['unpacked_items'])->pluck('item_id'))
                ->map(fn ($id) => preg_replace('/#\d+$/', '', $id))
                ->unique()
                ->values()
                ->all();

            $productBySku = Product::whereIn('sku', $skus)->pluck('id', 'sku')->all();

            // ─── 'BOX-1' → 1 ──────────────────────────────────────────────
            $boxByContainerId = Box::pluck('id')->mapWithKeys(
                fn ($id) => ['BOX-' . $id => $id]
            )->all();

            // ─── Коробки и содержимое ─────────────────────────────────────
            $boxIndex = 0;
            foreach ($raw['containers'] as $containerResult) {
                $boxIndex++;

                if (! preg_match('/^BOX-(\d+)/', $containerResult['container_id'], $m)) {
                    continue;
                }
                $boxId = (int) $m[1];
                if (! isset($boxByContainerId['BOX-' . $boxId])) {
                    continue;
                }

                $packingBox = $order->packingBoxes()->create([
                    'box_id'      => $boxId,
                    'order_index' => $boxIndex,
                ]);

                $position = 0;
                foreach ($containerResult['placements'] as $idx => $placement) {
                    $position++;
                    $sku       = preg_replace('/#\d+$/', '', $placement['item_id']);
                    $productId = $productBySku[$sku] ?? null;
                    if (! $productId) {
                        continue;
                    }

                    $step = $containerResult['steps'][$idx] ?? null;

                    $packingBox->contents()->create([
                        'product_id'        => $productId,
                        'quantity'          => 1,
                        'position'          => $position,
                        'orientation'       => Orientation::fromPackvium($placement['rotation'])->value,
                        'x'                 => $placement['x'],
                        'y'                 => $placement['y'],
                        'z'                 => $placement['z'],
                        'position_hint'     => $step['position_hint'] ?? null,
                        'step_text'         => $step['text'] ?? null,
                        'supported_by'      => $step['supported_by'] ?? null,
                        'neighbors'         => $step['neighbors'] ?? null,
                        'support_ratio'     => $placement['support_ratio'] ?? 1.0,
                        'stability_warning' => $placement['stability_warning'] ?? null,
                    ]);
                }
            }

            // ─── Неупакованные (только код причины) ───────────────────────
            foreach ($raw['unpacked_items'] as $u) {
                $sku       = preg_replace('/#\d+$/', '', $u['item_id']);
                $productId = $productBySku[$sku] ?? null;
                if (! $productId) {
                    continue;
                }

                $order->unpackedItems()->create([
                    'product_id' => $productId,
                    'quantity'   => 1,
                    'reason'     => $u['reason'],
                ]);
            }

            $order->update(['status' => OrderStatus::PACKED]);
        });

        return ['order' => $order->fresh(), 'raw' => $raw];
    }
}
