<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\QuotationActivity;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\QuotationActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A shared quotation used to be a black hole: the customer could open it, and
 * later accept or decline, and the shop was never told. These cover the alert
 * that closes that gap, plus the two defects that hid the activity behind it.
 */
class QuotationActivityAlertTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    /** The admin who owns the quotation created by quotation(). */
    protected function user(): User
    {
        return $this->staff ??= $this->adminFor();
    }

    protected function quotation(): Invoice
    {
        $this->staff = $this->adminFor(Tenant::factory()->forIndustry('tiles_marble')->create());
        $customer = $this->customerFor($this->staff);

        $this->actingAs($this->staff)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Tile supply',
                'quantity' => 100,
                'rate_type' => 'per_piece',
                'rate' => 500,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::firstOrFail();
    }

    protected function salesInvoice(): Invoice
    {
        $this->staff = $this->adminFor(Tenant::factory()->forIndustry('tiles_marble')->create());
        $customer = $this->customerFor($this->staff);

        $this->actingAs($this->staff)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Work',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::firstOrFail();
    }

    protected function shareLink(Invoice $invoice): InvoiceShareLink
    {
        $link = new InvoiceShareLink([
            'invoice_id' => $invoice->id,
            'is_active' => true,
        ]);
        $link->token = InvoiceShareLink::generateToken();
        $link->save();

        return $link;
    }

    public function test_the_first_public_view_alerts_the_shop(): void
    {
        Notification::fake();

        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        $this->get(route('invoices.public.show', $link->token))->assertOk();

        Notification::assertSentTo(
            $this->staff,
            QuotationActivityNotification::class,
            fn (QuotationActivityNotification $notification): bool => $notification->activity === QuotationActivity::Viewed
                && $notification->invoiceId === $quotation->id,
        );
    }

    public function test_a_refresh_does_not_alert_the_shop_twice(): void
    {
        Notification::fake();

        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        $this->get(route('invoices.public.show', $link->token))->assertOk();
        $this->get(route('invoices.public.show', $link->token))->assertOk();

        Notification::assertSentToTimes($this->staff, QuotationActivityNotification::class, 1);
    }

    public function test_accepting_a_shared_quotation_alerts_the_shop(): void
    {
        Notification::fake();

        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_customer_decisions' => true,
        ]);

        $this->post(route('invoices.public.decide', $link->token), [
            'decision' => QuotationStatus::Accepted->value,
            'name' => 'Ravi',
        ])->assertRedirect();

        Notification::assertSentTo(
            $this->staff,
            QuotationActivityNotification::class,
            fn (QuotationActivityNotification $notification): bool => $notification->activity === QuotationActivity::Accepted
                && $notification->detail === 'Accepted by Ravi',
        );
    }

    public function test_declining_carries_the_customers_reason(): void
    {
        Notification::fake();

        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_customer_decisions' => true,
        ]);

        $this->post(route('invoices.public.decide', $link->token), [
            'decision' => QuotationStatus::Rejected->value,
            'response' => 'Found it cheaper elsewhere',
        ])->assertRedirect();

        Notification::assertSentTo(
            $this->staff,
            QuotationActivityNotification::class,
            fn (QuotationActivityNotification $notification): bool => $notification->activity === QuotationActivity::Declined
                && $notification->detail === 'Found it cheaper elsewhere',
        );
    }

    public function test_the_shop_can_turn_its_own_alerts_off(): void
    {
        Notification::fake();

        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_alerts_owner' => false,
        ]);

        $this->get(route('invoices.public.show', $link->token))->assertOk();

        Notification::assertNothingSent();
    }

    public function test_read_only_viewers_are_not_alerted(): void
    {
        Notification::fake();

        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        $viewer = User::factory()->create([
            'tenant_id' => $quotation->tenant_id,
            'role' => UserRole::Viewer,
            'is_active' => true,
        ]);

        $this->get(route('invoices.public.show', $link->token))->assertOk();

        Notification::assertSentTo($this->staff, QuotationActivityNotification::class);
        Notification::assertNotSentTo($viewer, QuotationActivityNotification::class);
    }

    public function test_another_tenants_staff_is_never_alerted(): void
    {
        Notification::fake();

        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);
        $outsider = $this->adminFor();

        $this->get(route('invoices.public.show', $link->token))->assertOk();

        Notification::assertNotSentTo($outsider, QuotationActivityNotification::class);
    }

    public function test_viewing_a_sales_invoice_does_not_alert(): void
    {
        Notification::fake();

        $link = $this->shareLink($this->salesInvoice());

        $this->get(route('invoices.public.show', $link->token))->assertOk();

        Notification::assertNothingSent();
    }

    /**
     * The updates feed read `$event->properties` — an attribute that does not
     * exist on InvoiceEvent — so every row lost its note and rendered the
     * generic fallback. The column is `meta`.
     */
    public function test_the_updates_feed_shows_the_note_that_accompanied_a_change(): void
    {
        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_show_updates' => true,
        ]);

        $this->actingAs($this->user())->post(route('invoices.quotation-status', $quotation), [
            'status' => QuotationStatus::Sent->value,
            'note' => 'Sent on WhatsApp',
        ])->assertRedirect();

        $this->get(route('invoices.public.show', $link->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invoices/public')
                ->where('quotation.updates', fn ($updates): bool => collect($updates)->contains(
                    fn (array $update): bool => $update['detail'] === 'Sent on WhatsApp'
                        && $update['label'] === 'Quotation marked as sent',
                ))
            );
    }

    /**
     * The sweep ran with no tenant context, so TenantScope was a no-op and every
     * expiry event was written with tenant_id = NULL — invisible to the feed it
     * was supposed to populate.
     */
    public function test_the_expiry_sweep_stamps_events_with_the_tenant(): void
    {
        $quotation = $this->quotation();

        $quotation->update([
            'quotation_valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('quotations:expire')->assertSuccessful();

        $this->assertSame(QuotationStatus::Expired, $quotation->refresh()->quotation_status);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $quotation->id,
            'tenant_id' => $quotation->tenant_id,
        ]);
    }
}
