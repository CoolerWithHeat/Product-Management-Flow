<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockReportResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        return [
            'storage_id' => $this->resource['storage_id'],
            'storage_name' => $this->resource['storage_name'],
            'product_id' => $this->resource['product_id'],
            'product_name' => $this->resource['product_name'],
            'qty' => $this->resource['qty'],
        ];
    }
}
