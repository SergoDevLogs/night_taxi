<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackingBoxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $box      = $this->box;
        $contents = $this->contents;

        // ─── Метрики ──────────────────────────────────────────────────────
        $boxVolume = $box?->volume ?? 0;

        $itemsVolume = 0;
        $totalWeight = 0;

        foreach ($contents as $c) {
            $p = $c->product;
            if (! $p) {
                continue;
            }
            $itemVolume   = $p->length * $p->width * $p->height;
            $itemsVolume += $itemVolume * $c->quantity;
            $totalWeight += $p->weight * $c->quantity;
        }

        $fillRatio = $boxVolume > 0
            ? round($itemsVolume / $boxVolume, 4)
            : 0.0;

        return [
            'id'           => $this->id,
            'box_id'       => $this->box_id,
            'box'          => new BoxResource($this->whenLoaded('box')),
            'order_index'  => $this->order_index,
            'fill_ratio'   => $fillRatio,
            'total_weight' => $totalWeight,
            'contents'     => PackingContentResource::collection($contents),
            'layers'       => $this->buildLayersFromContents($contents),
            'steps'        => $this->buildStepsFromContents($contents),
        ];
    }

    private function buildLayersFromContents($contents): array
    {
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
                'support_ratio'     => $c->support_ratio ?? 1.0,
                'stability_warning' => $c->stability_warning,
            ];
        }
        ksort($byZ);

        $layers = [];
        $idx    = 0;
        foreach ($byZ as $z => $items) {
            $idx++;
            usort($items, fn ($a, $b) => $a['position'] <=> $b['position']);
            $layers[] = [
                'index' => $idx,
                'z'     => $z,
                'items' => $items,
            ];
        }
        return $layers;
    }

    private function buildStepsFromContents($contents): array
    {
        $sorted = $contents->sortBy('position')->values();
        $steps  = [];

        foreach ($sorted as $i => $c) {
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
                'support_ratio'     => $c->support_ratio ?? 1.0,
                'stability_warning' => $c->stability_warning,
                'text'              => $c->step_text,
            ];
        }

        return $steps;
    }
}
