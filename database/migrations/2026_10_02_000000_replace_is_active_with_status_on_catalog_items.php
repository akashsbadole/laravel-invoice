<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the is_active boolean with a status enum (draft|active|inactive).
     *
     * Existing active rows become 'active', existing inactive rows become
     * 'inactive'. There is no way to know which inactive rows were meant to
     * be drafts, so 'inactive' is the correct historical interpretation.
     */
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('description');
        });

        DB::table('catalog_items')
            ->where('is_active', true)
            ->update(['status' => 'active']);
        DB::table('catalog_items')
            ->where('is_active', false)
            ->update(['status' => 'inactive']);

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('status');
        });

        DB::table('catalog_items')
            ->where('status', 'active')
            ->update(['is_active' => true]);
        DB::table('catalog_items')
            ->whereIn('status', ['draft', 'inactive'])
            ->update(['is_active' => false]);

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
