<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\SaleRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaleRefund> */
class SaleRefundFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'quantity' => 1,
            'refunded_at' => now(),
        ];
    }
}
