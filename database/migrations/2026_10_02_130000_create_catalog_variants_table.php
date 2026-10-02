<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->string('label', 100);
            // Nullable: a shop can track SKU per variant or not at all, and
            // NULLs stay non-colliding under the unique index in MySQL/SQLite.
            $table->string('item_code', 100)->nullable();
            $table->json('attributes')->nullable();
            // Price override; null means "use the product's default rate".
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('stock_quantity', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'item_code']);
            $table->index('catalog_item_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            // Deliberately no foreign key: an invoice is a snapshot of what
            // was sold, so a NULLing or cascading constraint would rewrite
            // history the moment a size was retired. The id is kept as a
            // plain reference and the relation resolves to null if the row
            // is gone.
            $table->unsignedBigInteger('catalog_variant_id')->nullable();
            $table->index('catalog_variant_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_variant_id')
                ->nullable()
                ->after('catalog_item_id');
            $table->index('catalog_variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['catalog_variant_id']);
            $table->dropColumn('catalog_variant_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['catalog_variant_id']);
            $table->dropColumn('catalog_variant_id');
        });

        Schema::dropIfExists('catalog_variants');
    }
};
