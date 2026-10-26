<?php

namespace App\Http\Requests;

class StockReportRequest extends StockQueryRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [...parent::rules(), 'date' => ['required', 'date_format:Y-m-d']];
    }
}
