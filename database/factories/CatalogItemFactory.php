<?php

namespace Database\Factories;

use App\Enums\CatalogStatus;
use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    /**
     * @return array<string,mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'item_code' => fake()->unique()->bothify('SKU-####'),
            'rate_type' => 'per_piece',
            'default_rate' => fake()->randomFloat(2, 100, 5000),
            'stock_tracked' => false,
            'stock_quantity' => 0,
            'reorder_level' => 0,
            'status' => CatalogStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CatalogStatus::Draft,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CatalogStatus::Inactive,
        ]);
    }
}
