<?php

namespace Tests\Feature;

use App\Models\BatchItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Storage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PurchaseBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_receives_multiple_products_into_one_storage_and_returns_201(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00:00'));
        $provider = Provider::factory()->create();
        $storage = Storage::factory()->create();
        $root = Category::factory()->for($provider)->create();
        $child = Category::factory()->childOf($root)->create();
        $grandchild = Category::factory()->childOf($child)->create();
        $firstProduct = Product::factory()->for($grandchild)->create(['price' => '25.00']);
        $secondProduct = Product::factory()->for($root)->create();
        $payload = [
            'provider_id' => $provider->id,
            'storage_id' => $storage->id,
            'purchased_at' => '2000-01-01 00:00:00',
            'products' => [
                ['id' => $firstProduct->id, 'qty' => 10, 'unit_cost' => '12.35'],
                ['id' => $secondProduct->id, 'qty' => 5, 'unit_cost' => '0.10'],
            ],
        ];

        $response = $this->postJson(route('api.batches.store', [], false), $payload);

        $response->assertCreated()
            ->assertJsonPath('data.provider_id', $provider->id)
            ->assertJsonPath('data.storage_id', $storage->id)
            ->assertJsonPath('data.purchased_at', '2026-10-25T10:00:00.000000Z')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $firstProduct->id)
            ->assertJsonPath('data.items.0.quantity', 10)
            ->assertJsonPath('data.items.0.unit_cost', '12.35')
            ->assertJsonPath('data.items.1.unit_cost', '0.10');
        $this->assertDatabaseCount('batches', 1);
        $this->assertDatabaseCount('batch_items', 2);
        $this->assertDatabaseHas('batches', [
            'id' => $response->json('data.id'),
            'provider_id' => $provider->id,
            'storage_id' => $storage->id,
            'purchased_at' => '2026-10-25 10:00:00',
        ]);
        $this->assertDatabaseHas('batch_items', [
            'batch_id' => $response->json('data.id'),
            'product_id' => $secondProduct->id,
            'quantity' => 5,
            'unit_cost' => '0.10',
        ]);
        $this->assertSame('25.00', $firstProduct->fresh()->price);
        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    public function test_returns_422_for_missing_purchase_fields_without_creating_stock(): void
    {
        $response = $this->postJson(route('api.batches.store', [], false), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider_id', 'storage_id', 'products'])
            ->assertJsonValidationErrors(['provider_id' => 'The provider field is required.']);
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('batch_items', 0);
    }

    #[DataProvider('invalidPurchaseValues')]
    public function test_returns_422_for_invalid_purchase_values(string $field, mixed $value, string $message): void
    {
        $payload = $this->purchasePayload();
        data_set($payload, $field, $value);

        $response = $this->postJson(route('api.batches.store', [], false), $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('batch_items', 0);
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function invalidPurchaseValues(): array
    {
        return [
            'unknown provider' => ['provider_id', 999999, 'The selected provider is invalid.'],
            'unknown storage' => ['storage_id', 999999, 'The selected storage is invalid.'],
            'unknown product' => ['products.0.id', 999999, 'The selected product is invalid.'],
            'zero quantity' => ['products.0.qty', 0, 'The quantity field must be at least 1.'],
            'negative quantity' => ['products.0.qty', -1, 'The quantity field must be at least 1.'],
            'fractional quantity' => ['products.0.qty', 1.5, 'The quantity field must be an integer.'],
            'boolean quantity' => ['products.0.qty', true, 'The quantity field must be an integer.'],
            'quantity exceeds column capacity' => ['products.0.qty', 4294967296, 'The quantity field must not be greater than 4294967295.'],
            'negative cost' => ['products.0.unit_cost', '-0.01', 'The unit cost field must be between 0 and 999999999999.99.'],
            'too many decimal places' => ['products.0.unit_cost', '10.123', 'The unit cost field must have 0-2 decimal places.'],
            'cost exceeds column capacity' => ['products.0.unit_cost', '1000000000000.00', 'The unit cost field must be between 0 and 999999999999.99.'],
            'non-numeric cost' => ['products.0.unit_cost', 'cheap', 'The unit cost field must be a number.'],
        ];
    }

    public function test_returns_422_when_a_product_belongs_to_another_provider(): void
    {
        $payload = $this->purchasePayload();
        $foreignProduct = Product::factory()->create();
        $payload['products'][] = ['id' => $foreignProduct->id, 'qty' => 1, 'unit_cost' => '10.00'];

        $response = $this->postJson(route('api.batches.store', [], false), $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products.1.id' => 'This product does not belong to the selected provider.']);
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('batch_items', 0);
    }

    public function test_returns_422_for_duplicate_products_without_creating_a_batch(): void
    {
        $payload = $this->purchasePayload();
        $payload['products'][] = $payload['products'][0];

        $response = $this->postJson(route('api.batches.store', [], false), $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('products.1.id');
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('batch_items', 0);
    }

    public function test_returns_422_for_unexpected_attributes_in_product_entries(): void
    {
        $payload = $this->purchasePayload();
        $payload['products'][0]['batch_id'] = 999999;

        $response = $this->postJson(route('api.batches.store', [], false), $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('products.0');
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('batch_items', 0);
    }

    public function test_rejects_a_cyclic_category_tree_without_creating_a_batch(): void
    {
        $payload = $this->purchasePayload();
        $category = Product::findOrFail($payload['products'][0]['id'])->category;
        $category->update(['parent_id' => $category->id]);

        $response = $this->postJson(route('api.batches.store', [], false), $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('products.0.id');
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('batch_items', 0);
    }

    public function test_rolls_back_the_entire_purchase_if_an_item_write_fails(): void
    {
        $payload = $this->purchasePayload();
        $provider = Provider::findOrFail($payload['provider_id']);
        $product = Product::factory()->for(Category::factory()->for($provider))->create();
        $payload['products'][] = ['id' => $product->id, 'qty' => 2, 'unit_cost' => '1.00'];
        $writes = 0;
        BatchItem::creating(function () use (&$writes): void {
            if (++$writes === 2) {
                throw new RuntimeException('Simulated item write failure.');
            }
        });

        try {
            $response = $this->postJson(route('api.batches.store', [], false), $payload);

            $response->assertServerError();
            $this->assertDatabaseCount('batches', 0);
            $this->assertDatabaseCount('batch_items', 0);
        } finally {
            BatchItem::flushEventListeners();
        }
    }

    public function test_returns_422_when_products_are_an_object_instead_of_a_list(): void
    {
        $payload = $this->purchasePayload();
        $payload['products'] = ['first' => $payload['products'][0]];

        $response = $this->postJson(route('api.batches.store', [], false), $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['products' => 'The products field must be a list.']);
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('batch_items', 0);
    }

    /** @return array{provider_id: int, storage_id: int, products: array<int, array{id: int, qty: int, unit_cost: string}>} */
    private function purchasePayload(): array
    {
        $provider = Provider::factory()->create();
        $storage = Storage::factory()->create();
        $product = Product::factory()->for(Category::factory()->for($provider))->create();

        return [
            'provider_id' => $provider->id,
            'storage_id' => $storage->id,
            'products' => [['id' => $product->id, 'qty' => 10, 'unit_cost' => '12.35']],
        ];
    }
}
