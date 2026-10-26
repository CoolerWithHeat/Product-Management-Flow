<?php

namespace App\Http\Resources;

use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Batch */
class BatchResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'storage_id' => $this->storage_id,
            'purchased_at' => $this->purchased_at->toISOString(),
            'items' => BatchItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
