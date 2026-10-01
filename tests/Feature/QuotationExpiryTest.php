<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `quotation_valid_until` shipped as a column that nothing wrote, read or
 * acted on: it was mass-assignable but never populated, never displayed and
 * never compared against the clock, so `QuotationStatus::Expired` was only
 * reachable by a staff member picking it from a dropdown by hand.
 *
 * These cover the wiring that makes a quotation actually lapse.
 */
class QuotationExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $cachedUser = null;

    /**
     * One tenant for the whole test. A fresh admin per call would place each
     * document in a separate tenant, and both the reminders page and the
     * reminder digest are tenant-scoped.
     */
    protected function user(): User
    {
        return $this->cachedUser ??= $this->adminFor();
    }

    protected function quotation(array $attributes = []): Invoice
    {
        $user = $this->user();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), array_merge([
                'customer_id' => $customer->id,
                'document_type' => 'quotation',
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Ring',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 1000,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ], $attributes))
            ->assertRedirect();

        return Invoice::where('document_type', 'quotation')->latest('id')->firstOrFail();
    }

    public function test_a_validity_date_is_stored_for_a_quotation(): void
    {
        $until = now()->addDays(14)->toDateString();

        $quotation = $this->quotation(['quotation_valid_until' => $until]);

        $this->assertSame($until, $quotation->fresh()->quotation_valid_until->toDateString());
    }

    public function test_a_validity_date_is_ignored_on_a_real_invoice(): void
    {
        // Storing it on a sale would make the nightly sweep expire a real
        // invoice as though it were a lapsed quote.
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => 'general_invoice',
                'invoice_date' => now()->toDateString(),
                'quotation_valid_until' => now()->addDay()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Item', 'quantity' => 1, 'rate_type' => 'fixed',
                    'rate' => 100, 'tax_rate' => 0, 'discount' => 0, 'charges' => [],
                ]],
            ])
            ->assertRedirect();

        $invoice = Invoice::where('document_type', 'general_invoice')->latest('id')->firstOrFail();

        $this->assertNull($invoice->quotation_valid_until);
    }

    public function test_a_lapsed_quotation_reads_as_expired(): void
    {
        $quotation = $this->quotation([
            'invoice_date' => now()->subDays(10)->toDateString(),
            'quotation_valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->assertSame(
            QuotationStatus::Expired,
            app(QuotationService::class)->currentStatus($quotation),
        );
    }

    public function test_a_quotation_inside_its_window_does_not_read_as_expired(): void
    {
        $quotation = $this->quotation([
            'quotation_valid_until' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertNotSame(
            QuotationStatus::Expired,
            app(QuotationService::class)->currentStatus($quotation),
        );
    }

    public function test_an_accepted_quotation_is_never_lapsed_by_the_overlay(): void
    {
        // The customer already committed; a date passing must not rewrite it.
        $quotation = $this->quotation([
            'invoice_date' => now()->subDays(30)->toDateString(),
            'quotation_valid_until' => now()->subDays(20)->toDateString(),
        ]);

        app(QuotationService::class)->decide($quotation, QuotationStatus::Accepted);

        $this->assertSame(
            QuotationStatus::Accepted,
            app(QuotationService::class)->currentStatus($quotation->fresh()),
        );
    }

    public function test_the_sweep_persists_expiry_for_a_lapsed_quotation(): void
    {
        $quotation = $this->quotation([
            'invoice_date' => now()->subDays(10)->toDateString(),
            'quotation_valid_until' => now()->subDay()->toDateString(),
        ]);

        // Stored state is still draft/sent; only the read overlay said expired.
        $this->assertNotSame(
            QuotationStatus::Expired,
            $quotation->fresh()->quotation_status,
        );

        $expired = app(QuotationService::class)->expireLapsed();

        $this->assertSame(1, $expired);
        $this->assertSame(
            QuotationStatus::Expired,
            $quotation->fresh()->quotation_status,
            'The sweep must persist the state, not rely on the read overlay.',
        );
    }

    public function test_the_sweep_leaves_a_quotation_inside_its_window_alone(): void
    {
        $this->quotation(['quotation_valid_until' => now()->addDays(7)->toDateString()]);

        $this->assertSame(0, app(QuotationService::class)->expireLapsed());
    }

    public function test_the_sweep_does_not_touch_a_decided_quotation(): void
    {
        $quotation = $this->quotation([
            'invoice_date' => now()->subDays(30)->toDateString(),
            'quotation_valid_until' => now()->subDays(20)->toDateString(),
        ]);

        app(QuotationService::class)->decide($quotation, QuotationStatus::Rejected);

        $this->assertSame(0, app(QuotationService::class)->expireLapsed());
        $this->assertSame(
            QuotationStatus::Rejected,
            $quotation->fresh()->quotation_status,
        );
    }

    public function test_the_sweep_command_runs(): void
    {
        $this->quotation([
            'invoice_date' => now()->subDays(10)->toDateString(),
            'quotation_valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('quotations:expire')
            ->expectsOutputToContain('1 quotation(s) marked expired.')
            ->assertSuccessful();
    }

    public function test_staff_are_reminded_about_quotes_closing_soon(): void
    {
        $this->quotation(['quotation_valid_until' => now()->addDays(2)->toDateString()]);

        $gathered = app(ReminderService::class)->gather();

        $this->assertCount(1, $gathered['expiring_quotes']);
    }

    public function test_a_distant_expiry_is_not_reminded_yet(): void
    {
        $this->quotation(['quotation_valid_until' => now()->addDays(30)->toDateString()]);

        $this->assertCount(0, app(ReminderService::class)->gather()['expiring_quotes']);
    }

    public function test_the_reminders_page_exposes_expiring_quotes(): void
    {
        // Reuse the tenant the quotation helper created: a second admin would
        // belong to a different tenant, and the page is tenant-scoped.
        $quotation = $this->quotation([
            'quotation_valid_until' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($this->user())
            ->get(route('reminders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reminders')
                ->has('expiringQuotes', 1)
                ->where('expiringQuotes.0.id', $quotation->id)
                ->where('expiringQuotes.0.quotation_valid_until', now()->addDays(2)->toDateString())
            );
    }
}
