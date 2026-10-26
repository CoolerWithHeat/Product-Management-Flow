<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\PurchaseRefund;
use App\Models\SaleRefund;
use App\Models\Storage;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $provider = Provider::factory()->create(['name' => 'Ahmad Tea']);
        $root = Category::factory()->for($provider)->create(['name' => 'Ahmad Tea']);
        $category = Category::factory()->childOf($root)->create(['name' => 'Black Tea']);
        $product = Product::factory()->for($category)->create([
            'name' => 'Ahmad Tea Earl Grey, 500g',
            'price' => '15.00',
        ]);
        $storage = Storage::factory()->create(['name' => 'Main Warehouse']);
        $client = Client::factory()->create(['name' => 'Sample Market']);
        $batch = Batch::factory()->for($provider)->for($storage)->create([
            'purchased_at' => '2026-10-20 09:00:00',
        ]);
        $batchItem = BatchItem::factory()->for($batch)->for($product)->create([
            'quantity' => 10,
            'unit_cost' => '10.00',
        ]);
        $order = Order::factory()->for($client)->create([
            'ordered_at' => '2026-10-21 10:00:00',
        ]);
        $orderItem = OrderItem::factory()->for($order)->for($batchItem)->create([
            'quantity' => 3,
            'unit_price' => '15.00',
        ]);
        PurchaseRefund::factory()->for($batchItem)->create([
            'quantity' => 2,
            'refunded_at' => '2026-10-22 11:00:00',
        ]);
        SaleRefund::factory()->for($orderItem)->create([
            'quantity' => 1,
            'refunded_at' => '2026-10-23 12:00:00',
        ]);
    }
}
