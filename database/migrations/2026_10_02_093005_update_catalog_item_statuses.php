<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert any previously-created "inactive" rows to "discontinued".
     *
     * "Inactive" has been added back as a distinct temporary state, so the
     * legacy value must be renamed for historical rows.
     */
    public function up(): void
    {
        DB::table('catalog_items')
            ->where('status', 'inactive')
            ->update(['status' => 'discontinued']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('catalog_items')
            ->where('status', 'discontinued')
            ->update(['status' => 'inactive']);
    }
};
