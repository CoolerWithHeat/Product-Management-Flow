<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseRefund;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array{provider_id: int, storage_id: int, products: array<int, array{id: int, qty: int, unit_cost: int|float|string}>}  $data
     */
    public function purchase(array $data): Batch
    {
        return DB::transaction(function () use ($data): Batch {
            $products = Product::query()
                ->with('category')
                ->whereKey(array_column($data['products'], 'id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($data['products'] as $index => $line) {
                $product = $products->get($line['id']);

                if (! $product || $this->rootProviderId($product->category) !== $data['provider_id']) {
                    throw ValidationException::withMessages([
                        "products.{$index}.id" => 'This product does not belong to the selected provider.',
                    ]);
                }
            }

            $batch = Batch::create([
                'provider_id' => $data['provider_id'],
                'storage_id' => $data['storage_id'],
                'purchased_at' => now(),
            ]);

            foreach ($data['products'] as $line) {
                $batch->items()->create([
                    'product_id' => $line['id'],
                    'quantity' => $line['qty'],
                    'unit_cost' => $line['unit_cost'],
                ]);
            }

            return $batch->load('items');
        }, 3);
    }

    /**
     * @param  array{full?: bool, products?: array<int, array{id: int, qty: int}>}  $data
     * @return Collection<int, PurchaseRefund>
     */
    public function refund(Batch $batch, array $data): Collection
    {
        return DB::transaction(function () use ($batch, $data): Collection {
            $batch = Batch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $items = $batch->items()->orderBy('id')->lockForUpdate()->get();
            $available = $this->stock->availableQuantities($items);
            $itemsByProduct = $items->keyBy('product_id');

            if ($data['full'] ?? false) {
                $requested = $items
                    ->map(fn (BatchItem $item): array => [
                        'id' => $item->product_id,
                        'qty' => $available[$item->id],
                    ])
                    ->filter(fn (array $line): bool => $line['qty'] > 0)
                    ->values()
                    ->all();

                if ($requested === []) {
                    throw ValidationException::withMessages([
                        'batch' => 'This batch has no available stock to refund.',
                    ]);
                }
            } else {
                $requested = $data['products'];
            }

            $refunds = new Collection;
            $refundedAt = now();

            foreach ($requested as $index => $line) {
                $item = $itemsByProduct->get($line['id']);

                if (! $item) {
                    throw ValidationException::withMessages([
                        "products.{$index}.id" => 'This product is not part of the selected batch.',
                    ]);
                }

                $remaining = $available[$item->id];

                if ($line['qty'] > $remaining) {
                    throw ValidationException::withMessages([
                        "products.{$index}.qty" => "Refund quantity exceeds available stock ({$remaining}).",
                    ]);
                }

                $refunds->push($item->purchaseRefunds()->create([
                    'quantity' => $line['qty'],
                    'refunded_at' => $refundedAt,
                ]));
            }

            return $refunds;
        }, 3);
    }

    private function rootProviderId(Category $category): ?int
    {
        $visited = [];

        while ($category->parent_id !== null) {
            if (isset($visited[$category->id])) {
                return null;
            }

            $visited[$category->id] = true;
            $category = $category->parent;

            if (! $category) {
                return null;
            }
        }

        return $category->provider_id;
    }
}
