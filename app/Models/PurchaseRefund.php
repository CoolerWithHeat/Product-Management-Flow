<?php

namespace App\Models;

use Database\Factories\PurchaseRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated quantity returned to the provider at the original batch item's unit cost.
 */
#[Fillable(['batch_item_id', 'quantity', 'refunded_at'])]
class PurchaseRefund extends Model
{
    /** @use HasFactory<PurchaseRefundFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'refunded_at' => 'immutable_datetime',
        ];
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(BatchItem::class);
    }
}
