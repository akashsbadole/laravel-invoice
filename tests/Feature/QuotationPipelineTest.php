<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pipeline board exists because three quotation states used to render
 * identically in a list. These assert the numbers behind it, and that the
 * read-time expiry overlay is honoured rather than a GROUP BY on the column.
 */
class QuotationPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected function quotation(array $attributes = []): Invoice
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

        $quotation = Invoice::query()->latest('id')->firstOrFail();

        if ($attributes !== []) {
            $quotation->update($attributes);
        }

        return $quotation->refresh();
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

        return Invoice::query()->latest('id')->firstOrFail();
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

    public function test_it_values_the_open_pipeline(): void
    {
        $this->quotation();

        $this->actingAs($this->staff)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('quotations/index')
                ->where('openValue', fn ($value): bool => (float) $value === 50000.0)
                ->where('stages', function ($stages): bool {
                    $draft = collect($stages)->firstWhere('value', QuotationStatus::Draft->value);

                    return $draft !== null && $draft['count'] === 1 && (float) $draft['total'] === 50000.0;
                })
            );
    }

    /**
     * The stored status stays `null` for a lapsed quote; only the read-time
     * overlay knows it is expired. A SQL GROUP BY would file it as a draft.
     */
    public function test_a_lapsed_quotation_is_bucketed_as_expired(): void
    {
        $this->quotation(['quotation_valid_until' => now()->subDay()->toDateString()]);

        $this->actingAs($this->staff)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stages', function ($stages): bool {
                $expired = collect($stages)->firstWhere('value', QuotationStatus::Expired->value);
                $draft = collect($stages)->firstWhere('value', QuotationStatus::Draft->value);

                return $expired['count'] === 1 && $draft['count'] === 0;
            }));
    }

    public function test_sales_invoices_are_excluded(): void
    {
        $this->salesInvoice();

        $this->actingAs($this->staff)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('openValue', fn ($value): bool => (float) $value === 0.0)
                ->where('stages', fn ($stages): bool => collect($stages)->sum('count') === 0)
            );
    }

    public function test_it_lists_a_viewed_but_unanswered_quote_for_chasing(): void
    {
        $quotation = $this->quotation(['invoice_date' => now()->subDays(5)->toDateString()]);
        $this->shareLink($quotation, ['viewed_at' => now()->subDays(2)]);

        $this->actingAs($this->staff)->post(route('invoices.quotation-status', $quotation), [
            'status' => QuotationStatus::Sent->value,
        ])->assertRedirect();

        $this->actingAs($this->staff)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'needsChase',
                fn ($rows): bool => collect($rows)->contains(fn (array $row): bool => $row['id'] === $quotation->id),
            ));
    }

    public function test_it_lists_an_expiring_quote(): void
    {
        $quotation = $this->quotation([
            'quotation_valid_until' => now()->addDays(2)->toDateString(),
        ]);
        $this->shareLink($quotation);

        $this->actingAs($this->staff)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'expiringSoon',
                fn ($rows): bool => collect($rows)->contains(fn (array $row): bool => $row['id'] === $quotation->id),
            ));
    }

    public function test_another_tenant_sees_an_empty_board(): void
    {
        $this->quotation();
        $outsider = $this->adminFor();

        $this->actingAs($outsider)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'stages',
                fn ($stages): bool => collect($stages)->sum('count') === 0,
            ));
    }

    public function test_it_counts_follow_ups_that_are_due(): void
    {
        $quotation = $this->quotation(['invoice_date' => now()->subDays(5)->toDateString()]);
        $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_followup_enabled' => true,
            'quotation_followup_days' => 2,
        ]);

        $this->actingAs($this->staff)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dueCount', fn ($count): bool => (int) $count === 1)
                ->where('followUpEnabled', true)
            );
    }
}
