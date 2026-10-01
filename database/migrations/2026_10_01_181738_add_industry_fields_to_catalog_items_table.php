<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog columns for the industries added to config/industries.php.
     *
     * Furniture, textiles, electronics, paint and contractors each need a few
     * attributes a tiles or hardware catalog has no use for. Declaring them as
     * real columns (rather than leaning on the free-form `attributes` map)
     * means they are validated, filterable and CSV-importable like every other
     * catalog field.
     *
     * `batch_number` and `boxes` were already named by the tiles industry in
     * config but never existed here, so they are added in the same migration.
     */
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            // Textiles & furniture
            $table->string('fabric', 100)->nullable()->after('material');
            $table->string('weave', 50)->nullable()->after('fabric');
            $table->string('pattern', 100)->nullable()->after('weave');

            // Electronics - the serial number is what a warranty claim needs.
            $table->string('serial_number', 100)->nullable()->after('model_number');

            // Paint & coatings
            $table->string('shade_code', 50)->nullable()->after('color');
            $table->decimal('volume', 10, 3)->nullable()->after('shade_code');
            $table->decimal('coverage_area', 10, 3)->nullable()->after('volume');

            // Contractors & service work
            $table->string('service_type', 100)->nullable()->after('specification');
            $table->string('site_reference', 100)->nullable()->after('service_type');

            // Tiles already declared these in config; backfilling the columns.
            $table->string('batch_number', 100)->nullable()->after('grade');
            $table->decimal('boxes', 10, 2)->nullable()->after('batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn([
                'fabric',
                'weave',
                'pattern',
                'serial_number',
                'shade_code',
                'volume',
                'coverage_area',
                'service_type',
                'site_reference',
                'batch_number',
                'boxes',
            ]);
        });
    }
};
