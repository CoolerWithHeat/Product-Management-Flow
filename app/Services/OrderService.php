<?php

namespace App\Services;

use App\Models\BatchItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SaleRefund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private StockService $stock) {}

    /** @param array{client_id: int, products: array<int, array{id: int, qty: int}>} $data */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $products = Product::query()
                ->whereKey(array_column($data['products'], 'id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $items = BatchItem::query()
                ->whereIn('product_id', $products->modelKeys())
                ->whereHas('batch', fn (Builder $query) => $query->where('purchased_at', '<=', now()))
                ->orderBy('id')->lockForUpdate()->get();

            $available = $this->stock->availableQuantities($items);
            $fifoItems = $items
                ->sortBy([['batch.purchased_at', 'asc'], ['batch_id', 'asc'], ['id', 'asc']])
                ->groupBy('product_id');

            $order = Order::create(['client_id' => $data['client_id'], 'ordered_at' => now()]);

            foreach ($data['products'] as $index => $line) {
                $product = $products->get($line['id']);
                $productItems = $fifoItems->get($line['id'], new Collection);
                $total = $productItems->sum(fn (BatchItem $item): int => $available[$item->id]);

                if (! $product || $line['qty'] > $total) {
                    throw ValidationException::withMessages([
                        "products.{$index}.qty" => "Order quantity exceeds available stock ({$total}).",
                    ]);
                }

                $needed = $line['qty'];

                foreach ($productItems as $item) {
                    $allocated = min($needed, $available[$item->id]);

                    if ($allocated <= 0) {
                        continue;
                    }

                    $order->items()->create([
                        'batch_item_id' => $item->id,
                        'quantity' => $allocated,
                        'unit_price' => $product->price,
                    ]);
                    $needed -= $allocated;

                    if ($needed === 0) {
                        break;
                    }
                }
            }

            return $order->load('items.batchItem');
        }, 3);
    }

    /**
     * @param  array{full?: bool, products?: array<int, array{id: int, qty: int}>}  $data
     * @return Collection<int, SaleRefund>
     */
    public function refund(Order $order, array $data): Collection
    {
        return DB::transaction(function () use ($order, $data): Collection {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $items = $order->items()->orderBy('id')->lockForUpdate()->get();

            $batchItems = BatchItem::query()
                ->whereKey($items->pluck('batch_item_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $refunded = SaleRefund::query()
                ->whereIn('order_item_id', $items->modelKeys())
                ->selectRaw('order_item_id, SUM(quantity) AS total')
                ->groupBy('order_item_id')->pluck('total', 'order_item_id');

            $remaining = [];
            foreach ($items as $item) {
                $remaining[$item->id] = $item->quantity - (int) ($refunded[$item->id] ?? 0);
            }

            $itemsByProduct = $items->groupBy(
                fn (OrderItem $item): int => $batchItems[$item->batch_item_id]->product_id,
            );

            if ($data['full'] ?? false) {
                $requested = [];

                foreach ($itemsByProduct as $productId => $productItems) {
                    $quantity = $productItems->sum(fn (OrderItem $item): int => $remaining[$item->id]);

                    if ($quantity > 0) {
                        $requested[] = ['id' => $productId, 'qty' => $quantity];
                    }
                }

                if ($requested === []) {
                    throw ValidationException::withMessages(['order' => 'This order has no remaining quantity to refund.']);
                }
            } else {
                $requested = $data['products'];
            }

            $refunds = new Collection;
            $refundedAt = now();

            foreach ($requested as $index => $line) {
                $productItems = $itemsByProduct->get($line['id']);

                if (! $productItems) {
                    throw ValidationException::withMessages([
                        "products.{$index}.id" => 'This product is not part of the selected order.',
                    ]);
                }

                $total = $productItems->sum(fn (OrderItem $item): int => $remaining[$item->id]);

                if ($line['qty'] > $total) {
                    throw ValidationException::withMessages([
                        "products.{$index}.qty" => "Refund quantity exceeds the remaining sold quantity ({$total}).",
                    ]);
                }

                $needed = $line['qty'];

                foreach ($productItems as $item) {
                    $quantity = min($needed, $remaining[$item->id]);

                    if ($quantity <= 0) {
                        continue;
                    }

                    $refunds->push($item->saleRefunds()->create([
                        'quantity' => $quantity,
                        'refunded_at' => $refundedAt,
                    ]));
                    $needed -= $quantity;

                    if ($needed === 0) {
                        break;
                    }
                }
            }

            return $refunds;
        }, 3);
    }
}
