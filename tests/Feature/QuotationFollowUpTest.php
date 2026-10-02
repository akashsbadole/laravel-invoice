<?php

namespace Tests\Feature;

use App\Enums\ContactChannel;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Mail\QuotationFollowUpMail;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuotationFollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The customer made a nudge automatic: a sent quote that was never opened, or one
 * about to lapse, gets chased without the shopkeeper remembering to.
 */
class QuotationFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function quotation(array $attributes = [], array $customerAttributes = []): Invoice
    {
        $this->staff = $this->adminFor(Tenant::factory()->forIndustry('tiles_marble')->create());
        $customer = $this->customerFor($this->staff, $customerAttributes);

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

        $quotation = Invoice::query()->latest('id')->firstOrFail();

        if ($attributes !== []) {
            $quotation->update($attributes);
        }

        return $quotation->refresh();
    }

    protected function shareLink(Invoice $quotation, array $attributes = []): InvoiceShareLink
    {
        $link = new InvoiceShareLink([
            'invoice_id' => $quotation->id,
            'is_active' => true,
        ]);
        $link->token = InvoiceShareLink::generateToken();
        $link->forceFill($attributes);
        $link->save();

        return $link;
    }

    protected function enableFollowUps(Invoice $quotation, int $days = 2): void
    {
        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_followup_enabled' => true,
            'quotation_followup_days' => $days,
        ]);
    }

    protected function send(Invoice $quotation): array
    {
        return app(QuotationFollowUpService::class)->sendForQuotation($quotation, $this->staff->id);
    }

    public function test_it_nudges_a_sent_quote_that_was_never_opened(): void
    {
        Mail::fake();

        $quotation = $this->quotation(['invoice_date' => now()->subDays(3)->toDateString()]);
        $this->shareLink($quotation);
        $this->enableFollowUps($quotation);

        $sent = $this->send($quotation);

        $this->assertArrayHasKey('sms', $sent);
        Mail::assertSent(QuotationFollowUpMail::class);
        $this->assertDatabaseHas('message_logs', [
            'invoice_id' => $quotation->id,
            'channel' => 'sms',
        ]);
    }

    public function test_it_leaves_an_opened_quote_alone(): void
    {
        Mail::fake();

        $quotation = $this->quotation(['invoice_date' => now()->subDays(3)->toDateString()]);
        $this->shareLink($quotation, ['viewed_at' => now()]);
        $this->enableFollowUps($quotation);

        $this->assertSame([], $this->send($quotation));
        Mail::assertNothingSent();
        $this->assertDatabaseCount('message_logs', 0);
    }

    public function test_it_nudges_a_quote_about_to_expire_even_when_it_was_opened(): void
    {
        Mail::fake();

        $quotation = $this->quotation([
            'quotation_valid_until' => now()->addDay()->toDateString(),
        ]);
        $this->shareLink($quotation, ['viewed_at' => now()->subDay()]);
        $this->enableFollowUps($quotation);

        $this->assertArrayHasKey('sms', $this->send($quotation));
    }

    public function test_it_waits_the_configured_number_of_days(): void
    {
        Mail::fake();

        // Sent today, so there is nothing to chase yet.
        $quotation = $this->quotation();
        $this->shareLink($quotation);
        $this->enableFollowUps($quotation, days: 2);

        $this->assertSame([], $this->send($quotation));
        Mail::assertNothingSent();
    }

    public function test_it_is_throttled_after_a_recent_nudge(): void
    {
        Mail::fake();

        $quotation = $this->quotation([
            'invoice_date' => now()->subDays(5)->toDateString(),
            'last_reminder_sent_at' => now()->subDay(),
        ]);
        $this->shareLink($quotation);
        $this->enableFollowUps($quotation);

        $this->assertSame([], $this->send($quotation));
    }

    public function test_it_stays_silent_when_the_toggle_is_off(): void
    {
        Mail::fake();

        $quotation = $this->quotation(['invoice_date' => now()->subDays(3)->toDateString()]);
        $this->shareLink($quotation);

        $this->assertSame([], $this->send($quotation));
        Mail::assertNothingSent();
    }

    public function test_a_phone_call_customer_is_left_for_a_human(): void
    {
        Mail::fake();

        $quotation = $this->quotation(
            ['invoice_date' => now()->subDays(3)->toDateString()],
            ['preferred_contact_channel' => ContactChannel::Call->value],
        );
        $this->shareLink($quotation);
        $this->enableFollowUps($quotation);

        $this->assertSame([], $this->send($quotation));
        Mail::assertNothingSent();
        // Stamped so the nightly job does not reconsider it every evening.
        $this->assertNotNull($quotation->refresh()->last_reminder_sent_at);
    }

    public function test_an_email_only_customer_is_emailed_not_texted(): void
    {
        Mail::fake();

        $quotation = $this->quotation(
            ['invoice_date' => now()->subDays(3)->toDateString()],
            ['preferred_contact_channel' => ContactChannel::Email->value],
        );
        $this->shareLink($quotation);
        $this->enableFollowUps($quotation);

        $sent = $this->send($quotation);

        $this->assertSame(['email'], array_keys($sent));
        Mail::assertSent(QuotationFollowUpMail::class);
        $this->assertDatabaseMissing('message_logs', [
            'invoice_id' => $quotation->id,
            'channel' => 'sms',
        ]);
    }

    public function test_the_command_chases_eligible_quotations(): void
    {
        Mail::fake();

        $quotation = $this->quotation(['invoice_date' => now()->subDays(4)->toDateString()]);
        $this->shareLink($quotation);
        $this->enableFollowUps($quotation);

        $this->artisan('quotations:follow-up')->assertSuccessful();

        $this->assertDatabaseHas('message_logs', [
            'invoice_id' => $quotation->id,
            'channel' => 'sms',
        ]);
    }

    public function test_the_command_does_nothing_when_the_tenant_has_it_off(): void
    {
        Mail::fake();

        $quotation = $this->quotation(['invoice_date' => now()->subDays(4)->toDateString()]);
        $this->shareLink($quotation);

        $this->artisan('quotations:follow-up')->assertSuccessful();

        $this->assertDatabaseCount('message_logs', 0);
    }

    public function test_the_manual_nudge_endpoint_sends_even_when_chasing_is_off(): void
    {
        Mail::fake();

        $quotation = $this->quotation(['invoice_date' => now()->subDays(3)->toDateString()]);
        $this->shareLink($quotation);

        $this->actingAs($this->staff)
            ->post(route('quotations.nudge', $quotation))
            ->assertRedirect();

        $this->assertDatabaseHas('message_logs', [
            'invoice_id' => $quotation->id,
            'channel' => 'sms',
        ]);
    }

    public function test_a_viewer_cannot_nudge(): void
    {
        $quotation = $this->quotation(['invoice_date' => now()->subDays(3)->toDateString()]);

        $viewer = User::factory()->create([
            'tenant_id' => $quotation->tenant_id,
            'role' => UserRole::Viewer,
            'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->post(route('quotations.nudge', $quotation))
            ->assertForbidden();
    }
}
