<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackingContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'product'           => new ProductResource($this->whenLoaded('product')),
            'quantity'          => $this->quantity,
            'position'          => $this->position,
            'orientation'       => $this->orientation->value,
            'x'                 => $this->x,
            'y'                 => $this->y,
            'z'                 => $this->z,
            'position_hint'     => $this->position_hint,
            'step_text'         => $this->step_text,
            'supported_by'      => $this->supported_by ?? [],
            'neighbors'         => $this->neighbors ?? [],
            'support_ratio'     => $this->support_ratio ?? 1.0,
            'stability_warning' => $this->stability_warning,
        ];
    }
}
