<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Enums\InvoiceStatus;
use App\Models\ActivityLog;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class InvoiceCloner
{
    /**
     * Duplicate an invoice as a brand-new unpaid document: fresh number,
     * today's date, copied items/charges, zero payments.
     */
    public function cloneAsNew(Invoice $source, int $createdById, string $documentType = DocumentType::JewelryInvoice->value): Invoice
    {
        // Run inside the source invoice's tenant so numbering, settings
        // and the tenant auto-fill behave identically from web and console.
        return Tenant::runInContext($source->tenant_id, fn () => DB::transaction(function () use ($source, $createdById, $documentType) {
            $business = BusinessSetting::query()->lockForUpdate()->first() ?? BusinessSetting::current();

            $source->loadMissing(['items.charges', 'charges']);

            $copy = Invoice::create([
                'customer_id' => $source->customer_id,
                'document_type' => $documentType,
                'status' => InvoiceStatus::Unpaid,
                'invoice_number' => $business->nextInvoiceNumber(),
                'invoice_date' => today()->toDateString(),
                'due_date' => $source->due_date,
                'reference_number' => $source->reference_number,
                'salesperson_id' => $source->salesperson_id,
                'invoice_template_id' => $source->invoice_template_id,
                'pricing_mode' => $source->pricing_mode,
                'tax_mode' => $source->tax_mode,
                'tax_breakdown' => $source->tax_breakdown,
                'subtotal' => $source->subtotal,
                'charges_summary' => $source->charges_summary,
                'discount' => $source->discount,
                'tax' => $source->tax,
                'round_off' => $source->round_off,
                'grand_total' => $source->grand_total,
                'paid_amount' => 0,
                'balance_amount' => $source->grand_total,
                'notes' => $source->notes,
                'terms' => $source->terms,
                'created_by' => $createdById,
            ]);

            foreach ($source->items as $item) {
                $newItem = $copy->items()->create($item->only([
                    'sort_order', 'item_name', 'description', 'item_code', 'hsn_code',
                    'metal_type', 'purity', 'huid_number', 'stone_clarity', 'stone_color',
                    'stone_carat', 'certificate_number', 'quantity', 'gross_weight',
                    'net_weight', 'stone_weight', 'rate_type', 'rate', 'base_value',
                    'discount', 'tax_rate', 'tax', 'total',
                ]));

                foreach ($item->charges as $charge) {
                    $newItem->charges()->create($charge->only([
                        'charge_type_id', 'label', 'code', 'calculation_type',
                        'is_taxable', 'rate', 'amount', 'sort_order',
                    ]));
                }
            }

            foreach ($source->charges as $charge) {
                $copy->charges()->create($charge->only([
                    'charge_type_id', 'label', 'code', 'calculation_type',
                    'is_taxable', 'rate', 'amount', 'sort_order',
                ]));
            }

            InvoiceEvent::log($copy, InvoiceEventType::Created, ['cloned_from' => $source->id], $createdById);
            ActivityLog::record('invoice.created', $copy, "Created invoice {$copy->invoice_number}");

            return $copy;
        }));
    }
}
