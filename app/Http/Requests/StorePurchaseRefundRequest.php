<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'full' => ['sometimes', 'boolean:strict'],
            'products' => [
                Rule::requiredIf(fn (): bool => ! $this->boolean('full')),
                Rule::prohibitedIf(fn (): bool => $this->boolean('full')),
                'array',
                'list',
                'min:1',
            ],
            'products.*' => ['required', 'array:id,qty'],
            'products.*.id' => ['bail', 'required', 'integer:strict', 'distinct'],
            'products.*.qty' => ['bail', 'required', 'integer:strict', 'min:1', 'max:4294967295'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'products.*.id' => 'product',
            'products.*.qty' => 'quantity',
        ];
    }
}
