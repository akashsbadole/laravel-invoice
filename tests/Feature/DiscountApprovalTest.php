<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $customerId, float $discount): array
    {
        return [
            'customer_id' => $customerId,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => $discount,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Necklace',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 12000,
                'net_weight' => 10,
                'metal_type' => 'Gold',
                'purity' => '22K',
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ];
    }

    private function createQuotation(User $user, int $customerId, float $discount): Invoice
    {
        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customerId, $discount))
            ->assertRedirect();

        return Invoice::where('document_type', DocumentType::Quotation->value)->latest('id')->firstOrFail();
    }

    public function test_a_quotation_with_an_over_limit_discount_cannot_be_converted_until_approved(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['discount_approval_threshold' => 10]);
        });

        // ₹4000 off ₹12000 → 33.3% share, over the 10% limit.
        $quotation = $this->createQuotation($user, $customer->id, 4000);

        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::GeneralInvoice->value,
        ])->assertStatus(422);

        $this->assertNull($quotation->converted_to_id);
    }

    public function test_an_admin_can_approve_and_then_convert(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['discount_approval_threshold' => 10]);
        });

        $quotation = $this->createQuotation($user, $customer->id, 4000);

        $this->actingAs($user)->post(route('invoices.approve-discount', $quotation))
            ->assertRedirect();

        $quotation->refresh();

        $this->assertSame($user->id, $quotation->discount_approved_by);
        $this->assertSame((float) $quotation->discount, (float) $quotation->discount_approved_discount);

        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::GeneralInvoice->value,
        ])->assertRedirect();

        $invoice = Invoice::where('document_type', DocumentType::GeneralInvoice->value)->firstOrFail();

        // The approval trail travels with the invoice.
        $this->assertSame($user->id, $invoice->discount_approved_by);
        $this->assertSame((float) $quotation->discount, (float) $invoice->discount_approved_discount);
    }

    public function test_a_bigger_discount_later_needs_a_fresh_approval(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['discount_approval_threshold' => 10]);
        });

        $quotation = $this->createQuotation($user, $customer->id, 2000);

        $this->actingAs($user)->post(route('invoices.approve-discount', $quotation))->assertRedirect();

        // Staff then quietly bumps the discount from ₹2000 to ₹5000: the old
        // approval no longer covers this, so conversion is blocked again.
        $this->actingAs($user)->put(route('invoices.update', $quotation), $this->payload($customer->id, 5000))
            ->assertRedirect();

        $this->actingAs($user)->post(route('invoices.convert', $quotation->refresh()), [
            'document_type' => DocumentType::GeneralInvoice->value,
        ])->assertStatus(422);
    }

    public function test_discount_approval_is_disabled_without_a_threshold(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $quotation = $this->createQuotation($user, $customer->id, 4000);

        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::GeneralInvoice->value,
        ])->assertRedirect();
    }

    public function test_a_discount_under_the_limit_converts_without_approval(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['discount_approval_threshold' => 10]);
        });

        // ₹1000 off ₹12000 → ~7.7% share, under the 10% limit.
        $quotation = $this->createQuotation($user, $customer->id, 1000);

        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::GeneralInvoice->value,
        ])->assertRedirect();
    }

    public function test_approval_requires_manage_settings_permission(): void
    {
        $admin = $this->adminFor();
        $customer = $this->customerFor($admin);

        $this->inTenant($admin, function () use ($admin) {
            BusinessSetting::forTenant($admin->tenant_id)->update(['discount_approval_threshold' => 10]);
        });

        $quotation = $this->createQuotation($admin, $customer->id, 4000);

        $viewer = User::factory()->create([
            'tenant_id' => $admin->tenant_id,
            'role' => 'viewer',
        ]);

        $this->actingAs($viewer)->post(route('invoices.approve-discount', $quotation))
            ->assertStatus(403);
    }
}
