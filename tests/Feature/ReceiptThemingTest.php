<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\ReceiptTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A receipt is the document a customer physically keeps, so its branding and
 * the accent colour it prints are both worth proving. The colour check matters
 * because it is interpolated straight into a style attribute.
 */
class ReceiptThemingTest extends TestCase
{
    use RefreshDatabase;

    protected function invoiceFor(User $user): Invoice
    {
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
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
        ])->assertRedirect();

        return Invoice::with(['customer', 'items.charges', 'payments'])->firstOrFail();
    }

    public function test_the_accent_colour_is_applied_to_the_receipt(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        BusinessSetting::forTenant($user->tenant_id)->update([
            'receipt_accent_color' => '#7C3AED',
        ]);

        $html = $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('#7C3AED', $html);
        $this->assertStringContainsString('hr class="accent"', $html);
    }

    public function test_a_hostile_accent_colour_falls_back_to_the_default(): void
    {
        // Bypasses form validation to prove the render path is also safe.
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        BusinessSetting::forTenant($user->tenant_id)->update([
            'receipt_accent_color' => 'red; } body { display:none',
        ]);

        $html = $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(ReceiptTheme::DEFAULT_ACCENT, $html);
        $this->assertStringNotContainsString('display:none', $html);
    }

    public function test_sanitizing_accepts_short_and_uppercase_hex_only(): void
    {
        $this->assertSame('#AABBCC', ReceiptTheme::sanitize('#abc'));
        $this->assertSame('#7C3AED', ReceiptTheme::sanitize('#7c3aed'));
        $this->assertSame(ReceiptTheme::DEFAULT_ACCENT, ReceiptTheme::sanitize('blue'));
        $this->assertSame(ReceiptTheme::DEFAULT_ACCENT, ReceiptTheme::sanitize(null));
        $this->assertSame(ReceiptTheme::DEFAULT_ACCENT, ReceiptTheme::sanitize('#12345'));
        $this->assertSame(
            ReceiptTheme::DEFAULT_ACCENT,
            ReceiptTheme::sanitize('"><script>alert(1)</script>'),
        );
    }

    public function test_the_gstin_can_be_hidden_from_a_receipt(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        BusinessSetting::forTenant($user->tenant_id)->update([
            'tax_number' => '27ABCDE1234F1Z5',
            'receipt_show_gstin' => false,
        ]);
        $invoice = $invoice->fresh();

        $html = $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('27ABCDE1234F1Z5', $html);

        BusinessSetting::forTenant($user->tenant_id)->update(['receipt_show_gstin' => true]);

        $html = $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('27ABCDE1234F1Z5', $html);
    }

    public function test_the_logo_is_printed_only_when_enabled(): void
    {
        Storage::fake('public');
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        $logo = Storage::disk('public')->put('catalog/logo.png', 'not-really-an-image');
        BusinessSetting::forTenant($user->tenant_id)->update([
            'logo_path' => $logo,
            'receipt_show_logo' => false,
        ]);

        $html = $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('class="logo"', $html);

        BusinessSetting::forTenant($user->tenant_id)->update(['receipt_show_logo' => true]);

        $html = $this->actingAs($user)
            ->get(route('invoices.receipt', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="logo"', $html);
        $this->assertStringContainsString($logo, $html);
    }

    public function test_the_payment_receipt_is_themed_too(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
            'amount' => 500,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $payment = Payment::firstOrFail();

        BusinessSetting::forTenant($user->tenant_id)->update([
            'receipt_accent_color' => '#0F172A',
            'receipt_footer' => 'Settled in full',
        ]);

        $html = $this->actingAs($user)
            ->get(route('payments.receipt', $payment))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('PAYMENT RECEIPT', $html);
        $this->assertStringContainsString('Settled in full', $html);
        $this->assertStringContainsString(sprintf('RCPT-%06d', $payment->id), $html);
    }

    public function test_the_receipt_settings_persist_and_can_be_switched_off(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('business.update'), $this->payload([
            'receipt_width' => '58',
            'receipt_accent_color' => '#7C3AED',
            'receipt_show_logo' => true,
            'receipt_show_signature' => true,
            'receipt_show_stamp' => true,
            'receipt_show_gstin' => true,
            'receipt_footer' => 'Thanks for your business',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $settings = BusinessSetting::forTenant($user->tenant_id);
        $this->assertSame('58', $settings->receipt_width);
        $this->assertSame('#7C3AED', $settings->receipt_accent_color);
        $this->assertTrue($settings->receipt_show_signature);
        $this->assertSame('Thanks for your business', $settings->receipt_footer);

        // Unchecked boxes must clear, not be ignored.
        $payload = $this->payload();
        unset($payload['receipt_show_logo'], $payload['receipt_show_signature']);

        $this->actingAs($user)->post(route('business.update'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $settings = BusinessSetting::forTenant($user->tenant_id);
        $this->assertFalse($settings->receipt_show_logo);
        $this->assertFalse($settings->receipt_show_signature);
        $this->assertTrue($settings->receipt_show_gstin);
    }

    public function test_a_malformed_accent_colour_is_rejected_by_the_form(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('business.update'), $this->payload([
            'receipt_accent_color' => 'javascript:alert(1)',
        ]))->assertSessionHasErrors('receipt_accent_color');
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Bill Smith',
            'industry' => 'general',
            'unit_label' => 'piece',
            'invoice_prefix' => 'INV-',
            'invoice_number_start' => 1,
            'default_tax_rate' => 0,
            'default_currency' => 'INR',
            'receipt_width' => '80',
            'receipt_accent_color' => '#0F172A',
            'receipt_show_gstin' => true,
            'state_code' => '27',
            'sms_driver' => 'log',
            'sms_country_code' => '+91',
            'quotation_prefix' => 'QT-',
            'next_quotation_sequence' => 1,
            'challan_prefix' => 'CH-',
            'next_challan_sequence' => 1,
            'sms_payment_reminders' => false,
            'sms_birthday_wishes' => false,
            'sms_anniversary_wishes' => false,
            'quotation_customer_decisions' => false,
            'quotation_show_updates' => true,
            'show_all_catalog_fields' => false,
        ], $overrides);
    }
}
