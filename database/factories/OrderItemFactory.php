<?php

namespace Database\Factories;

use App\Models\BatchItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'batch_item_id' => BatchItem::factory(),
            'quantity' => 1,
            'unit_price' => '15.00',
        ];
    }
}
