<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PurchaseRefund;
use App\Models\SaleRefund;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundPurchaseBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_refunds_part_of_a_purchase_without_changing_original_quantity_or_cost(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00'));
        $item = BatchItem::factory()->create(['quantity' => 10, 'unit_cost' => '12.35']);
        $payload = [
            'refunded_at' => '2000-01-01 00:00:00',
            'products' => [['id' => $item->product_id, 'qty' => 3]],
        ];

        $response = $this->postJson($this->refundUrl($item->batch), $payload);

        $response->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.batch_item_id', $item->id)
            ->assertJsonPath('data.0.quantity', 3)
            ->assertJsonPath('data.0.refunded_at', '2026-10-25T10:00:00.000000Z');
        $this->assertDatabaseCount('purchase_refunds', 1);
        $this->assertDatabaseHas('purchase_refunds', [
            'batch_item_id' => $item->id,
            'quantity' => 3,
            'refunded_at' => '2026-10-25 10:00:00',
        ]);
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertSame('12.35', $item->fresh()->unit_cost);
        $this->assertModelExists($item->batch);
    }

    public function test_full_refund_uses_unsold_stock_prior_supplier_refunds_and_client_returns(): void
    {
        [$first, $second] = $this->purchaseItems();
        $sale = OrderItem::factory()->for($first, 'batchItem')->create(['quantity' => 4]);
        SaleRefund::factory()->for($sale, 'orderItem')->create(['quantity' => 1]);
        PurchaseRefund::factory()->for($first, 'batchItem')->create(['quantity' => 2]);

        $response = $this->postJson($this->refundUrl($first->batch), ['full' => true]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.batch_item_id', $first->id)
            ->assertJsonPath('data.0.quantity', 5)
            ->assertJsonPath('data.1.batch_item_id', $second->id)
            ->assertJsonPath('data.1.quantity', 5);
        $this->assertDatabaseCount('purchase_refunds', 3);
        $this->assertDatabaseHas('purchase_refunds', ['batch_item_id' => $first->id, 'quantity' => 5]);
        $this->assertDatabaseHas('purchase_refunds', ['batch_item_id' => $second->id, 'quantity' => 5]);
        $this->assertSame(10, $first->fresh()->quantity);
        $this->assertSame(5, $second->fresh()->quantity);
        $this->assertSame(4, $sale->fresh()->quantity);
    }

    public function test_returns_422_when_refund_exceeds_available_stock(): void
    {
        $item = BatchItem::factory()->create(['quantity' => 10]);
        OrderItem::factory()->for($item, 'batchItem')->create(['quantity' => 4]);

        $response = $this->postJson($this->refundUrl($item->batch), [
            'products' => [['id' => $item->product_id, 'qty' => 7]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products.0.qty' => 'Refund quantity exceeds available stock (6).']);
        $this->assertDatabaseCount('purchase_refunds', 0);
        $this->assertSame(10, $item->fresh()->quantity);
    }

    public function test_returns_422_when_prior_refunds_have_used_the_available_quantity(): void
    {
        $item = BatchItem::factory()->create(['quantity' => 10]);
        PurchaseRefund::factory()->for($item, 'batchItem')->create(['quantity' => 7]);

        $response = $this->postJson($this->refundUrl($item->batch), [
            'products' => [['id' => $item->product_id, 'qty' => 4]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products.0.qty' => 'Refund quantity exceeds available stock (3).']);
        $this->assertDatabaseCount('purchase_refunds', 1);
    }

    public function test_returns_422_for_a_product_outside_the_selected_batch(): void
    {
        $item = BatchItem::factory()->create();
        $otherProduct = Product::factory()->create();

        $response = $this->postJson($this->refundUrl($item->batch), [
            'products' => [['id' => $otherProduct->id, 'qty' => 1]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products.0.id' => 'This product is not part of the selected batch.']);
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_rolls_back_earlier_refund_items_when_a_later_item_has_insufficient_stock(): void
    {
        [$first, $second] = $this->purchaseItems();

        $response = $this->postJson($this->refundUrl($first->batch), [
            'products' => [
                ['id' => $first->product_id, 'qty' => 2],
                ['id' => $second->product_id, 'qty' => 6],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products.1.qty' => 'Refund quantity exceeds available stock (5).']);
        $this->assertDatabaseCount('purchase_refunds', 0);
        $this->assertSame(10, $first->fresh()->quantity);
        $this->assertSame(5, $second->fresh()->quantity);
    }

    public function test_client_returns_in_another_batch_do_not_increase_this_batchs_stock(): void
    {
        $item = BatchItem::factory()->create(['quantity' => 1]);
        $otherItem = BatchItem::factory()->for($item->product)->create(['quantity' => 10]);
        $otherSale = OrderItem::factory()->for($otherItem, 'batchItem')->create(['quantity' => 10]);
        SaleRefund::factory()->for($otherSale, 'orderItem')->create(['quantity' => 10]);

        $response = $this->postJson($this->refundUrl($item->batch), [
            'products' => [['id' => $item->product_id, 'qty' => 2]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products.0.qty' => 'Refund quantity exceeds available stock (1).']);
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_returns_422_for_a_full_refund_when_no_stock_remains(): void
    {
        $item = BatchItem::factory()->create(['quantity' => 10]);
        OrderItem::factory()->for($item, 'batchItem')->create(['quantity' => 10]);

        $response = $this->postJson($this->refundUrl($item->batch), ['full' => true]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['batch' => 'This batch has no available stock to refund.']);
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_full_refund_skips_products_whose_available_stock_is_zero(): void
    {
        [$first, $second] = $this->purchaseItems();
        OrderItem::factory()->for($first, 'batchItem')->create(['quantity' => 10]);

        $response = $this->postJson($this->refundUrl($first->batch), ['full' => true]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.batch_item_id', $second->id)
            ->assertJsonPath('data.0.quantity', 5);
        $this->assertDatabaseCount('purchase_refunds', 1);
    }

    public function test_returns_404_for_an_unknown_batch(): void
    {
        $response = $this->postJson(route('api.batches.refunds.store', ['batch' => 999999], false), ['full' => true]);

        $response->assertNotFound();
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_returns_422_when_neither_products_nor_full_refund_is_requested(): void
    {
        $item = BatchItem::factory()->create();

        $response = $this->postJson($this->refundUrl($item->batch), []);

        $response->assertUnprocessable()->assertJsonValidationErrors('products');
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_returns_422_when_full_refund_and_product_quantities_are_combined(): void
    {
        $item = BatchItem::factory()->create();

        $response = $this->postJson($this->refundUrl($item->batch), [
            'full' => true,
            'products' => [['id' => $item->product_id, 'qty' => 1]],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('products');
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_returns_422_for_duplicate_refund_products(): void
    {
        $item = BatchItem::factory()->create(['quantity' => 10]);

        $response = $this->postJson($this->refundUrl($item->batch), [
            'products' => [
                ['id' => $item->product_id, 'qty' => 8],
                ['id' => $item->product_id, 'qty' => 8],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('products.1.id');
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    #[DataProvider('invalidRefundQuantities')]
    public function test_returns_422_for_invalid_refund_quantities(mixed $quantity, string $message): void
    {
        $item = BatchItem::factory()->create();

        $response = $this->postJson($this->refundUrl($item->batch), [
            'products' => [['id' => $item->product_id, 'qty' => $quantity]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products.0.qty' => $message]);
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidRefundQuantities(): array
    {
        return [
            'zero' => [0, 'The quantity field must be at least 1.'],
            'negative' => [-1, 'The quantity field must be at least 1.'],
            'fractional' => [1.5, 'The quantity field must be an integer.'],
            'boolean' => [true, 'The quantity field must be an integer.'],
            'overflow' => [4294967296, 'The quantity field must not be greater than 4294967295.'],
        ];
    }

    public function test_returns_422_when_refund_products_are_not_a_list(): void
    {
        $item = BatchItem::factory()->create();

        $response = $this->postJson($this->refundUrl($item->batch), [
            'products' => ['first' => ['id' => $item->product_id, 'qty' => 1]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products' => 'The products field must be a list.']);
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_returns_422_when_full_is_not_a_json_boolean(): void
    {
        $item = BatchItem::factory()->create();

        $response = $this->postJson($this->refundUrl($item->batch), ['full' => 'true']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['full' => 'The full field must be true or false.']);
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    private function refundUrl(Batch $batch): string
    {
        return route('api.batches.refunds.store', ['batch' => $batch->id], false);
    }

    /** @return array{BatchItem, BatchItem} */
    private function purchaseItems(): array
    {
        $batch = Batch::factory()->create();

        return [
            BatchItem::factory()->for($batch)->create(['quantity' => 10]),
            BatchItem::factory()->for($batch)->create(['quantity' => 5]),
        ];
    }
}
