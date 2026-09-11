<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PackOrderRequest;
use App\Http\Resources\PackingResultResource;
use App\Models\Box;
use App\Models\Order;
use App\Services\InstructionClient;
use App\Services\PackingExportService;
use App\Services\PackingPersistenceService;
use App\Services\ReasonTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackingController extends Controller
{
    public function __construct(
        private readonly PackingPersistenceService $persistence,
        private readonly InstructionClient $instructionClient,
        private readonly PackingExportService $exportService,
        private readonly ReasonTranslator $translator,
    ) {}

    /** POST /orders/{id}/pack */
    public function pack(PackOrderRequest $request, int $id): PackingResultResource|JsonResponse
    {
        $order = Order::with('items.product')->findOrFail($id);

        if ($order->items->isEmpty()) {
            return response()->json([
                'error'   => 'bad_request',
                'message' => 'Заказ пуст — нечего упаковывать',
            ], 400);
        }

        ['order' => $order, 'raw' => $raw] = $this->persistence->pack($order);

        if ($request->boolean('generate_instruction', false)) {
            $ai = $this->instructionClient->generate($order, $raw);
            cache()->put("order:{$order->id}:instruction", $ai['instruction'], now()->addDay());
        }

        $order->load(['packingBoxes.box', 'packingBoxes.contents.product', 'unpackedItems.product']);

        // Прокидываем альтернативы только в ответ POST /pack
        $order->alternatives = $raw['alternatives'] ?? [];

        return new PackingResultResource($order);
    }

    /** GET /orders/{id}/packing */
    public function show(int $id): PackingResultResource
    {
        $order = Order::with([
            'packingBoxes.box',
            'packingBoxes.contents.product',
            'unpackedItems.product',
        ])->findOrFail($id);

        $order->instruction  = cache()->get("order:{$order->id}:instruction");
        $order->alternatives = [];   // при GET альтернативы не возвращаем

        return new PackingResultResource($order);
    }

    /** GET /orders/{id}/packing/raw */
    public function raw(int $id): JsonResponse
    {
        $order = Order::with([
            'packingBoxes.box',
            'packingBoxes.contents.product',
            'unpackedItems.product',
        ])->findOrFail($id);

        $boxes    = $order->packingBoxes;
        $allBoxes = Box::all()->all();

        $sumFillRatios  = 0.0;
        $sumItemsVolume = 0;
        $sumBoxesVolume = 0;
        $totalWeight    = 0;

        $boxesData = [];

        foreach ($boxes as $box) {
            $boxVolume   = $box->box?->volume ?? 0;
            $itemsVolume = 0;
            $boxWeight   = 0;

            $contents = $box->contents->sortBy('position')->values();

            foreach ($contents as $c) {
                $p = $c->product;
                if (! $p) {
                    continue;
                }
                $itemsVolume += ($p->length * $p->width * $p->height) * $c->quantity;
                $boxWeight   += $p->weight * $c->quantity;
            }

            $fillRatio      = $boxVolume > 0 ? $itemsVolume / $boxVolume : 0.0;
            $sumFillRatios  += $fillRatio;
            $sumItemsVolume += $itemsVolume;
            $sumBoxesVolume += $boxVolume;
            $totalWeight    += $boxWeight;

            $byZ = [];
            foreach ($contents as $c) {
                $byZ[(int) $c->z][] = [
                    'position'          => $c->position,
                    'sku'               => $c->product?->sku,
                    'product_name'      => $c->product?->name,
                    'orientation'       => $c->orientation->value,
                    'x'                 => $c->x,
                    'y'                 => $c->y,
                    'z'                 => $c->z,
                    'position_hint'     => $c->position_hint,
                    'support_ratio'     => $c->support_ratio,
                    'stability_warning' => $c->stability_warning,
                ];
            }
            ksort($byZ);

            $layers = [];
            $idx = 0;
            foreach ($byZ as $z => $items) {
                $idx++;
                $layers[] = ['index' => $idx, 'z' => $z, 'items' => $items];
            }

            $steps = [];
            foreach ($contents as $i => $c) {
                $steps[] = [
                    'step'              => $i + 1,
                    'sku'               => $c->product?->sku,
                    'product_name'      => $c->product?->name,
                    'orientation'       => $c->orientation->value,
                    'coordinates'       => ['x' => $c->x, 'y' => $c->y, 'z' => $c->z],
                    'position_hint'     => $c->position_hint,
                    'supported_by'      => $c->supported_by ?? [],
                    'neighbors'         => $c->neighbors ?? [],
                    'on_floor'          => $c->z === 0,
                    'layer_z'           => $c->z,
                    'support_ratio'     => $c->support_ratio,
                    'stability_warning' => $c->stability_warning,
                    'text'              => $c->step_text,
                ];
            }

            $boxesData[] = [
                'order_index' => $box->order_index,
                'box'         => [
                    'id'           => $box->box?->id,
                    'name'         => $box->box?->name,
                    'inner_length' => $box->box?->inner_length,
                    'inner_width'  => $box->box?->inner_width,
                    'inner_height' => $box->box?->inner_height,
                    'max_weight'   => $box->box?->max_weight,
                    'volume'       => $box->box?->volume,
                ],
                'fill_ratio'   => round($fillRatio, 4),
                'total_weight' => $boxWeight,
                'layers'       => $layers,
                'steps'        => $steps,
                'contents'     => $contents->map(fn ($c) => [
                    'sku'               => $c->product?->sku,
                    'product_name'      => $c->product?->name,
                    'quantity'          => $c->quantity,
                    'position'          => $c->position,
                    'orientation'       => $c->orientation->value,
                    'x'                 => $c->x,
                    'y'                 => $c->y,
                    'z'                 => $c->z,
                    'weight'            => $c->product?->weight,
                    'position_hint'     => $c->position_hint,
                    'support_ratio'     => $c->support_ratio,
                    'stability_warning' => $c->stability_warning,
                ])->all(),
            ];
        }

        $boxesCount        = $boxes->count();
        $averageFillRatio  = $boxesCount > 0 ? round($sumFillRatios / $boxesCount, 4) : 0.0;
        $weightedFillRatio = $sumBoxesVolume > 0 ? round($sumItemsVolume / $sumBoxesVolume, 4) : 0.0;

        $unpackedData = $order->unpackedItems->map(function ($u) use ($allBoxes) {
            $message = null;
            $context = null;
            if ($u->product) {
                $translated = $this->translator->translate($u->reason, $u->product, $allBoxes);
                $message    = $translated['message'];
                $context    = $translated['context'];
            }
            return [
                'sku'            => $u->product?->sku,
                'product_name'   => $u->product?->name,
                'quantity'       => $u->quantity,
                'reason'         => $u->reason,
                'reason_message' => $message,
                'reason_context' => $context,
            ];
        })->all();

        return response()->json([
            'order' => [
                'id'         => $order->id,
                'number'     => $order->number,
                'created_at' => $order->created_at?->toIso8601String(),
                'status'     => $order->status->value,
            ],
            'summary' => [
                'boxes_count'         => $boxesCount,
                'average_fill_ratio'  => $averageFillRatio,
                'weighted_fill_ratio' => $weightedFillRatio,
                'total_weight'        => $totalWeight,
                'unpacked_count'      => $order->unpackedItems->count(),
            ],
            'boxes'          => $boxesData,
            'unpacked_items' => $unpackedData,
        ]);
    }

    /** GET /orders/{id}/packing.csv */
    public function csv(int $id): StreamedResponse
    {
        $order = Order::findOrFail($id);
        return $this->exportService->csv($order);
    }

    /** GET /orders/{id}/packing.pdf */
    public function pdf(int $id): Response
    {
        $order = Order::findOrFail($id);
        return $this->exportService->pdf($order);
    }
}
