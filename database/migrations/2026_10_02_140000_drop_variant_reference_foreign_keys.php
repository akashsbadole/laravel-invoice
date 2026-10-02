<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The variant columns were first added with a foreign key that nulls the
 * reference on delete. That rewrites history: an invoice line or a stock
 * movement silently forgets which size was sold or written off the moment
 * that size is retired. The reference is kept as a plain indexed column
 * instead, so the ledger and the invoice stay honest after a deletion.
 *
 * The create migration has been corrected for fresh databases; this one
 * brings an already-migrated database in line. SQLite never receives it
 * because the driver cannot drop a foreign key, and tests rebuild from
 * scratch anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (['invoice_items', 'inventory_movements'] as $table) {
            $hasForeign = DB::select(
                'select count(*) as total
                   from information_schema.key_column_usage k
                   join information_schema.referential_constraints r
                     on r.constraint_schema = k.constraint_schema
                    and r.constraint_name = k.constraint_name
                  where k.table_schema = schema()
                    and k.table_name = ?
                    and k.column_name = ?
                    and k.referenced_table_name is not null',
                [$table, 'catalog_variant_id'],
            );

            if ((int) ($hasForeign[0]->total ?? 0) > 0) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropForeign(['catalog_variant_id']);
                });
            }
        }
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreign('catalog_variant_id')
                ->references('id')
                ->on('catalog_variants')
                ->nullOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreign('catalog_variant_id')
                ->references('id')
                ->on('catalog_variants')
                ->nullOnDelete();
        });
    }
};
