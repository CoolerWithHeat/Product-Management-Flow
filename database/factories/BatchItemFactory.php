<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\BatchItem;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BatchItem> */
class BatchItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'batch_id' => Batch::factory(),
            'product_id' => function (array $attributes): int {
                $batch = Batch::findOrFail($attributes['batch_id']);

                return Product::factory()
                    ->for(Category::factory()->for($batch->provider))
                    ->create()->id;
            },
            'quantity' => 100,
            'unit_cost' => '10.00',
        ];
    }
}
