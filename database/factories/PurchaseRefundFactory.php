<?php

namespace Database\Factories;

use App\Models\BatchItem;
use App\Models\PurchaseRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseRefund> */
class PurchaseRefundFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'batch_item_id' => BatchItem::factory(),
            'quantity' => 1,
            'refunded_at' => now(),
        ];
    }
}
