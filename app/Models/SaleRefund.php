<?php

namespace App\Models;

use Database\Factories\SaleRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated quantity returned by a client at the original sale price, back to its source batch stock.
 */
#[Fillable(['order_item_id', 'quantity', 'refunded_at'])]
class SaleRefund extends Model
{
    /** @use HasFactory<SaleRefundFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
