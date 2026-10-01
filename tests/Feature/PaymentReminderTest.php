<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Mail\PaymentReminderMail;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Services\PaymentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function unpaidInvoice(array $customerAttributes = []): Invoice
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user, $customerAttributes);
        BusinessSetting::forTenant($user->tenant_id)->update([
            'sms_payment_reminders' => true,
            'email_payment_reminders' => true,
        ]);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Work',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 5000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::with('customer')->firstOrFail();
    }

    public function test_staff_can_send_a_reminder_and_both_channels_are_logged(): void
    {
        Mail::fake();

        $invoice = $this->unpaidInvoice();
        $user = $invoice->tenant->users()->firstOrFail();

        $this->actingAs($user)
            ->post(route('invoices.remind', $invoice))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Mail::assertSent(PaymentReminderMail::class);

        $this->assertSame(2, MessageLog::withoutGlobalScopes()->count());
        $this->assertSame(
            ['sms', 'email'],
            MessageLog::withoutGlobalScopes()->orderBy('id')->pluck('channel')->all(),
        );

        $invoice->refresh();
        $this->assertNotNull($invoice->last_reminder_sent_at);
    }

    public function test_a_staff_reminder_bypasses_the_three_day_throttle(): void
    {
        Mail::fake();

        $invoice = $this->unpaidInvoice();
        $invoice->forceFill(['last_reminder_sent_at' => now()->subHour()])->saveQuietly();

        $this->actingAs($invoice->tenant->users()->firstOrFail())
            ->post(route('invoices.remind', $invoice))
            ->assertRedirect();

        Mail::assertSent(PaymentReminderMail::class);
    }

    public function test_a_paid_invoice_cannot_be_reminded(): void
    {
        Mail::fake();

        $invoice = $this->unpaidInvoice();
        $user = $invoice->tenant->users()->firstOrFail();

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
            'amount' => 5000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('invoices.remind', $invoice))
            ->assertSessionHasErrors('reminder');

        Mail::assertNothingSent();
    }

    public function test_a_customer_without_contact_details_is_reported_back(): void
    {
        Mail::fake();

        $invoice = $this->unpaidInvoice();
        $invoice->customer->update(['mobile_number' => '', 'email' => null]);

        $this->actingAs($invoice->tenant->users()->firstOrFail())
            ->post(route('invoices.remind', $invoice))
            ->assertSessionHasErrors('reminder');

        Mail::assertNothingSent();
    }

    public function test_each_channel_is_gated_by_its_own_tenant_toggle(): void
    {
        Mail::fake();

        $invoice = $this->unpaidInvoice();
        BusinessSetting::forTenant($invoice->tenant_id)->update([
            'sms_payment_reminders' => false,
            'email_payment_reminders' => true,
        ]);

        $sent = app(PaymentReminderService::class)
            ->sendForInvoice($invoice->refresh()->load('customer'), force: true);

        $this->assertArrayNotHasKey('sms', $sent);
        $this->assertSame('sent', $sent['email'] ?? null);
        Mail::assertSent(PaymentReminderMail::class);

        // And the mirror image: SMS on, email off.
        Mail::fake();
        $invoice->forceFill(['last_reminder_sent_at' => null])->saveQuietly();
        BusinessSetting::forTenant($invoice->tenant_id)->update([
            'sms_payment_reminders' => true,
            'email_payment_reminders' => false,
        ]);

        $sent = app(PaymentReminderService::class)
            ->sendForInvoice($invoice->refresh()->load('customer'), force: true);

        $this->assertArrayNotHasKey('email', $sent);
        // The log driver reports 'logged'; real gateways report 'sent'.
        $this->assertSame('logged', $sent['sms'] ?? null);
        Mail::assertNothingSent();
    }

    public function test_both_toggles_off_sends_nothing_even_for_a_staff_nudge(): void
    {
        Mail::fake();

        $invoice = $this->unpaidInvoice();
        BusinessSetting::forTenant($invoice->tenant_id)->update([
            'sms_payment_reminders' => false,
            'email_payment_reminders' => false,
        ]);

        $sent = app(PaymentReminderService::class)
            ->sendForInvoice($invoice->refresh()->load('customer'), force: true);

        $this->assertSame([], $sent);
        Mail::assertNothingSent();
    }

    public function test_the_nightly_command_skips_recently_reminded_invoices(): void
    {
        Mail::fake();

        $invoice = $this->unpaidInvoice();
        $invoice->forceFill(['last_reminder_sent_at' => now()->subDay()])->saveQuietly();

        $sent = app(PaymentReminderService::class)
            ->sendForInvoice($invoice->refresh()->load('customer'));

        $this->assertSame([], $sent);
        Mail::assertNothingSent();
    }
}
