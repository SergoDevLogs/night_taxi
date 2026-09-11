<?php

namespace App\Services;

use App\Models\Order;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Клиент к Python/FastAPI (Pydantic AI + LLM).
 */
class InstructionClient
{
    public function __construct(private readonly Client $http) {}

    /**
     * @return array{instruction: string, steps: array}
     */
    public function generate(Order $order, array $rawPacking): array
    {
        $order->loadMissing(['packingBoxes.box', 'packingBoxes.contents.product', 'unpackedItems.product', 'items.product']);

        $payload = [
            'packing_result' => $rawPacking,
            'products'       => $order->items->pluck('product')->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name,
                'height' => $p->height, 'length' => $p->length, 'width' => $p->width,
                'weight' => $p->weight, 'can_rotate' => $p->can_rotate,
                'fragile' => $p->fragile, 'top_bottom' => $p->top_bottom,
            ])->values()->all(),
            'boxes' => $order->packingBoxes->pluck('box')->unique('id')->values()->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name,
                'inner_height' => $b->inner_height, 'inner_length' => $b->inner_length,
                'inner_width'  => $b->inner_width,  'max_weight'   => $b->max_weight,
            ])->all(),
        ];

        $url = rtrim(config('services.ai.base_url'), '/') . '/generate-instruction';

        try {
            $response = $this->http->post($url, [
                'json'    => $payload,
                'timeout' => 60,
            ]);
            return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('Instruction generation failed', ['error' => $e->getMessage()]);
            return ['instruction' => '', 'steps' => []];
        }
    }
}
