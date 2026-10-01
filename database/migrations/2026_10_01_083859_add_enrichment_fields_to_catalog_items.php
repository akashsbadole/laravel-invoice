<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commercial, physical and presentation attributes that apply across
     * every industry, so one catalog schema serves all trades.
     */
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            // Commercial
            $table->decimal('cost_price', 12, 2)->nullable()->after('default_rate');
            $table->unsignedInteger('minimum_order_quantity')->default(1)->after('cost_price');
            $table->string('pack_size', 100)->nullable()->after('minimum_order_quantity');
            $table->boolean('tax_inclusive')->default(false)->after('pack_size');

            // Physical / traceability
            $table->string('barcode', 100)->nullable()->after('tax_inclusive');
            $table->string('color', 50)->nullable()->after('barcode');
            $table->string('material', 100)->nullable()->after('color');
            $table->string('thickness', 50)->nullable()->after('material');
            $table->unsignedInteger('warranty_months')->nullable()->after('thickness');
            $table->string('manufacturer', 100)->nullable()->after('warranty_months');
            $table->string('country_of_origin', 100)->nullable()->after('manufacturer');

            // Stock. Opt-in per item because made-to-order work (most jewelry)
            // has no shelf quantity to track.
            $table->boolean('stock_tracked')->default(false)->after('country_of_origin');
            $table->decimal('stock_quantity', 12, 3)->default(0)->after('stock_tracked');
            $table->decimal('reorder_level', 12, 3)->default(0)->after('stock_quantity');
            $table->string('stock_unit', 20)->nullable()->after('reorder_level');

            $table->string('image_path', 255)->nullable()->after('stock_unit');

            $table->index('barcode');
        });

        Schema::table('business_settings', function (Blueprint $table) {
            // Reveal every catalog field regardless of the tenant's industry.
            $table->boolean('show_all_catalog_fields')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('show_all_catalog_fields');
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropIndex(['barcode']);

            $table->dropColumn([
                'cost_price', 'minimum_order_quantity', 'pack_size', 'tax_inclusive',
                'barcode', 'color', 'material', 'thickness', 'warranty_months',
                'manufacturer', 'country_of_origin',
                'stock_tracked', 'stock_quantity', 'reorder_level', 'stock_unit',
                'image_path',
            ]);
        });
    }
};