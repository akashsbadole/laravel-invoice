<?php

namespace App\Console\Commands;

use App\Services\QuotationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Marks quotations whose validity window has closed as expired.
 *
 * `currentStatus()` already reports a lapsed quotation as expired when it is
 * read, so this sweep exists to persist that state: it lets filters, reports
 * and the reminders digest see it without recomputing the date everywhere.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Mark quotations past their validity date as expired';

    public function handle(QuotationService $quotations): int
    {
        try {
            $count = $quotations->expireLapsed();
        } catch (\Throwable $e) {
            // Never let one bad row stop the nightly run for every tenant.
            Log::error('Quotation expiry sweep failed.', ['exception' => $e]);

            $this->error('Quotation expiry sweep failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$count} quotation(s) marked expired.");

        return self::SUCCESS;
    }
}
