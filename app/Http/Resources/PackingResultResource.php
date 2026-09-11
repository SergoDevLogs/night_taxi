<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackingResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $boxes = $this->whenLoaded('packingBoxes');

        $boxesCount = $boxes->count();

        $sumFillRatios   = 0.0;
        $sumItemsVolume  = 0;
        $sumBoxesVolume  = 0;
        $totalWeight     = 0;

        foreach ($boxes as $box) {
            $boxVolume   = $box->box?->volume ?? 0;
            $itemsVolume = 0;
            $boxWeight   = 0;

            foreach ($box->contents as $c) {
                $p = $c->product;
                if (! $p) {
                    continue;
                }
                $itemsVolume += ($p->length * $p->width * $p->height) * $c->quantity;
                $boxWeight   += $p->weight * $c->quantity;
            }

            $sumFillRatios  += $boxVolume > 0 ? ($itemsVolume / $boxVolume) : 0;
            $sumItemsVolume += $itemsVolume;
            $sumBoxesVolume += $boxVolume;
            $totalWeight    += $boxWeight;
        }

        $averageFillRatio  = $boxesCount > 0 ? round($sumFillRatios / $boxesCount, 4) : 0.0;
        $weightedFillRatio = $sumBoxesVolume > 0 ? round($sumItemsVolume / $sumBoxesVolume, 4) : 0.0;

        return [
            'order_id'            => $this->id,
            'boxes_count'         => $boxesCount,
            'average_fill_ratio'  => $averageFillRatio,
            'weighted_fill_ratio' => $weightedFillRatio,
            'total_weight'        => $totalWeight,
            'boxes'               => PackingBoxResource::collection($boxes),
            'unpacked_items'      => UnpackedItemResource::collection($this->whenLoaded('unpackedItems')),
            'alternatives'        => $this->alternatives ?? [],
            'instruction'         => $this->instruction ?? null,
        ];
    }
}
