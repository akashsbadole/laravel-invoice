<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MetalRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReQuoteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A gold quotation priced ten days ago at 7000/g, with a making
     * charge and a per-gram metal line.
     */
    private function oldGoldInvoice(User $user, float $oldRate = 7000.0): Invoice
    {
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->subDays(10)->toDateString(),
            'rate_locked_at' => now()->subDays(10)->toDateString(),
            'pricing_mode' => 'jewelry_calculated',
            'tax_mode' => 'cgst_sgst',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => '22K Gold Necklace',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => $oldRate,
                'net_weight' => 10,
                'metal_type' => 'Gold',
                'purity' => '22K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::where('document_type', DocumentType::Quotation->value)->latest('id')->firstOrFail();
    }

    private function recordRate(User $user, float $rate, string $date): void
    {
        MetalRate::create([
            'metal_type' => 'Gold',
            'purity' => '22K',
            'rate_date' => $date,
            'rate_per_gram' => $rate,
            'created_by' => $user->id,
        ]);
    }

    public function test_a_re_quote_uses_todays_rate_not_the_old_one(): void
    {
        $user = $this->adminFor();
        $old = $this->oldGoldInvoice($user, 7000.0);

        $this->recordRate($user, 7600.0, now()->toDateString());

        $this->actingAs($user)
            ->post(route('invoices.re-quote', $old))
            ->assertRedirect();

        $new = Invoice::where('document_type', DocumentType::Quotation->value)
            ->where('id', '!=', $old->id)
            ->latest('id')
            ->firstOrFail();

        $item = InvoiceItem::where('invoice_id', $new->id)->firstOrFail();

        $this->assertSame(7600.0, (float) $item->rate, 'The re-quote must price at today\'s rate.');

        // 10g × 7600 = 76,000 base, +3% CGST/SGST.
        $this->assertSame(76000.0, (float) $item->base_value);
        $this->assertGreaterThan((float) $old->grand_total, (float) $new->grand_total);
    }

    public function test_a_re_quote_is_a_fresh_draft_not_a_copy_of_the_old_terms(): void
    {
        $user = $this->adminFor();
        $old = $this->oldGoldInvoice($user);

        $this->recordRate($user, 7600.0, now()->toDateString());

        $this->actingAs($user)->post(route('invoices.re-quote', $old))->assertRedirect();

        $new = Invoice::where('document_type', DocumentType::Quotation->value)
            ->where('id', '!=', $old->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(InvoiceStatus::Draft, $new->status);
        $this->assertSame(1, $new->revision_number);
        $this->assertSame(0.0, (float) $new->paid_amount, 'A re-quote has been paid nothing.');
        $this->assertNull($new->converted_to_id);

        // The rate date must be today, not the ten-day-old one it copies.
        $this->assertSame(now()->toDateString(), $new->rate_locked_at->toDateString());

        // Same customer, same piece — that is the whole point.
        $this->assertSame($old->customer_id, $new->customer_id);
        $this->assertSame(
            $old->items->first()->item_name,
            $new->items->first()->item_name,
        );
    }

    public function test_an_old_discount_approval_does_not_carry_to_a_re_quote(): void
    {
        $user = $this->adminFor();
        $old = $this->oldGoldInvoice($user);

        $old->update([
            'discount' => 5000,
            'discount_approved_by' => $user->id,
            'discount_approved_at' => now()->subDay(),
            'discount_approved_discount' => 5000,
        ]);

        $this->recordRate($user, 7600.0, now()->toDateString());

        $this->actingAs($user)->post(route('invoices.re-quote', $old))->assertRedirect();

        $new = Invoice::where('document_type', DocumentType::Quotation->value)
            ->where('id', '!=', $old->id)
            ->latest('id')
            ->firstOrFail();

        // The new total is a different number, so the old approval is void.
        $this->assertNull($new->discount_approved_by);
        $this->assertNull($new->discount_approved_discount);
    }

    public function test_a_non_metal_line_keeps_its_original_price(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->subDays(10)->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Ring resizing',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1200,
                'tax_rate' => 18,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $old = Invoice::where('document_type', DocumentType::Quotation->value)->latest('id')->firstOrFail();

        // A gold rate exists, but it must not touch a fixed-price service.
        $this->recordRate($user, 9999.0, now()->toDateString());

        $this->actingAs($user)->post(route('invoices.re-quote', $old))->assertRedirect();

        $new = Invoice::where('document_type', DocumentType::Quotation->value)
            ->where('id', '!=', $old->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(1200.0, (float) $new->items->first()->rate);
    }

    public function test_an_adjustment_note_cannot_be_re_quoted(): void
    {
        $user = $this->adminFor();
        $old = $this->oldGoldInvoice($user);

        $old->update(['document_type' => DocumentType::CreditNote->value]);

        $this->actingAs($user)
            ->post(route('invoices.re-quote', $old))
            ->assertStatus(422);
    }

    public function test_the_latest_rate_wins_when_a_metal_was_recorded_twice(): void
    {
        $user = $this->adminFor();
        $old = $this->oldGoldInvoice($user, 7000.0);

        $this->recordRate($user, 7400.0, now()->subDay()->toDateString());
        $this->recordRate($user, 7600.0, now()->toDateString());

        $this->actingAs($user)->post(route('invoices.re-quote', $old))->assertRedirect();

        $new = Invoice::where('document_type', DocumentType::Quotation->value)
            ->where('id', '!=', $old->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(7600.0, (float) $new->items->first()->rate);
    }
}
