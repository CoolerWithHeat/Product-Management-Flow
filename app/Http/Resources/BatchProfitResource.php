<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchProfitResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        return [
            'batch_id' => $this->resource['batch_id'],
            'provider_id' => $this->resource['provider_id'],
            'storage_id' => $this->resource['storage_id'],
            'purchase_cost' => $this->resource['purchase_cost'],
            'supplier_refunds' => $this->resource['supplier_refunds'],
            'net_purchase_cost' => $this->resource['net_purchase_cost'],
            'sales_revenue' => $this->resource['sales_revenue'],
            'client_refunds' => $this->resource['client_refunds'],
            'net_revenue' => $this->resource['net_revenue'],
            'cost_of_goods_sold' => $this->resource['cost_of_goods_sold'],
            'profit' => $this->resource['profit'],
        ];
    }
}
