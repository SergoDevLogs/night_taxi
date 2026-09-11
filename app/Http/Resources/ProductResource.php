<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'sku'        => $this->sku,
            'name'       => $this->name,
            'height'     => $this->height,
            'length'     => $this->length,
            'width'      => $this->width,
            'weight'     => $this->weight,
            'can_rotate' => $this->can_rotate,
            'fragile'    => $this->fragile,
            'top_bottom' => $this->top_bottom,
        ];
    }
}
