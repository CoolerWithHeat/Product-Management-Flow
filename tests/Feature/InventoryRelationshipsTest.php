<?php

namespace Tests\Feature;

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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryRelationshipsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_products_follow_category_tree_to_provider_and_batch_storage(): void
    {
        $provider = Provider::factory()->create();
        $root = Category::factory()->for($provider)->create();
        $category = Category::factory()->childOf($root)->create();
        $product = Product::factory()->for($category)->create();
        $storage = Storage::factory()->create();
        $batch = Batch::factory()->for($provider)->for($storage)->create();
        $item = BatchItem::factory()->for($batch)->for($product)->create([
            'quantity' => 10,
            'unit_cost' => '12.35',
        ]);

        $purchasedProduct = $batch->products()->firstOrFail();

        $this->assertTrue($product->category->parent->provider->is($provider));
        $this->assertNull($category->provider_id);
        $this->assertTrue($root->children->sole()->is($category));
        $this->assertTrue($provider->categories->sole()->is($root));
        $this->assertTrue($category->products->sole()->is($product));
        $this->assertTrue($storage->batches->sole()->is($batch));
        $this->assertTrue($provider->batches->sole()->is($batch));
        $this->assertTrue($batch->storage->is($storage));
        $this->assertTrue($batch->provider->is($provider));
        $this->assertTrue($batch->items->sole()->is($item));
        $this->assertTrue($product->batchItems->sole()->is($item));
        $this->assertTrue($product->batches->sole()->is($batch));
        $this->assertTrue($purchasedProduct->is($product));
        $this->assertSame($item->id, $purchasedProduct->pivot->id);
        $this->assertSame(10, (int) $purchasedProduct->pivot->quantity);
        $this->assertSame('12.35', $item->fresh()->unit_cost);
    }

    public function test_orders_can_split_one_product_between_batches_and_preserve_sale_prices(): void
    {
        $product = Product::factory()->create(['price' => '19.95']);
        $provider = $product->category->provider;
        $storage = Storage::factory()->create();
        $olderBatch = Batch::factory()->for($provider)->for($storage)->create([
            'purchased_at' => '2026-10-20 09:00:00',
        ]);
        $newerBatch = Batch::factory()->for($provider)->for($storage)->create([
            'purchased_at' => '2026-10-21 09:00:00',
        ]);
        $olderItem = BatchItem::factory()->for($olderBatch)->for($product)->create([
            'quantity' => 5,
            'unit_cost' => '10.10',
        ]);
        $newerItem = BatchItem::factory()->for($newerBatch)->for($product)->create([
            'quantity' => 10,
            'unit_cost' => '12.20',
        ]);
        $client = Client::factory()->create();
        $order = Order::factory()->for($client)->create([
            'ordered_at' => '2026-10-22 10:00:00',
        ]);
        OrderItem::factory()->for($order)->for($olderItem, 'batchItem')->create([
            'quantity' => 5,
            'unit_price' => '19.95',
        ]);
        OrderItem::factory()->for($order)->for($newerItem, 'batchItem')->create([
            'quantity' => 3,
            'unit_price' => '19.95',
        ]);

        $product->update(['price' => '25.00']);
        $items = $order->items()->with('batchItem.product')->orderBy('id')->get();

        $this->assertTrue($order->client->is($client));
        $this->assertTrue($client->orders->sole()->is($order));
        $this->assertCount(2, $items);
        $this->assertSame(8, $items->sum('quantity'));
        $this->assertSame([$olderItem->id, $newerItem->id], $items->pluck('batch_item_id')->all());
        $this->assertSame([$product->id, $product->id], $items->pluck('batchItem.product.id')->all());
        $this->assertSame(['19.95', '19.95'], $items->pluck('unit_price')->all());
        $this->assertSame('25.00', $product->fresh()->price);
        $this->assertTrue($items->first()->order->is($order));
        $this->assertTrue($olderItem->orderItems->sole()->is($items->first()));
        $this->assertSame('2026-10-20 09:00:00', $olderBatch->fresh()->purchased_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-22 10:00:00', $order->fresh()->ordered_at->format('Y-m-d H:i:s'));
    }

    public function test_partial_refunds_keep_original_purchase_and_sale_references_and_dates(): void
    {
        $batch = Batch::factory()->create(['purchased_at' => '2026-10-20 09:00:00']);
        $batchItem = BatchItem::factory()->for($batch)->create(['quantity' => 10]);
        $order = Order::factory()->create(['ordered_at' => '2026-10-21 10:00:00']);
        $orderItem = OrderItem::factory()->for($order)->for($batchItem)->create([
            'quantity' => 4,
        ]);
        $firstPurchaseRefund = PurchaseRefund::factory()->for($batchItem)->create([
            'quantity' => 2,
            'refunded_at' => '2026-10-23 11:00:00',
        ]);
        PurchaseRefund::factory()->for($batchItem)->create([
            'quantity' => 1,
            'refunded_at' => '2026-10-24 11:00:00',
        ]);
        $saleRefund = SaleRefund::factory()->for($orderItem)->create([
            'quantity' => 2,
            'refunded_at' => '2026-10-24 12:00:00',
        ]);

        $purchaseRefunds = $batchItem->purchaseRefunds()->orderBy('refunded_at')->get();

        $this->assertCount(2, $purchaseRefunds);
        $this->assertSame(3, $purchaseRefunds->sum('quantity'));
        $this->assertSame(
            ['2026-10-23 11:00:00', '2026-10-24 11:00:00'],
            $purchaseRefunds->map(fn (PurchaseRefund $refund): string => $refund->refunded_at->format('Y-m-d H:i:s'))->all(),
        );
        $this->assertTrue($firstPurchaseRefund->batchItem->is($batchItem));
        $this->assertCount(2, $batchItem->batch->purchaseRefunds);
        $this->assertTrue($saleRefund->orderItem->batchItem->is($batchItem));
        $this->assertTrue($orderItem->saleRefunds->sole()->is($saleRefund));
        $this->assertTrue($orderItem->order->saleRefunds->sole()->is($saleRefund));
        $this->assertSame('2026-10-24 12:00:00', $saleRefund->fresh()->refunded_at->format('Y-m-d H:i:s'));
        $this->assertSame(10, $batchItem->fresh()->quantity);
        $this->assertSame(4, $orderItem->fresh()->quantity);
    }

    /** @param class-string<Model> $modelClass
     * @param  array<string, int>  $attributes
     */
    #[DataProvider('invalidReferences')]
    public function test_rejects_unknown_related_records(string $modelClass, array $attributes): void
    {
        $this->expectException(QueryException::class);

        $modelClass::factory()->create($attributes);
    }

    /** @return array<string, array{class-string<Model>, array<string, int>}> */
    public static function invalidReferences(): array
    {
        return [
            'product category' => [Product::class, ['category_id' => 999999]],
            'category parent' => [Category::class, ['parent_id' => 999999, 'provider_id' => null]],
            'batch provider' => [Batch::class, ['provider_id' => 999999]],
            'batch storage' => [Batch::class, ['storage_id' => 999999]],
            'purchased product' => [BatchItem::class, ['product_id' => 999999]],
            'order client' => [Order::class, ['client_id' => 999999]],
            'sold batch item' => [OrderItem::class, ['batch_item_id' => 999999]],
            'purchase refund source' => [PurchaseRefund::class, ['batch_item_id' => 999999]],
            'sale refund source' => [SaleRefund::class, ['order_item_id' => 999999]],
        ];
    }

    public function test_a_product_cannot_appear_twice_in_the_same_purchase_batch(): void
    {
        $item = BatchItem::factory()->create();

        $this->expectException(QueryException::class);

        BatchItem::factory()->for($item->batch)->for($item->product)->create();
    }

    public function test_purchase_history_prevents_deleting_its_batch(): void
    {
        $item = BatchItem::factory()->create();

        $this->expectException(QueryException::class);

        $item->batch->delete();
    }

    public function test_sale_refunds_prevent_deleting_the_original_sale_item(): void
    {
        $refund = SaleRefund::factory()->create();

        $this->expectException(QueryException::class);

        $refund->orderItem->delete();
    }
}
