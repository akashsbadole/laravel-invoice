<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A reusable design/item catalog to speed up invoice entry — this is
     * NOT stock/inventory (no quantity-on-hand is tracked, deliberately
     * out of scope). Selecting a catalog item on an invoice just pre-fills
     * its fields; actual weight/quantity/charges are still set per line.
     */
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('item_code')->nullable()->unique();
            $table->string('hsn_code')->nullable();
            $table->string('metal_type')->nullable();
            $table->string('purity')->nullable();
            $table->string('rate_type')->default('per_gram');
            $table->decimal('default_rate', 12, 2)->nullable();
            $table->decimal('default_net_weight', 10, 3)->nullable();
            $table->decimal('default_gross_weight', 10, 3)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
