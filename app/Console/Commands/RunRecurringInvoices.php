<?php

namespace App\Console\Commands;

use App\Models\RecurringProfile;
use App\Models\Tenant;
use App\Services\InvoiceCloner;
use Illuminate\Console\Command;
use Throwable;

class RunRecurringInvoices extends Command
{
    protected $signature = 'recurring:run';

    protected $description = 'Generate invoices for due recurring profiles (all tenants)';

    public function handle(InvoiceCloner $cloner): int
    {
        $count = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            Tenant::runInContext($tenant->id, function () use ($cloner, $tenant, &$count) {
                $due = RecurringProfile::query()
                    ->with('sourceInvoice')
                    ->where('is_active', true)
                    ->whereDate('next_run_at', '<=', today())
                    ->get();

                foreach ($due as $profile) {
                    try {
                        $source = $profile->sourceInvoice;

                        if (! $source) {
                            $profile->delete();

                            continue;
                        }

                        $copy = $cloner->cloneAsNew($source, $profile->created_by ?? $tenant->users()->orderBy('id')->value('id'));
                        $profile->advance();
                        $count++;

                        $this->info("Tenant {$tenant->slug}: generated {$copy->invoice_number}.");
                    } catch (Throwable $e) {
                        report($e);
                        $this->warn("Tenant {$tenant->slug}: failed profile #{$profile->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        $this->info("{$count} recurring invoice(s) generated.");

        return self::SUCCESS;
    }
}
