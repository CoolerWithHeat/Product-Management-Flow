<?php

namespace App\Services;

use App\Models\Batch;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ProfitService
{
    /**
     * Profit is realized margin: net sales revenue minus the cost of units still sold.
     * Supplier returns reduce purchase expenditure; unsold units remain inventory.
     *
     * @return array<int, array<string, int|string>>
     */
    public function batches(): array
    {
        return DB::transaction(function (): array {
            $at = now();
            $batches = Batch::query()
                ->where('purchased_at', '<=', $at)
                ->with([
                    'items.purchaseRefunds' => fn (HasMany $query) => $query->where('refunded_at', '<=', $at),
                    'items.orderItems' => fn (HasMany $query) => $query->whereHas('order', fn (Builder $orders) => $orders->where('ordered_at', '<=', $at)),
                    'items.orderItems.saleRefunds' => fn (HasMany $query) => $query->where('refunded_at', '<=', $at),
                ])
                ->orderBy('id')->get();

            $rows = [];

            foreach ($batches as $batch) {
                $purchaseCost = BigDecimal::of('0.00');
                $supplierRefunds = BigDecimal::of('0.00');
                $salesRevenue = BigDecimal::of('0.00');
                $clientRefunds = BigDecimal::of('0.00');
                $costOfGoodsSold = BigDecimal::of('0.00');

                foreach ($batch->items as $item) {
                    $unitCost = BigDecimal::of($item->unit_cost);
                    $purchaseCost = $purchaseCost->plus($unitCost->multipliedBy($item->quantity));
                    $supplierRefunds = $supplierRefunds->plus(
                        $unitCost->multipliedBy((int) $item->purchaseRefunds->sum('quantity')),
                    );

                    foreach ($item->orderItems as $sale) {
                        $unitPrice = BigDecimal::of($sale->unit_price);
                        $returned = (int) $sale->saleRefunds->sum('quantity');
                        $salesRevenue = $salesRevenue->plus($unitPrice->multipliedBy($sale->quantity));
                        $clientRefunds = $clientRefunds->plus($unitPrice->multipliedBy($returned));
                        $costOfGoodsSold = $costOfGoodsSold->plus($unitCost->multipliedBy($sale->quantity - $returned));
                    }
                }

                $netRevenue = $salesRevenue->minus($clientRefunds);
                $rows[] = [
                    'batch_id' => $batch->id,
                    'provider_id' => $batch->provider_id,
                    'storage_id' => $batch->storage_id,
                    'purchase_cost' => (string) $purchaseCost->toScale(2),
                    'supplier_refunds' => (string) $supplierRefunds->toScale(2),
                    'net_purchase_cost' => (string) $purchaseCost->minus($supplierRefunds)->toScale(2),
                    'sales_revenue' => (string) $salesRevenue->toScale(2),
                    'client_refunds' => (string) $clientRefunds->toScale(2),
                    'net_revenue' => (string) $netRevenue->toScale(2),
                    'cost_of_goods_sold' => (string) $costOfGoodsSold->toScale(2),
                    'profit' => (string) $netRevenue->minus($costOfGoodsSold)->toScale(2),
                ];
            }

            return $rows;

        });
    }
}
