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
    public function cloneAsNew(
        Invoice $source,
        int $createdById,
        string $documentType = DocumentType::JewelryInvoice->value,
        ?string $dueDate = null,
        bool $resetToDraft = false
    ): Invoice {
        // Run inside the source invoice's tenant so numbering, settings
        // and the tenant auto-fill behave identically from web and console.
        return Tenant::runInContext($source->tenant_id, fn () => DB::transaction(function () use ($source, $createdById, $documentType, $dueDate, $resetToDraft) {
            $business = BusinessSetting::query()->lockForUpdate()->first() ?? BusinessSetting::current();

            $source->loadMissing(['items.charges', 'charges']);

            $isQuotation = $documentType === DocumentType::Quotation->value;
            $status = $resetToDraft && $isQuotation ? InvoiceStatus::Draft : InvoiceStatus::Unpaid;
            $invoiceNumber = $isQuotation
                ? $business->nextQuotationNumber()
                : $business->nextInvoiceNumber();

            $copy = Invoice::create([
                'customer_id' => $source->customer_id,
                'document_type' => $documentType,
                'status' => $status,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => today()->toDateString(),
                'due_date' => $dueDate ?? $source->due_date,
                'quotation_valid_until' => $isQuotation ? $source->quotation_valid_until : null,
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
                'tds_rate' => $source->tds_rate,
                'tds_amount' => $source->tds_amount,
                'tcs_rate' => $source->tcs_rate,
                'tcs_amount' => $source->tcs_amount,
                'round_off' => $source->round_off,
                'grand_total' => $source->grand_total,
                'paid_amount' => 0,
                'balance_amount' => max((float) $source->grand_total - (float) $source->tds_amount, 0),
                'notes' => $source->notes,
                'terms' => $source->terms,
                'rate_locked_at' => $source->rate_locked_at,
                'discount_approved_by' => $source->discount_approved_by,
                'discount_approved_at' => $source->discount_approved_at,
                'discount_approved_discount' => $source->discount_approved_discount,
                'created_by' => $createdById,
            ]);

            foreach ($source->items as $item) {
                $newItem = $copy->items()->create(
                    $item->only(array_diff($item->getFillable(), ['invoice_id'])),
                );

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
