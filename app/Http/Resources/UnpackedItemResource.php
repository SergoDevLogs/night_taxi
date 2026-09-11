<?php

namespace App\Http\Resources;

use App\Models\Box;
use App\Services\ReasonTranslator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnpackedItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;

        $message = null;
        $context = null;

        if ($product) {
            /** @var ReasonTranslator $translator */
            $translator = app(ReasonTranslator::class);

            $boxes = Box::all()->all();

            $translated = $translator->translate($this->reason, $product, $boxes);
            $message    = $translated['message'];
            $context    = $translated['context'];
        }

        return [
            'product_id'     => $this->product_id,
            'product'        => new ProductResource($this->whenLoaded('product')),
            'quantity'       => $this->quantity,
            'reason'         => $this->reason,
            'reason_message' => $message,
            'reason_context' => $context,
        ];
    }
}
