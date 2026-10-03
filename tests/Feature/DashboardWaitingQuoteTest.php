<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardWaitingQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function createQuotation(User $user, Customer $customer, string $date, string $status): Invoice
    {
        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => $date,
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Necklace',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 7150,
                'net_weight' => 15.2,
                'metal_type' => 'Gold',
                'purity' => '22K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)
            ->latest('id')
            ->firstOrFail();

        if ($status !== 'draft') {
            $this->actingAs($user)->post(route('invoices.quotation-status', $quotation), ['status' => $status])->assertRedirect();
        }

        return $quotation->refresh();
    }

    public function test_sent_quotations_older_than_two_days_appear_in_waiting_on_customer(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['quotation_customer_decisions' => true]);
        });

        $quotation = $this->createQuotation($user, $customer, now()->subDays(3)->toDateString(), 'sent');

        $this->actingAs($user)->post(route('invoices.share-links.store', $quotation))->assertRedirect();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('waitingOnCustomer', 1)
                ->where('waitingOnCustomer.0.invoice_number', $quotation->invoice_number)
                ->where('waitingOnCustomer.0.customer', $customer->full_name)
                ->where('waitingOnCustomer.0.viewed_at', null)
            );
    }

    public function test_recently_sent_and_draft_quotations_are_not_on_the_waiting_list(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->createQuotation($user, $customer, now()->subDay()->toDateString(), 'sent');
        $this->createQuotation($user, $customer, now()->subDays(4)->toDateString(), 'draft');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('waitingOnCustomer', 0));
    }

    public function test_accepted_quotations_are_not_on_the_waiting_list(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $quotation = $this->createQuotation($user, $customer, now()->subDays(4)->toDateString(), 'accepted');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('waitingOnCustomer', 0)
                ->has('acceptedQuotationsToConvert', 1)
                ->where('acceptedQuotationsToConvert.0.id', $quotation->id)
            );
    }

    public function test_a_viewed_waiting_quote_shows_the_viewed_badge_state(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $quotation = $this->createQuotation($user, $customer, now()->subDays(5)->toDateString(), 'sent');
        $this->actingAs($user)->post(route('invoices.share-links.store', $quotation))->assertRedirect();

        $link = InvoiceShareLink::where('invoice_id', $quotation->id)->firstOrFail();
        // viewed_at/downloaded_at are tracked, not user-editable, so they
        // are deliberately fillable-light; use forceFill like the model's
        // own markViewed() does.
        $link->forceFill(['viewed_at' => now()->subDay()])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('waitingOnCustomer.0.viewed_at', fn ($value) => $value !== null)
            );
    }
}
