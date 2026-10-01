<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'invoice_charges',
        'invoice_item_charges',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->index()->constrained()->cascadeOnDelete();
            });
        }

        // Backfill from the parent invoice's tenant. MySQL's UPDATE ... JOIN has no
        // SQLite equivalent, so this walks the invoices instead — portable
        // across both drivers and cheap at these table sizes.
        DB::table('invoices')
            ->select('id', 'tenant_id')
            ->whereNotNull('tenant_id')
            ->orderBy('id')
            ->each(function ($invoice) {
                DB::table('invoice_charges')
                    ->where('invoice_id', $invoice->id)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $invoice->tenant_id]);

                $itemIds = DB::table('invoice_items')
                    ->where('invoice_id', $invoice->id)
                    ->pluck('id');

                if ($itemIds->isNotEmpty()) {
                    DB::table('invoice_item_charges')
                        ->whereIn('invoice_item_id', $itemIds)
                        ->whereNull('tenant_id')
                        ->update(['tenant_id' => $invoice->tenant_id]);
                }
            });
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
