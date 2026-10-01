<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalog "Add item" dialog pre-selects a rate type, but a select the user
 * never opens is not guaranteed to submit one. Since rate_type is a NOT NULL
 * column and a required rule, an absent value used to make the whole form
 * unsatisfiable with "The rate type field is required" - a confusing message
 * for someone who had filled in the product name.
 */
class CatalogRateTypeDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_name_alone_is_enough_to_create_an_item(): void
    {
        $response = $this->actingAs($this->adminFor())
            ->post(route('catalog.store'), ['name' => 'Gold Ring']);

        $response->assertSessionHasNoErrors();

        $item = CatalogItem::where('name', 'Gold Ring')->sole();

        // Falls back to the industry's first rate type, matching the value the
        // form displays as selected.
        $this->assertSame('per_gram', $item->rate_type->value);
    }

    public function test_an_explicit_rate_type_is_never_overwritten(): void
    {
        $this->actingAs($this->adminFor())
            ->post(route('catalog.store'), [
                'name' => 'Diamond Ring',
                'rate_type' => 'per_piece',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'per_piece',
            CatalogItem::where('name', 'Diamond Ring')->sole()->rate_type->value,
        );
    }

    public function test_an_unsupported_rate_type_is_still_rejected(): void
    {
        // The fallback must not become a way to smuggle an invalid value past
        // the industry's allowed list.
        $this->actingAs($this->adminFor())
            ->post(route('catalog.store'), [
                'name' => 'Sneaky Item',
                'rate_type' => 'per_furlong',
            ])
            ->assertSessionHasErrors('rate_type');

        $this->assertSame(0, CatalogItem::count());
    }

    public function test_the_fallback_matches_the_rate_type_the_form_shows(): void
    {
        $user = $this->adminFor();

        $props = $this->actingAs($user)
            ->get(route('catalog.index'))
            ->viewData('page')['props'];

        $shown = $props['industry']['rate_types'][0];

        $this->actingAs($user)->post(route('catalog.store'), ['name' => 'Aligned']);

        // The value the dialog pre-selects and the value persisted when the
        // select is untouched have to agree, or the user is silently saved
        // with different pricing than the form appeared to offer.
        $this->assertSame($shown, CatalogItem::where('name', 'Aligned')->sole()->rate_type->value);
    }

    public function test_updating_an_item_without_a_rate_type_keeps_the_existing_one(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Bangle',
            'rate_type' => 'per_piece',
        ]);

        $item = CatalogItem::where('name', 'Bangle')->sole();

        // The edit dialog omits the select when the user does not touch it.
        $this->actingAs($user)
            ->put(route('catalog.update', $item), ['name' => 'Bangle'])
            ->assertSessionHasNoErrors();

        $this->assertSame('per_piece', $item->fresh()->rate_type->value);
    }
}
