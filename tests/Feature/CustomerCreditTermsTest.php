<?php

namespace Tests\Feature;

use App\Enums\ContactChannel;
use App\Enums\GstinType;
use App\Enums\PriceTier;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PaymentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Credit terms have to be enforced, which is why these live in real columns
 * rather than the free-form `attributes` map: a credit limit that is only a
 * string cannot be compared against an outstanding balance.
 */
class CustomerCreditTermsTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $cachedUser = null;

    /**
     * One tenant per test. Reports and credit balances are tenant-scoped, so a
     * fresh admin per call would hide earlier rows from later assertions.
     */
    protected function user(): User
    {
        return $this->cachedUser ??= $this->adminFor();
    }

    protected function invoice(Customer $customer, float $amount, string $type = 'general_invoice'): Invoice
    {
        $response = $this->actingAs($this->user())
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => $type,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => $amount,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ]);

        $response->assertRedirect();

        return Invoice::where('customer_id', $customer->id)
            ->where('document_type', $type)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * Reminder channels are tenant settings, off by default, so they have to be
     * switched on before a customer's channel preference can be observed.
     */
    protected function enableReminderChannels(): void
    {
        BusinessSetting::forTenant($this->user()->tenant_id)->update([
            'sms_payment_reminders' => true,
            'email_payment_reminders' => true,
        ]);
    }

    public function test_a_customer_with_no_credit_limit_is_never_blocked(): void
    {
        $customer = $this->customerFor($this->user());

        $this->assertFalse($customer->hasCreditLimit());
        $this->assertNull($customer->creditOverrun());

        // A very large invoice is fine when credit is not extended.
        $this->invoice($customer, 5_000_00);
    }

    public function test_an_invoice_within_the_limit_is_allowed(): void
    {
        $customer = $this->customerFor($this->user(), ['credit_limit' => 10000]);

        $this->invoice($customer, 6000);

        $this->assertSame(6000.0, $customer->creditOutstanding());
        $this->assertSame(0.0, $customer->creditOverrun());
    }

    public function test_an_invoice_beyond_the_limit_is_refused(): void
    {
        $customer = $this->customerFor($this->user(), ['credit_limit' => 10000]);

        $this->invoice($customer, 8000);

        // 8000 already outstanding, so 5000 more breaches the limit.
        $this->actingAs($this->user())
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => 'general_invoice',
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line', 'quantity' => 1, 'rate_type' => 'fixed',
                    'rate' => 5000, 'tax_rate' => 0, 'discount' => 0, 'charges' => [],
                ]],
            ])
            ->assertSessionHasErrors('customer_id');

        $this->assertSame(1, Invoice::count(), 'The over-limit invoice was written.');
    }

    public function test_the_error_message_names_the_customer_and_the_numbers(): void
    {
        $customer = $this->customerFor($this->user(), [
            'credit_limit' => 10000,
            'full_name' => 'Asha Verma',
        ]);

        $this->invoice($customer, 9000);

        $this->actingAs($this->user())
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => 'general_invoice',
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line', 'quantity' => 1, 'rate_type' => 'fixed',
                    'rate' => 3000, 'tax_rate' => 0, 'discount' => 0, 'charges' => [],
                ]],
            ])
            ->assertSessionHasErrors([
                'customer_id' => 'Asha Verma is over their credit limit. '
                    .'Balance after this invoice would be 12,000.00 '
                    .'against a limit of 10,000.00.',
            ]);
    }

    public function test_a_quotation_never_counts_against_a_credit_limit(): void
    {
        // Start with no limit so the invoices can be written, then set a limit the
        // customer is already over - the realistic "they ran up a tab" case.
        $customer = $this->customerFor($this->user());

        $this->invoice($customer, 10000);
        $this->invoice($customer, 24000);

        $customer->update(['credit_limit' => 30000]);

        $this->assertSame(34000.0, $customer->creditOutstanding());
        $this->assertSame(4000.0, $customer->creditOverrun());

        $response = $this->actingAs($this->user())
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => 'quotation',
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line', 'quantity' => 1, 'rate_type' => 'fixed',
                    'rate' => 50000, 'tax_rate' => 0, 'discount' => 0, 'charges' => [],
                ]],
            ]);

        $response->assertSessionHasNoErrors();

        $quotation = Invoice::where('document_type', 'quotation')
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($quotation, 'A quotation must not be blocked by a credit limit.');

        // And the quote itself did not become outstanding money owed.
        $this->assertSame(34000.0, $customer->creditOutstanding());
    }

    public function test_re_saving_an_invoice_does_not_double_count_its_own_balance(): void
    {
        $customer = $this->customerFor($this->user(), ['credit_limit' => 10000]);
        $invoice = $this->invoice($customer, 9000);

        // Saving the same 9000 again would otherwise look like 18000.
        $this->actingAs($this->user())
            ->put(route('invoices.update', $invoice), [
                'customer_id' => $customer->id,
                'document_type' => 'general_invoice',
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line', 'quantity' => 1, 'rate_type' => 'fixed',
                    'rate' => 9000, 'tax_rate' => 0, 'discount' => 0, 'charges' => [],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(9000.0, $customer->creditOutstanding());
    }

    public function test_credit_days_fill_in_the_due_date(): void
    {
        $customer = $this->customerFor($this->user(), ['credit_days' => 30]);

        $invoice = $this->invoice($customer, 1000);

        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $invoice->fresh()->due_date->toDateString(),
        );
    }

    public function test_an_explicit_due_date_wins_over_credit_days(): void
    {
        $customer = $this->customerFor($this->user(), ['credit_days' => 30]);
        $explicit = now()->addDays(7)->toDateString();

        $this->actingAs($this->user())
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => 'general_invoice',
                'invoice_date' => now()->toDateString(),
                'due_date' => $explicit,
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Line', 'quantity' => 1, 'rate_type' => 'fixed',
                    'rate' => 100, 'tax_rate' => 0, 'discount' => 0, 'charges' => [],
                ]],
            ])
            ->assertRedirect();

        $this->assertSame(
            $explicit,
            Invoice::where('customer_id', $customer->id)
                ->latest('id')
                ->firstOrFail()
                ->due_date
                ->toDateString(),
        );
    }

    public function test_a_customer_without_credit_days_gets_no_due_date(): void
    {
        $customer = $this->customerFor($this->user());

        $this->assertNull($customer->creditDueDate());

        $this->assertNull($this->invoice($customer, 1000)->fresh()->due_date);
    }

    public function test_commercial_fields_persist_through_the_form(): void
    {
        $this->actingAs($this->user())
            ->post(route('customers.store'), [
                'full_name' => 'Vikram Rao',
                'mobile_number' => '9800000099',
                'customer_type' => 'business',
                'gstin_type' => 'composition',
                'state_code' => '29',
                'place_of_supply' => '27',
                'credit_limit' => 25000,
                'credit_days' => 45,
                'price_tier' => 'b',
                'preferred_contact_channel' => 'email',
                'referral_source' => 'Trade show',
                'tags' => ['bridal', 'vip'],
            ])
            ->assertRedirect();

        $customer = Customer::where('full_name', 'Vikram Rao')->sole();

        $this->assertSame(GstinType::Composition, $customer->gstin_type);
        $this->assertSame('27', $customer->place_of_supply);
        $this->assertSame('25000.00', $customer->credit_limit);
        $this->assertSame(45, $customer->credit_days);
        $this->assertSame(PriceTier::B, $customer->price_tier);
        $this->assertSame(ContactChannel::Email, $customer->preferred_contact_channel);
        $this->assertSame('Trade show', $customer->referral_source);
        $this->assertSame(['bridal', 'vip'], $customer->tags);
    }

    public function test_place_of_supply_falls_back_to_the_home_state(): void
    {
        $customer = $this->customerFor($this->user(), ['state_code' => '29']);

        $this->assertSame('29', $customer->effectivePlaceOfSupply());

        $customer->place_of_supply = '27';

        $this->assertSame('27', $customer->effectivePlaceOfSupply());
    }

    public function test_gstin_type_drives_whether_a_buyer_is_b2b(): void
    {
        $customer = $this->customerFor($this->user(), [
            'gstin_type' => 'consumer',
            'tax_number' => '29AAAPZ1234C1ZV',
        ]);

        // Consumer is B2C even though the row still holds a GSTIN, because the
        // customer explicitly says they are not a registered business buyer.
        $this->assertFalse($customer->isBusinessBuyer());

        $customer->gstin_type = GstinType::Regular;

        $this->assertTrue($customer->isBusinessBuyer());
    }

    public function test_without_a_gstin_type_a_gstin_still_implies_b2b(): void
    {
        $withGstin = $this->customerFor($this->user(), [
            'tax_number' => '29AAAPZ1234C1ZV',
        ]);

        $this->assertTrue($withGstin->isBusinessBuyer());

        $this->assertFalse($this->customerFor($this->user())->isBusinessBuyer());
    }

    public function test_an_email_only_customer_is_not_texted(): void
    {
        $user = $this->user();
        $customer = $this->customerFor($user, [
            'preferred_contact_channel' => 'email',
            'email' => 'asha@example.com',
        ]);

        $this->enableReminderChannels();

        $invoice = $this->invoice($customer, 1000);

        $sent = app(PaymentReminderService::class)->sendForInvoice($invoice->fresh(), force: true);

        $this->assertArrayNotHasKey('sms', $sent);
        $this->assertArrayHasKey('email', $sent);
    }

    public function test_a_phone_only_customer_is_not_messaged_automatically(): void
    {
        $user = $this->user();
        $customer = $this->customerFor($user, [
            'preferred_contact_channel' => 'call',
            'email' => 'asha@example.com',
        ]);

        $this->enableReminderChannels();

        $invoice = $this->invoice($customer, 1000);

        $sent = app(PaymentReminderService::class)->sendForInvoice($invoice->fresh(), force: true);

        $this->assertSame([], $sent);
        // Still stamped, so the nightly job does not retry every night.
        $this->assertNotNull($invoice->fresh()->last_reminder_sent_at);
    }

    public function test_no_preference_still_uses_both_channels(): void
    {
        $user = $this->user();
        $customer = $this->customerFor($user, ['email' => 'asha@example.com']);

        $this->enableReminderChannels();

        $invoice = $this->invoice($customer, 1000);

        $sent = app(PaymentReminderService::class)->sendForInvoice($invoice->fresh(), force: true);

        $this->assertArrayHasKey('sms', $sent);
        $this->assertArrayHasKey('email', $sent);
    }

    public function test_a_whatsapp_preference_is_served_by_the_text_channel(): void
    {
        $user = $this->user();
        $customer = $this->customerFor($user, [
            'preferred_contact_channel' => 'whatsapp',
            'email' => 'asha@example.com',
        ]);

        $this->enableReminderChannels();

        $invoice = $this->invoice($customer, 1000);

        $sent = app(PaymentReminderService::class)->sendForInvoice($invoice->fresh(), force: true);

        $this->assertArrayHasKey('sms', $sent);
        $this->assertArrayNotHasKey('email', $sent);
    }
}
