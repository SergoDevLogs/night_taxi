<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $productId = $this->route('id');

        return [
            'sku'        => ['required', 'string', 'max:64',
                             Rule::unique('products', 'sku')->ignore($productId)],
            'name'       => ['required', 'string', 'max:255'],
            'height'     => ['required', 'integer', 'min:1'],
            'length'     => ['required', 'integer', 'min:1'],
            'width'      => ['required', 'integer', 'min:1'],
            'weight'     => ['required', 'integer', 'min:1'],
            'can_rotate' => ['sometimes', 'boolean'],
            'fragile'    => ['sometimes', 'boolean'],
            'top_bottom' => ['sometimes', 'boolean'],
        ];
    }
}
