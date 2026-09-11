<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'inner_height'       => ['required', 'integer', 'min:1'],
            'inner_length'       => ['required', 'integer', 'min:1'],
            'inner_width'        => ['required', 'integer', 'min:1'],
            'max_weight'         => ['required', 'integer', 'min:1'],
            'available_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
