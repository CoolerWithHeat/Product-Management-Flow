<?php

namespace App\Http\Resources;

use App\Models\SaleRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleRefund */
class SaleRefundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
            'refunded_at' => $this->refunded_at->toISOString(),
        ];
    }
}
