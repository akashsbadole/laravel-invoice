<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer tags are stored as a JSON array so they can be filtered exactly.
 *
 * A substring match would be wrong here: filtering for "bridal" must not
 * return a customer tagged "bridal-wear".
 */
class CustomerTagFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_be_filtered_by_an_exact_tag(): void
    {
        $user = $this->adminFor();

        $this->customerFor($user, ['full_name' => 'Asha Verma', 'tags' => ['bridal', 'vip']]);
        $this->customerFor($user, ['full_name' => 'Rohit Shah', 'tags' => ['bridal-wear']]);
        $this->customerFor($user, ['full_name' => 'Neha Gupta']);

        $this->actingAs($user)
            ->get(route('customers.index', ['tag' => 'bridal']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('customers/index')
                ->where('filters.tag', 'bridal')
                ->where('customers.data', fn ($rows) => collect($rows)->pluck('full_name')->all() === ['Asha Verma'])
            );
    }

    public function test_a_compound_tag_is_matched_exactly(): void
    {
        $user = $this->adminFor();

        $this->customerFor($user, ['full_name' => 'Asha Verma', 'tags' => ['bridal', 'vip']]);
        $this->customerFor($user, ['full_name' => 'Rohit Shah', 'tags' => ['bridal-wear']]);

        $this->actingAs($user)
            ->get(route('customers.index', ['tag' => 'bridal-wear']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('customers.data', fn ($rows) => collect($rows)->pluck('full_name')->all() === ['Rohit Shah'])
            );
    }
}
