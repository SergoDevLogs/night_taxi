<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'number'     => $this->number,
            'created_at' => $this->created_at?->toIso8601String(),
            'status'     => $this->status->value,
            'items'      => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
