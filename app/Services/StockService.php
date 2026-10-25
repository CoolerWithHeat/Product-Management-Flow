<?php

namespace App\Services;

use App\Models\BatchItem;
use App\Models\OrderItem;
use App\Models\PurchaseRefund;
use App\Models\SaleRefund;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Stock writers must lock the batch items before calling this method.
     *
     * @param  Collection<int, BatchItem>  $items
     * @return array<int, int>
     */
    public function availableQuantities(Collection $items, ?CarbonImmutable $at = null): array
    {
        $at ??= now()->toImmutable();
        $items->loadMissing('batch');
        $ids = $items->modelKeys();

        $sold = OrderItem::query()
            ->whereIn('batch_item_id', $ids)
            ->whereHas('order', fn (Builder $query) => $query->where('ordered_at', '<=', $at))
            ->selectRaw('batch_item_id, SUM(quantity) AS total')
            ->groupBy('batch_item_id')
            ->pluck('total', 'batch_item_id');

        $clientRefunds = SaleRefund::query()
            ->join('order_items', 'order_items.id', '=', 'sale_refunds.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.batch_item_id', $ids)
            ->where('orders.ordered_at', '<=', $at)
            ->where('sale_refunds.refunded_at', '<=', $at)
            ->selectRaw('order_items.batch_item_id, SUM(sale_refunds.quantity) AS total')
            ->groupBy('order_items.batch_item_id')
            ->pluck('total', 'order_items.batch_item_id');

        $supplierRefunds = PurchaseRefund::query()
            ->whereIn('batch_item_id', $ids)
            ->where('refunded_at', '<=', $at)
            ->selectRaw('batch_item_id, SUM(quantity) AS total')
            ->groupBy('batch_item_id')
            ->pluck('total', 'batch_item_id');

        $quantities = [];

        foreach ($items as $item) {
            $received = $item->batch->purchased_at->lessThanOrEqualTo($at) ? $item->quantity : 0;
            $soldQuantity = (int) ($sold[$item->id] ?? 0);
            $clientReturned = (int) ($clientRefunds[$item->id] ?? 0);
            $supplierReturned = (int) ($supplierRefunds[$item->id] ?? 0);

            $quantities[$item->id] = $received - $soldQuantity + $clientReturned - $supplierReturned;
        }

        return $quantities;
    }

    /** @return array<int, array<string, int|string>> */
    public function availableProducts(?int $storageId = null): array
    {
        return DB::transaction(function () use ($storageId): array {
            $at = now()->toImmutable();
            $items = $this->itemsAt($at, $storageId);
            $quantities = $this->availableQuantities($items, $at);
            $products = [];

            foreach ($items as $item) {
                $product = $item->product;

                if (! isset($products[$product->id])) {
                    $products[$product->id] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'category_name' => $product->category->name,
                        'price' => $product->price,
                        'qty' => 0,
                    ];
                }

                $products[$product->id]['qty'] += $quantities[$item->id];
            }

            return array_values(array_filter($products, fn (array $product): bool => $product['qty'] > 0));

        });
    }

    /** @return array<int, array<string, int|string>> */
    public function historicalStock(string $date, ?int $storageId = null): array
    {
        return DB::transaction(function () use ($date, $storageId): array {
            $at = CarbonImmutable::parse($date)->endOfDay();
            $items = $this->itemsAt($at, $storageId);
            $quantities = $this->availableQuantities($items, $at);
            $rows = [];

            foreach ($items as $item) {
                $storage = $item->batch->storage;
                $key = $storage->id.':'.$item->product_id;

                if (! isset($rows[$key])) {
                    $rows[$key] = [
                        'storage_id' => $storage->id,
                        'storage_name' => $storage->name,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'qty' => 0,
                    ];
                }

                $rows[$key]['qty'] += $quantities[$item->id];
            }

            return array_values($rows);

        });
    }

    /** @return Collection<int, BatchItem> */
    private function itemsAt(CarbonImmutable $at, ?int $storageId): Collection
    {
        return BatchItem::query()
            ->with(['batch.storage', 'product.category'])
            ->whereHas('batch', function (Builder $query) use ($at, $storageId): void {
                $query->where('purchased_at', '<=', $at);

                if ($storageId !== null) {
                    $query->where('storage_id', $storageId);
                }
            })
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();
    }
}
