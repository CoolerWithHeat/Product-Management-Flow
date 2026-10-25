<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'provider_id' => ['bail', 'required', 'integer:strict', 'exists:providers,id'],
            'storage_id' => ['bail', 'required', 'integer:strict', 'exists:storages,id'],
            'products' => ['required', 'array', 'list', 'min:1'],
            'products.*' => ['required', 'array:id,qty,unit_cost'],
            'products.*.id' => ['bail', 'required', 'integer:strict', 'distinct', 'exists:products,id'],
            'products.*.qty' => ['bail', 'required', 'integer:strict', 'min:1', 'max:4294967295'],
            'products.*.unit_cost' => ['bail', 'required', 'numeric', 'decimal:0,2', 'between:0,999999999999.99'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'provider_id' => 'provider',
            'storage_id' => 'storage',
            'products.*.id' => 'product',
            'products.*.qty' => 'quantity',
            'products.*.unit_cost' => 'unit cost',
        ];
    }
}
