<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only ledger behind catalog_items.stock_quantity.
     *
     * The catalog row holds the current on-hand figure for fast reads; every
     * change is also recorded here so the balance can be explained and
     * corrected rather than silently drifting.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // in | out | adjustment
            $table->string('type');
            // Signed delta applied to the on-hand quantity.
            $table->decimal('quantity', 12, 3);
            // On-hand after the movement, captured so history is readable
            // without replaying the whole ledger.
            $table->decimal('balance_after', 12, 3);

            $table->string('reason', 100)->nullable();
            $table->text('note')->nullable();

            // Optional link back to whatever caused the movement.
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['catalog_item_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};