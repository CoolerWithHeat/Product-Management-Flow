<?php

namespace App\Models;

use Database\Factories\StorageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Storage extends Model
{
    /** @use HasFactory<StorageFactory> */
    use HasFactory;

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
