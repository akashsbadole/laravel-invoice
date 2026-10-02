<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner pain: "half my sales are to the same handful of dealers, and I
 * re-type the same wholesale discount on every single invoice. Tell me the
 * customer once and price every blank line for me — but never overrule a
 * discount I typed myself, and never touch the old-gold exchange line."
 */
class CustomerGroupTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->adminFor();
    }

    protected function group(array $attributes = []): CustomerGroup
    {
        // tenant_id is stamped by BelongsToTenant, which only fires inside a
        // tenant context — and it is deliberately not mass-assignable.
        return $this->inTenant($this->owner, fn (): CustomerGroup => CustomerGroup::create(array_merge([
            'name' => 'Wholesale',
            'discount_percent' => 5,
        ], $attributes)));
    }

    protected function customerInGroup(array $groupAttributes = [], array $customerAttributes = []): Customer
    {
        $group = $this->group($groupAttributes);

        return $this->customerFor($this->owner, array_merge([
            'customer_group_id' => $group->id,
        ], $customerAttributes));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function invoicePayload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Ring',
                'line_type' => 'sale',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ], $overrides);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    protected function storeInvoice(Customer $customer, array $overrides = []): Invoice
    {
        $this->actingAs($this->owner)
            ->post(route('invoices.store'), $this->invoicePayload($customer, $overrides))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Invoice::latest('id')->firstOrFail();
    }

    public function test_a_wholesale_customer_is_discounted_without_staff_retyping_it(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer);

        $item = $invoice->items()->firstOrFail();
        $this->assertSame(50.0, (float) $item->discount);
        $this->assertSame(1000.0, (float) $invoice->subtotal);
        $this->assertSame(950.0, (float) $invoice->grand_total);
    }

    public function test_the_group_discount_follows_the_line_value_not_the_unit_rate(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer, [
            'items' => [[
                'item_name' => 'Necklace',
                'line_type' => 'sale',
                'quantity' => 3,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]);

        // 5% of 3 × ₹1000, not 5% of one unit.
        $this->assertSame(150.0, (float) $invoice->items()->firstOrFail()->discount);
        $this->assertSame(2850.0, (float) $invoice->grand_total);
    }

    public function test_a_discount_the_shopkeeper_typed_always_wins(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer, [
            'items' => [[
                'item_name' => 'Ring',
                'line_type' => 'sale',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                // A better deal than the group's 5% — their call, not ours.
                'discount' => 300,
                'charges' => [],
            ]],
        ]);

        $this->assertSame(300.0, (float) $invoice->items()->firstOrFail()->discount);
        $this->assertSame(700.0, (float) $invoice->grand_total);
    }

    public function test_even_a_smaller_typed_discount_beats_the_group_rate(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer, [
            'items' => [[
                'item_name' => 'Ring',
                'line_type' => 'sale',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                'discount' => 10,
                'charges' => [],
            ]],
        ]);

        $this->assertSame(10.0, (float) $invoice->items()->firstOrFail()->discount);
    }

    public function test_the_group_discount_lands_before_gst_so_tax_is_charged_on_the_lower_value(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer, [
            'items' => [[
                'item_name' => 'Ring',
                'line_type' => 'sale',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ]);

        $item = $invoice->items()->firstOrFail();
        // 3% of (1000 − 50), not 3% of 1000.
        $this->assertSame(50.0, (float) $item->discount);
        $this->assertSame(28.5, (float) $item->tax);
        $this->assertSame(978.5, (float) $item->total);
    }

    public function test_the_discount_is_printed_on_the_bill_as_an_item_discount_row(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer);

        $summary = collect($invoice->charges_summary)->keyBy('code');

        $this->assertTrue($summary->has('item_discount'));
        $this->assertSame('Item discounts', $summary->get('item_discount')['label']);
        $this->assertSame(-50.0, (float) $summary->get('item_discount')['amount']);
    }

    public function test_an_exchange_credit_line_is_never_group_discounted(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer, [
            'items' => [[
                'item_name' => 'Old gold exchange',
                'line_type' => 'exchange_credit',
                'quantity' => 1,
                'net_weight' => 10,
                'rate_type' => 'per_gram',
                'rate' => 5500,
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ]);

        $credit = $invoice->items()->firstOrFail();
        // Handing back the customer's own metal is not a supply, so there is
        // nothing to discount and no GST to give up. (Manual pricing takes
        // the typed rate as the line amount, so 10g × 5500 is not implied.)
        $this->assertSame(0.0, (float) $credit->discount);
        $this->assertSame(0.0, (float) $credit->tax);
        $this->assertSame(-5500.0, (float) $credit->base_value);
    }

    public function test_a_customer_with_no_group_is_billed_at_face_value(): void
    {
        $customer = $this->customerFor($this->owner);

        $invoice = $this->storeInvoice($customer);

        $this->assertSame(0.0, (float) $invoice->items()->firstOrFail()->discount);
        $this->assertSame(1000.0, (float) $invoice->grand_total);
    }

    public function test_switching_a_group_off_stops_the_discount_immediately(): void
    {
        $group = $this->group();
        $customer = $this->customerFor($this->owner, ['customer_group_id' => $group->id]);

        $this->storeInvoice($customer);

        $group->update(['is_active' => false]);

        $invoice = $this->storeInvoice($customer);

        $this->assertSame(0.0, (float) $invoice->items()->firstOrFail()->discount);
        $this->assertSame(1000.0, (float) $invoice->grand_total);
    }

    public function test_an_already_issued_invoice_keeps_its_discount_when_the_group_rate_later_changes(): void
    {
        $group = $this->group();
        $customer = $this->customerFor($this->owner, ['customer_group_id' => $group->id]);

        $issued = $this->storeInvoice($customer);

        // The shop re-prices the tier. The bill that already went out does not.
        $group->update(['discount_percent' => 20]);

        $this->assertSame(50.0, (float) $issued->fresh()->items()->firstOrFail()->discount);
        $this->assertSame(950.0, (float) $issued->fresh()->grand_total);
    }

    public function test_a_client_cannot_post_its_own_group_percentage(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer, [
            // Spoofed: the customer's real group is 5%.
            'group_discount_percent' => 90,
        ]);

        $this->assertSame(50.0, (float) $invoice->items()->firstOrFail()->discount);
        $this->assertSame(950.0, (float) $invoice->grand_total);
    }

    public function test_a_quotation_to_a_wholesale_customer_is_priced_the_same_way(): void
    {
        $customer = $this->customerInGroup();

        $invoice = $this->storeInvoice($customer, [
            'document_type' => DocumentType::Quotation->value,
        ]);

        $this->assertSame(50.0, (float) $invoice->items()->firstOrFail()->discount);
        $this->assertSame(950.0, (float) $invoice->grand_total);
    }

    public function test_the_invoice_form_is_told_each_customer_group_rate_so_the_preview_matches(): void
    {
        $grouped = $this->customerInGroup();
        $plain = $this->customerFor($this->owner);

        $response = $this->actingAs($this->owner)->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('invoices/create'));

        $customers = collect($response->viewData('page')['props']['customers'])->keyBy('id');

        $this->assertSame(5.0, $customers[$grouped->id]['group_discount_percent']);
        $this->assertSame(0.0, $customers[$plain->id]['group_discount_percent']);
    }

    public function test_deleting_a_group_frees_its_customers_rather_than_orphaning_them(): void
    {
        $group = $this->group();
        $customer = $this->customerFor($this->owner, ['customer_group_id' => $group->id]);

        $this->actingAs($this->owner)
            ->delete(route('customer-groups.destroy', $group))
            ->assertRedirect();

        $customer->refresh();
        $this->assertNull($customer->customer_group_id);

        // And the next bill is back at face value.
        $invoice = $this->storeInvoice($customer);
        $this->assertSame(0.0, (float) $invoice->items()->firstOrFail()->discount);
    }

    public function test_a_group_is_created_from_the_settings_page(): void
    {
        $this->actingAs($this->owner)
            ->post(route('customer-groups.store'), [
                'name' => 'Silver dealer',
                'discount_percent' => 7.5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $group = CustomerGroup::where('name', 'Silver dealer')->firstOrFail();
        $this->assertSame(7.5, (float) $group->discount_percent);
        $this->assertTrue($group->is_active);
        // First group sorts ahead of nothing; the next one lands after it.
        $this->assertSame(1, $group->sort_order);
    }

    public function test_a_group_rate_and_name_are_edited_and_the_group_can_be_switched_off(): void
    {
        $group = $this->group();

        $this->actingAs($this->owner)
            ->put(route('customer-groups.update', $group), [
                'name' => 'VIP dealers',
                'discount_percent' => 12,
                'is_active' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $group->refresh();
        $this->assertSame('VIP dealers', $group->name);
        $this->assertSame(12.0, (float) $group->discount_percent);
        $this->assertFalse($group->is_active);
        $this->assertSame(0.0, $group->effectiveDiscountPercent());
    }

    public function test_the_settings_page_lists_the_groups_with_their_customer_counts(): void
    {
        $group = $this->group();
        $this->customerFor($this->owner, ['customer_group_id' => $group->id]);
        $this->customerFor($this->owner, ['customer_group_id' => $group->id]);

        $this->actingAs($this->owner)->get(route('customer-groups.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/customer-groups')
                ->where('customerGroups.0.name', 'Wholesale')
                ->where('customerGroups.0.discount_percent', '5.00')
                ->where('customerGroups.0.is_active', true)
                ->where('customerGroups.0.customers_count', 2));
    }

    public function test_two_groups_cannot_share_a_name(): void
    {
        $this->group();

        $this->actingAs($this->owner)
            ->post(route('customer-groups.store'), [
                'name' => 'Wholesale',
                'discount_percent' => 10,
            ])
            ->assertSessionHasErrors('name');

        // Raw table counts: the tenant scope left over from the request would
        // otherwise hide the rows belonging to the other business.
        $this->assertSame(1, DB::table('customer_groups')->count());
    }

    public function test_a_group_rate_has_to_be_a_real_percentage(): void
    {
        $this->actingAs($this->owner)
            ->post(route('customer-groups.store'), [
                'name' => 'Ridiculous',
                'discount_percent' => 140,
            ])
            ->assertSessionHasErrors('discount_percent');

        $this->actingAs($this->owner)
            ->post(route('customer-groups.store'), [
                'name' => 'Freebie',
                'discount_percent' => -5,
            ])
            ->assertSessionHasErrors('discount_percent');

        $this->assertSame(0, DB::table('customer_groups')->count());
    }

    public function test_group_names_are_scoped_to_the_business(): void
    {
        $this->group();

        $otherOwner = $this->adminFor();

        $this->actingAs($otherOwner)
            ->post(route('customer-groups.store'), [
                'name' => 'Wholesale',
                'discount_percent' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, DB::table('customer_groups')->count());
    }

    public function test_a_manager_cannot_reach_customer_groups(): void
    {
        $manager = $this->userWithRole(UserRole::Manager);

        $this->actingAs($manager)->get(route('customer-groups.index'))->assertForbidden();

        $this->actingAs($manager)
            ->post(route('customer-groups.store'), [
                'name' => 'Back door',
                'discount_percent' => 99,
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('customer_groups')->count());
    }

    public function test_a_customer_is_filed_under_a_group_from_their_record(): void
    {
        $group = $this->group();

        $this->actingAs($this->owner)->post(route('customers.store'), [
            'full_name' => 'Ramesh Dealer',
            'mobile_number' => '9998887776',
            'customer_type' => 'business',
            'customer_group_id' => $group->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customer = Customer::where('mobile_number', '9998887776')->firstOrFail();
        $this->assertSame($group->id, $customer->customer_group_id);
        $this->assertSame(5.0, $customer->groupDiscountPercent());
    }

    public function test_a_customer_can_be_moved_between_groups_and_back_to_none(): void
    {
        $wholesale = $this->group();
        $staffTier = $this->group(['name' => 'Staff', 'discount_percent' => 10]);
        $customer = $this->customerFor($this->owner, ['customer_group_id' => $wholesale->id]);

        $payload = [
            'full_name' => $customer->full_name,
            'mobile_number' => $customer->mobile_number,
            'customer_type' => $customer->customer_type,
        ];

        $this->actingAs($this->owner)
            ->put(route('customers.update', $customer), $payload + ['customer_group_id' => $staffTier->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(10.0, $customer->fresh()->groupDiscountPercent());

        $this->actingAs($this->owner)
            ->put(route('customers.update', $customer), $payload + ['customer_group_id' => null])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->customer_group_id);
        $this->assertSame(0.0, $customer->fresh()->groupDiscountPercent());
    }

    public function test_a_customer_cannot_be_filed_under_another_businesss_group(): void
    {
        $rival = $this->adminFor();
        $foreignGroup = $this->inTenant($rival, fn (): CustomerGroup => CustomerGroup::create([
            'name' => 'Rival dealers',
            'discount_percent' => 50,
        ]));

        $this->actingAs($this->owner)->post(route('customers.store'), [
            'full_name' => 'Sneaky',
            'mobile_number' => '9112223334',
            'customer_type' => 'individual',
            'customer_group_id' => $foreignGroup->id,
        ])->assertSessionHasErrors('customer_group_id');

        $this->assertSame(0, DB::table('customers')->count());
    }

    public function test_the_customer_editor_offers_the_groups(): void
    {
        $group = $this->group();

        $this->actingAs($this->owner)->get(route('customers.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('customers/create')
                ->where('customerGroups.0.id', $group->id)
                ->where('customerGroups.0.name', 'Wholesale'));

        $customer = $this->customerFor($this->owner, ['customer_group_id' => $group->id]);

        $this->actingAs($this->owner)->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('customers/edit')
                ->where('customer.customer_group_id', $group->id)
                ->where('customerGroups.0.id', $group->id));
    }

    public function test_the_customer_page_shows_the_tier_they_bill_under(): void
    {
        $customer = $this->customerInGroup();

        $this->actingAs($this->owner)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('customers/show')
                ->where('customer.group.name', 'Wholesale')
                ->where('customer.group.discount_percent', '5.00'));
    }
}
