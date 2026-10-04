<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicQuotationStateTest extends TestCase
{
    use RefreshDatabase;

    protected function acceptedQuotation(): Invoice
    {
        $tenant = Tenant::factory()->create();
        $this->subscribe($tenant);

        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quotation = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'document_type' => 'quotation',
            'status' => 'sent',
            'quotation_status' => QuotationStatus::Accepted,
            'quotation_valid_until' => now()->addDays(7),
            'invoice_number' => 'QT-'.fake()->unique()->numerify('####'),
            'invoice_date' => now(),
            'subtotal' => 1000,
            'grand_total' => 1000,
            'created_by' => $user->id,
        ]);

        $shareLink = InvoiceShareLink::create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $quotation->id,
            'token' => 'accepted-token',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $shareLink->tenant_id = $tenant->id;
        $shareLink->save();

        $quotation->tenant_id = $tenant->id;
        $quotation->save();

        return $quotation;
    }

    public function test_requesting_changes_after_acceptance_returns_404(): void
    {
        $this->acceptedQuotation();

        $this->post('/invoice/view/accepted-token/changes', [
            'response' => 'Please change something',
            'name' => 'Customer',
        ])->assertNotFound();
    }

    public function test_requesting_changes_after_rejection_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $this->subscribe($tenant);

        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $quotation = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'document_type' => 'quotation',
            'status' => 'draft',
            'quotation_status' => QuotationStatus::Rejected,
            'quotation_valid_until' => now()->addDays(7),
            'invoice_number' => 'QT-'.fake()->unique()->numerify('####'),
            'invoice_date' => now(),
            'subtotal' => 1000,
            'grand_total' => 1000,
            'created_by' => $user->id,
        ]);

        $shareLink = InvoiceShareLink::create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $quotation->id,
            'token' => 'rejected-token',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $shareLink->tenant_id = $tenant->id;
        $shareLink->save();

        $quotation->tenant_id = $tenant->id;
        $quotation->save();

        $this->post('/invoice/view/rejected-token/changes', [
            'response' => 'Please change something',
        ])->assertNotFound();
    }

    public function test_requesting_changes_after_conversion_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        $this->subscribe($tenant);

        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $convertedInvoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'document_type' => 'general_invoice',
            'status' => 'unpaid',
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####'),
            'invoice_date' => now(),
            'subtotal' => 1000,
            'grand_total' => 1000,
            'created_by' => $user->id,
        ]);
        $convertedInvoice->tenant_id = $tenant->id;
        $convertedInvoice->save();

        $quotation = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'document_type' => 'quotation',
            'status' => 'converted',
            'converted_to_id' => $convertedInvoice->id,
            'quotation_status' => QuotationStatus::Converted,
            'quotation_valid_until' => now()->addDays(7),
            'invoice_number' => 'QT-'.fake()->unique()->numerify('####'),
            'invoice_date' => now(),
            'subtotal' => 1000,
            'grand_total' => 1000,
            'created_by' => $user->id,
        ]);

        $shareLink = InvoiceShareLink::create([
            'tenant_id' => $tenant->id,
            'invoice_id' => $quotation->id,
            'token' => 'converted-token',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $shareLink->tenant_id = $tenant->id;
        $shareLink->save();

        $quotation->tenant_id = $tenant->id;
        $quotation->save();

        $this->post('/invoice/view/converted-token/changes', [
            'response' => 'Please change something',
        ])->assertNotFound();
    }
}
