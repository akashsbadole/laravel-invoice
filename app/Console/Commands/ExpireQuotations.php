<?php

namespace App\Console\Commands;

use App\Models\Tenant;
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
        $expired = 0;

        try {
            // Run per tenant so every expiry event is stamped with the tenant it
            // belongs to. Without a tenant context TenantScope is a no-op and the
            // events land with tenant_id = NULL, invisible to the tenant's feed.
            foreach (Tenant::query()->get() as $tenant) {
                $expired += Tenant::runInContext(
                    $tenant->id,
                    fn (): int => $quotations->expireLapsed(),
                );
            }
        } catch (\Throwable $e) {
            // Never let one bad row stop the nightly run for every tenant.
            Log::error('Quotation expiry sweep failed.', ['exception' => $e]);

            $this->error('Quotation expiry sweep failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$expired} quotation(s) marked expired.");

        return self::SUCCESS;
    }
}
