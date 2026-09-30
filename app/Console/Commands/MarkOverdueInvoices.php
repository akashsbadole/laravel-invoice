<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Flag unpaid invoices that are past their due date as overdue';

    public function handle(): int
    {
        $count = Invoice::query()
            ->where('status', InvoiceStatus::Unpaid->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('balance_amount', '>', 0)
            ->update(['status' => InvoiceStatus::Overdue->value]);

        $this->info("{$count} invoice(s) marked overdue.");

        return self::SUCCESS;
    }
}
