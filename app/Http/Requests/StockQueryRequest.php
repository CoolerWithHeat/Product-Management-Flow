<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['storage_id' => ['sometimes', 'integer', 'exists:storages,id']];
    }
}
