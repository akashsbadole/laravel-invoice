<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'mobile_number' => fake()->unique()->numerify('+91#########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'tax_number' => null,
            'state_code' => fake()->randomElement(['07', '19', '23', '27', '29']),
            'birthday' => fake()->optional(0.7)->date(),
            'anniversary' => fake()->optional(0.4)->date(),
            'customer_type' => 'individual',
        ];
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_type' => 'business',
            'tax_number' => fake()->regexify('[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]{3}'),
        ]);
    }
}
