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

        // Backfill from the parent invoice's tenant.
        DB::statement('UPDATE invoice_charges ic JOIN invoices i ON i.id = ic.invoice_id SET ic.tenant_id = i.tenant_id WHERE ic.tenant_id IS NULL');
        DB::statement('UPDATE invoice_item_charges iic JOIN invoice_items ii ON ii.id = iic.invoice_item_id JOIN invoices i ON i.id = ii.invoice_id SET iic.tenant_id = i.tenant_id WHERE iic.tenant_id IS NULL');
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
