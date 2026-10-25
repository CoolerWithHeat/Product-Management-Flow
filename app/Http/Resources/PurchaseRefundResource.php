<?php

namespace App\Http\Resources;

use App\Models\PurchaseRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseRefund */
class PurchaseRefundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_item_id' => $this->batch_item_id,
            'quantity' => $this->quantity,
            'refunded_at' => $this->refunded_at->toISOString(),
        ];
    }
}
