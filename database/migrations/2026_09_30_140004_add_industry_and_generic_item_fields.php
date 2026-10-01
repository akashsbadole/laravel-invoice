<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('industry', 40)->default('jewelry')->after('business_name');
            $table->string('unit_label', 20)->nullable()->after('industry');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('industry', 40)->nullable()->after('name');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->after('item_code')->constrained()->nullOnDelete();

            // Generic product identity — hardware, tiles, plumbing, general.
            $table->string('brand', 100)->nullable()->after('catalog_item_id');
            $table->string('model_number', 100)->nullable()->after('brand');
            $table->string('serial_number', 100)->nullable()->after('model_number');
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('serial_number');
            $table->string('size_label', 50)->nullable()->after('warranty_months');
            $table->string('finish', 50)->nullable()->after('size_label');
            $table->string('grade', 50)->nullable()->after('finish');
            $table->string('specification', 255)->nullable()->after('grade');
            $table->string('batch_number', 100)->nullable()->after('specification');

            // Area-based pricing — tiles, marble, flooring.
            $table->decimal('length', 10, 3)->nullable()->after('batch_number');
            $table->decimal('width', 10, 3)->nullable()->after('length');
            $table->decimal('height', 10, 3)->nullable()->after('width');
            $table->decimal('wastage_percent', 5, 2)->nullable()->after('height');
            $table->decimal('boxes', 10, 2)->nullable()->after('wastage_percent');

            // Free-form attributes so a tenant can record anything its trade
            // needs without a schema change (grade, finish, voltage, ...).
            $table->json('attributes')->nullable()->after('boxes');
        });

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('name');
            $table->string('model_number', 100)->nullable()->after('item_code');
            $table->string('size_label', 50)->nullable()->after('model_number');
            $table->string('finish', 50)->nullable()->after('size_label');
            $table->string('grade', 50)->nullable()->after('finish');
            $table->string('specification', 255)->nullable()->after('grade');
            $table->string('unit_label', 20)->nullable()->after('specification');
            $table->decimal('default_length', 10, 3)->nullable()->after('unit_label');
            $table->decimal('default_width', 10, 3)->nullable()->after('default_length');
            $table->decimal('default_wastage_percent', 5, 2)->nullable()->after('default_width');
            $table->json('attributes')->nullable()->after('default_wastage_percent');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn([
                'brand', 'model_number', 'size_label', 'finish', 'grade',
                'specification', 'unit_label', 'default_length', 'default_width',
                'default_wastage_percent', 'attributes',
            ]);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
            $table->dropColumn([
                'catalog_item_id', 'brand', 'model_number', 'serial_number',
                'warranty_months', 'size_label', 'finish', 'grade', 'specification',
                'batch_number', 'length', 'width', 'height', 'wastage_percent',
                'boxes', 'attributes',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('industry');
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['industry', 'unit_label']);
        });
    }
};
