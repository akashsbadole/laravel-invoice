<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditDebitNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function invoice(float $grandTotal = 5000, float $paid = 0): Invoice
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Service line',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => $grandTotal,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $invoice = Invoice::where('document_type', DocumentType::GeneralInvoice->value)->firstOrFail();

        if ($paid > 0) {
            $invoice->payments()->create([
                'amount' => $paid,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'tenant_id' => $invoice->tenant_id,
            ]);
            $invoice->load('payments');
            $invoice->recalculatePaymentStatus();
            $invoice->save();
        }

        return $invoice;
    }

    protected function issue(Invoice $invoice, array $overrides = [])
    {
        return $this->actingAs($invoice->tenant->users()->first())->post(
            route('invoices.notes.store', $invoice),
            array_merge([
                'type' => DocumentType::CreditNote->value,
                'amount' => 1000,
                'tax_rate' => 0,
                'reason' => 'Returned goods',
            ], $overrides),
        );
    }

    public function test_a_credit_note_reduces_what_the_customer_owes(): void
    {
        $invoice = $this->invoice(5000);

        $this->issue($invoice, ['amount' => 2000])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $note = Invoice::where('document_type', DocumentType::CreditNote->value)->firstOrFail();

        $this->assertSame($invoice->id, $note->parent_invoice_id);
        $this->assertSame(InvoiceStatus::Closed, $note->status);
        $this->assertStringStartsWith('CN-', $note->invoice_number);

        $parent = $invoice->fresh();
        $this->assertSame(3000.0, (float) $parent->balance_amount);
        $this->assertSame(InvoiceStatus::Unpaid, $parent->status);
    }

    public function test_a_debit_note_adds_to_what_the_customer_owes(): void
    {
        $invoice = $this->invoice(5000);

        $this->issue($invoice, [
            'type' => DocumentType::DebitNote->value,
            'amount' => 750,
            'reason' => 'Undercharged on labour',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $note = Invoice::where('document_type', DocumentType::DebitNote->value)->firstOrFail();

        $this->assertStringStartsWith('DN-', $note->invoice_number);
        $this->assertSame(5750.0, (float) $invoice->fresh()->balance_amount);
    }

    public function test_a_credit_note_covers_an_unpaid_invoice_completely(): void
    {
        $invoice = $this->invoice(5000);

        $this->issue($invoice, ['amount' => 5000])->assertRedirect();

        $parent = $invoice->fresh();

        $this->assertSame(0.0, (float) $parent->balance_amount);
        $this->assertSame(InvoiceStatus::Paid, $parent->status);
    }

    public function test_a_credit_note_never_creates_a_negative_balance(): void
    {
        $invoice = $this->invoice(1000);

        $this->issue($invoice, ['amount' => 4000])->assertRedirect();

        $parent = $invoice->fresh();

        $this->assertSame(0.0, (float) $parent->balance_amount);
        $this->assertSame(InvoiceStatus::Paid, $parent->status);
    }

    public function test_a_cancelled_credit_note_stops_reducing_the_balance(): void
    {
        $invoice = $this->invoice(5000);
        $this->issue($invoice, ['amount' => 2000]);

        $note = Invoice::where('document_type', DocumentType::CreditNote->value)->firstOrFail();

        $this->actingAs($note->tenant->users()->first())
            ->post(route('invoices.cancel', $note))
            ->assertRedirect();

        $parent = $invoice->fresh();

        $this->assertSame(5000.0, (float) $parent->balance_amount);
        $this->assertSame(InvoiceStatus::Unpaid, $parent->status);
    }

    public function test_each_adjustment_type_numbers_on_its_own_sequence(): void
    {
        $invoice = $this->invoice();

        $this->issue($invoice, ['amount' => 100])->assertRedirect();
        $this->issue($invoice, ['amount' => 200])->assertRedirect();
        $this->issue($invoice, [
            'type' => DocumentType::DebitNote->value,
            'amount' => 300,
        ])->assertRedirect();

        $credits = Invoice::where('document_type', DocumentType::CreditNote->value)
            ->orderBy('id')->pluck('invoice_number')->all();
        $debits = Invoice::where('document_type', DocumentType::DebitNote->value)
            ->orderBy('id')->pluck('invoice_number')->all();

        $this->assertCount(2, $credits);
        $this->assertStringEndsWith('-00001', $credits[0]);
        $this->assertStringEndsWith('-00002', $credits[1]);
        $this->assertStringEndsWith('-00001', $debits[0]);
    }

    public function test_notes_are_not_counted_as_documents_or_sales_on_the_dashboard(): void
    {
        $invoice = $this->invoice(5000);
        $this->issue($invoice, ['amount' => 1500]);
        $this->issue($invoice, ['type' => DocumentType::DebitNote->value, 'amount' => 500]);

        $this->actingAs($invoice->tenant->users()->first())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stats.total_invoices', 1)
                ->where('stats.total_sales', 5000)
                ->where('stats.total_outstanding', 4000)
            );
    }

    public function test_a_fully_credited_invoice_leaves_the_reminder_queue(): void
    {
        $invoice = $this->invoice(5000);
        $invoice->update(['due_date' => now()->addDay()->toDateString()]);

        $this->issue($invoice, ['amount' => 5000])->assertRedirect();

        $this->actingAs($invoice->tenant->users()->first());

        $reminders = app(ReminderService::class)->gather();

        $this->assertNotContains(
            $invoice->id,
            $reminders['payments']->pluck('id')->all(),
        );
    }

    public function test_a_note_cannot_receive_payments(): void
    {
        $invoice = $this->invoice();
        $this->issue($invoice, ['amount' => 500]);

        $note = Invoice::where('document_type', DocumentType::CreditNote->value)->firstOrFail();

        $this->actingAs($note->tenant->users()->first())
            ->post(route('invoices.payments.store', $note), [
                'amount' => 100,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertForbidden();
    }

    public function test_a_note_cannot_be_issued_against_a_quotation(): void
    {
        $invoice = $this->invoice();
        $user = $invoice->tenant->users()->first();

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $invoice->customer_id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Quote',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 100,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->actingAs($user)
            ->post(route('invoices.notes.store', $quotation), [
                'type' => DocumentType::CreditNote->value,
                'amount' => 100,
            ])
            ->assertStatus(422);
    }

    public function test_the_regular_invoice_form_refuses_to_create_a_note(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.store'), [
                'customer_id' => $invoice->customer_id,
                'document_type' => DocumentType::CreditNote->value,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Sneaky note',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 100,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_a_viewer_cannot_issue_a_note(): void
    {
        $invoice = $this->invoice();
        $viewer = $this->userWithRole(UserRole::Viewer, $invoice->tenant);

        $this->actingAs($viewer)
            ->post(route('invoices.notes.store', $invoice), [
                'type' => DocumentType::CreditNote->value,
                'amount' => 100,
            ])
            ->assertForbidden();
    }

    public function test_the_parent_shows_its_notes_and_the_note_links_back(): void
    {
        $invoice = $this->invoice();
        $this->issue($invoice, ['amount' => 250]);
        $note = Invoice::where('document_type', DocumentType::CreditNote->value)->firstOrFail();

        $this->actingAs($invoice->tenant->users()->first())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/show')
                ->has('invoice.adjustment_notes', 1)
                ->where('invoice.adjustment_notes.0.id', $note->id)
            );

        $this->actingAs($invoice->tenant->users()->first())
            ->get(route('invoices.show', $note))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/show')
                ->where('invoice.parent_invoice.id', $invoice->id)
            );
    }

    public function test_the_credit_note_pdf_renders(): void
    {
        $invoice = $this->invoice();
        $this->issue($invoice, ['amount' => 500]);
        $note = Invoice::where('document_type', DocumentType::CreditNote->value)->firstOrFail();

        $response = $this->actingAs($note->tenant->users()->first())
            ->get(route('invoices.pdf', $note));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
