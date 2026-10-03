<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner sets a password on the share link and reads it out to the
 * customer separately. These tests pin the behaviour that actually
 * matters: the PDF is unreachable until the password is entered.
 */
class ShareLinkPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceFor(User $user): Invoice
    {
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Ring',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 7000,
                'net_weight' => 5,
                'metal_type' => 'Gold',
                'purity' => '22K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::where('document_type', DocumentType::Quotation->value)->latest('id')->firstOrFail();
    }

    private function linkWithPassword(User $user, Invoice $invoice, string $password = '4821'): InvoiceShareLink
    {
        $this->actingAs($user)->post(route('invoices.share-links.store', $invoice), [
            'password' => $password,
        ])->assertRedirect();

        return InvoiceShareLink::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
    }

    public function test_the_owner_can_set_a_password_on_a_share_link(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        $link = $this->linkWithPassword($user, $invoice);

        $this->assertTrue($link->has_password);
        $this->assertTrue($link->checkPassword('4821'));
        $this->assertFalse($link->checkPassword('wrong'));
    }

    public function test_the_password_hash_is_never_exposed_to_the_browser(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        $link = $this->linkWithPassword($user, $invoice);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('invoice.share_links.0.has_password', true)
                ->missing('invoice.share_links.0.password_hash')
            );
    }

    public function test_the_page_and_pdf_are_locked_until_the_password_is_entered(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);
        $link = $this->linkWithPassword($user, $invoice);

        // Locked: the page asks for the password, the PDF refuses outright.
        $this->get(route('invoices.public.show', $link->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('status', 'password_required'));

        $this->get(route('invoices.public.pdf', $link->token))->assertStatus(403);

        // A wrong password must not unlock it.
        $this->post(route('invoices.public.verify', $link->token), ['password' => 'nope'])
            ->assertSessionHasErrors('password');

        $this->get(route('invoices.public.pdf', $link->token))->assertStatus(403);

        // The correct password opens both.
        $this->post(route('invoices.public.verify', $link->token), ['password' => '4821'])
            ->assertRedirect();

        $this->get(route('invoices.public.show', $link->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('status', 'ok'));

        $this->get(route('invoices.public.pdf', $link->token))->assertOk();
    }

    public function test_a_link_without_a_password_opens_the_pdf_directly(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        $this->actingAs($user)->post(route('invoices.share-links.store', $invoice))->assertRedirect();

        $link = InvoiceShareLink::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();

        $this->assertFalse($link->has_password);
        $this->get(route('invoices.public.pdf', $link->token))->assertOk();
    }

    public function test_a_blank_expiry_does_not_fail_validation(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        // An untouched number input posts "" — it must be treated as "never".
        $this->actingAs($user)->post(route('invoices.share-links.store', $invoice), [
            'expires_in_days' => '',
            'password' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $link = InvoiceShareLink::where('invoice_id', $invoice->id)->latest('id')->firstOrFail();

        $this->assertNull($link->expires_at);
        $this->assertFalse($link->has_password);
    }

    public function test_a_short_password_is_rejected(): void
    {
        $user = $this->adminFor();
        $invoice = $this->invoiceFor($user);

        $this->actingAs($user)->post(route('invoices.share-links.store', $invoice), [
            'password' => '12',
        ])->assertSessionHasErrors('password');
    }
}
