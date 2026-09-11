<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'inner_height'       => $this->inner_height,
            'inner_length'       => $this->inner_length,
            'inner_width'        => $this->inner_width,
            'max_weight'         => $this->max_weight,
            'available_quantity' => $this->available_quantity,
            'volume'             => $this->volume,
            'biggest_side'       => $this->biggest_side,
        ];
    }
}
