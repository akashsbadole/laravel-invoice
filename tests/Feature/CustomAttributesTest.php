<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Attributes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Customers and invoices both carry a free-form `attributes` map, fed by the
 * same repeatable editor the catalog uses. The editor posts indexed
 * [key, value] rows while a CSV import supplies a plain map, so both shapes
 * have to survive the trip to a stored map and back out to the page.
 */
class CustomAttributesTest extends TestCase
{
    use RefreshDatabase;

    protected function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'document_type' => 'general_invoice',
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Item',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 500,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ], $overrides);
    }

    public function test_customer_attributes_are_stored_as_a_map_from_editor_rows(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('customers.store'), [
                'full_name' => 'Asha Verma',
                'mobile_number' => '9800000001',
                'customer_type' => 'individual',
                // The repeatable editor's indexed payload.
                'attributes' => [
                    ['key' => 'Referral', 'value' => 'Walk-in'],
                    ['key' => 'Segment', 'value' => 'VIP'],
                ],
            ])
            ->assertRedirect();

        $customer = Customer::where('full_name', 'Asha Verma')->sole();

        $this->assertSame([
            'Referral' => 'Walk-in',
            'Segment' => 'VIP',
        ], $customer->attributes);
    }

    public function test_customer_attributes_accept_a_plain_map(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('customers.store'), [
                'full_name' => 'Rohit Shah',
                'mobile_number' => '9800000002',
                'customer_type' => 'individual',
                'attributes' => ['GSTIN type' => 'Regular'],
            ])
            ->assertRedirect();

        $customer = Customer::where('full_name', 'Rohit Shah')->sole();

        $this->assertSame(['GSTIN type' => 'Regular'], $customer->attributes);
    }

    public function test_blank_attribute_rows_are_dropped_rather_than_stored_half_empty(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('customers.store'), [
                'full_name' => 'Neha Gupta',
                'mobile_number' => '9800000003',
                'customer_type' => 'individual',
                'attributes' => [
                    // A row the user started but never finished.
                    ['key' => 'Notes', 'value' => ''],
                    ['key' => '', 'value' => 'orphan value'],
                    ['key' => 'City', 'value' => 'Pune'],
                ],
            ])
            ->assertRedirect();

        $customer = Customer::where('full_name', 'Neha Gupta')->sole();

        $this->assertSame(['City' => 'Pune'], $customer->attributes);
    }

    public function test_customer_attributes_are_replaced_on_update(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user, ['attributes' => ['Old' => 'value']]);

        $this->actingAs($user)
            ->put(route('customers.update', $customer), [
                'full_name' => $customer->full_name,
                'mobile_number' => $customer->mobile_number,
                'customer_type' => 'individual',
                'attributes' => [['key' => 'New', 'value' => 'value']],
            ])
            ->assertRedirect();

        $this->assertSame(['New' => 'value'], $customer->fresh()->attributes);
    }

    public function test_customer_attributes_are_rejected_when_not_an_array(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('customers.store'), [
                'full_name' => 'Bad Input',
                'mobile_number' => '9800000004',
                'customer_type' => 'individual',
                'attributes' => 'not-an-array',
            ])
            ->assertSessionHasErrors('attributes');
    }

    public function test_invoice_attributes_are_stored_and_rendered_on_the_page(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload([
                'customer_id' => $customer->id,
                'attributes' => [
                    ['key' => 'Site reference', 'value' => 'MG Road 42'],
                    ['key' => 'Job number', 'value' => 'JOB-991'],
                ],
            ]))
            ->assertRedirect();

        $invoice = Invoice::where('customer_id', $customer->id)->sole();

        $this->assertSame([
            'Site reference' => 'MG Road 42',
            'Job number' => 'JOB-991',
        ], $invoice->attributes);

        // Inertia hands the page JSON props, so assert on the prop rather
        // than on rendered HTML.
        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('invoices/show')
                ->where('invoice.attributes', [
                    'Site reference' => 'MG Road 42',
                    'Job number' => 'JOB-991',
                ])
            );
    }

    public function test_invoice_attributes_are_cleared_when_the_editor_is_emptied(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload([
                'customer_id' => $customer->id,
                'attributes' => [['key' => 'Site', 'value' => 'A1']],
            ]))
            ->assertRedirect();

        $invoice = Invoice::where('customer_id', $customer->id)->sole();

        $this->assertSame(['Site' => 'A1'], $invoice->attributes);

        $this->actingAs($user)
            ->put(route('invoices.update', $invoice), $this->invoicePayload([
                'customer_id' => $customer->id,
                // The editor always keeps one blank row on screen.
                'attributes' => [['key' => '', 'value' => '']],
            ]))
            ->assertRedirect();

        $this->assertSame([], $invoice->fresh()->attributes);
    }

    public function test_attribute_keys_and_values_are_length_capped(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), $this->invoicePayload([
                'customer_id' => $customer->id,
                'attributes' => [
                    ['key' => str_repeat('k', 256), 'value' => 'v'],
                ],
            ]))
            ->assertSessionHasErrors('attributes.0.key');
    }

    /**
     * The helper accepts either shape, so the CSV path and the editor path
     * cannot drift apart.
     */
    public function test_clean_normalises_both_accepted_shapes(): void
    {
        $this->assertSame(
            ['thread' => '2x40', 'brand' => 'Acme'],
            Attributes::clean([
                ['key' => ' thread ', 'value' => ' 2x40 '],
                ['key' => 'brand', 'value' => 'Acme'],
            ])
        );

        $this->assertSame(
            ['thread' => '2x40'],
            Attributes::clean(['thread' => '2x40'])
        );
    }
}
