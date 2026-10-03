<?php

namespace App\Console\Commands;

use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\QuotationFollowUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class QuotationDigestCommand extends Command
{
    protected $signature = 'quotations:digest';

    protected $description = 'Send owners a daily summary of quotation attention items';

    public function handle(QuotationFollowUpService $followUps): int
    {
        $tenants = Tenant::query()->pluck('id');

        foreach ($tenants as $tenantId) {
            Tenant::runInContext($tenantId, function () use ($tenantId, $followUps) {
                $settings = BusinessSetting::forTenant($tenantId);

                if (! $settings->quotation_alerts_owner) {
                    return;
                }

                $quotations = Invoice::query()
                    ->where('document_type', DocumentType::Quotation->value)
                    ->with(['customer', 'shareLinks'])
                    ->get();

                $expiringSoon = $quotations->filter(function ($q) use ($followUps) {
                    return $followUps->expiringSoon($q);
                });

                $needsChase = $quotations->filter(function ($q) {
                    return $q->quotation_status === QuotationStatus::Sent->value
                        && $q->shareLinks->isNotEmpty()
                        && $q->shareLinks->sortByDesc('id')->first()->viewed_at !== null
                        && $q->invoice_date->diffInDays(now()) >= 3;
                });

                $acceptedNotConverted = $quotations->filter(function ($q) {
                    return $q->quotation_status === QuotationStatus::Accepted->value
                        && $q->converted_to_id === null;
                });

                if ($expiringSoon->isEmpty() && $needsChase->isEmpty() && $acceptedNotConverted->isEmpty()) {
                    return;
                }

                $lines = [];
                $lines[] = "Quotation Digest for {$settings->business_name}";
                $lines[] = str_repeat('-', 40);

                if ($expiringSoon->isNotEmpty()) {
                    $lines[] = "\nExpiring Soon (within 3 days):";
                    foreach ($expiringSoon as $q) {
                        $lines[] = "  - {$q->invoice_number}: {$q->customer->full_name} ({$q->grand_total}) valid until {$q->quotation_valid_until?->format('d M')}";
                    }
                }

                if ($needsChase->isNotEmpty()) {
                    $lines[] = "\nOpened but Not Answered (>3 days):";
                    foreach ($needsChase as $q) {
                        $lines[] = "  - {$q->invoice_number}: {$q->customer->full_name} ({$q->grand_total})";
                    }
                }

                if ($acceptedNotConverted->isNotEmpty()) {
                    $lines[] = "\nAccepted but Not Converted:";
                    foreach ($acceptedNotConverted as $q) {
                        $lines[] = "  - {$q->invoice_number}: {$q->customer->full_name} ({$q->grand_total})";
                    }
                }

                $message = implode("\n", $lines);

                if ($settings->quotation_alerts_email && ! empty($settings->quotation_alerts_email)) {
                    \Mail::raw($message, function ($mail) use ($settings) {
                        $mail->to($settings->quotation_alerts_email)
                            ->subject("Quotation Digest - {$settings->business_name}");
                    });
                }

                Log::info('Quotation digest sent', [
                    'tenant_id' => $tenantId,
                    'expiring_soon' => $expiringSoon->count(),
                    'needs_chase' => $needsChase->count(),
                    'accepted_not_converted' => $acceptedNotConverted->count(),
                ]);
            });
        }

        $this->info('Quotation digest command completed.');

        return 0;
    }
}
