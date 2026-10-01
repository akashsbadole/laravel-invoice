<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Support\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'industry' => Industry::default(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
        ];
    }

    public function forIndustry(string $industry): static
    {
        return $this->state(fn (array $attributes) => [
            'industry' => $industry,
        ]);
    }
}
