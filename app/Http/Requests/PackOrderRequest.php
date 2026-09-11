<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PackOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'generate_instruction' => ['sometimes', 'boolean'],
        ];
    }
}
