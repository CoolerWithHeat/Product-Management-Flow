<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'client_id' => ['bail', 'required', 'integer:strict', 'exists:clients,id'],
            'products' => ['required', 'array', 'list', 'min:1'],
            'products.*' => ['required', 'array:id,qty'],
            'products.*.id' => ['bail', 'required', 'integer:strict', 'distinct', 'exists:products,id'],
            'products.*.qty' => ['bail', 'required', 'integer:strict', 'min:1', 'max:4294967295'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['client_id' => 'client', 'products.*.id' => 'product', 'products.*.qty' => 'quantity'];
    }
}
