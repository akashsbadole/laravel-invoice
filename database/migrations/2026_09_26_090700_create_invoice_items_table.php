<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('item_code')->nullable();
            $table->string('hsn_code')->nullable();

            // Jewelry-specific identification
            $table->string('metal_type')->nullable(); // gold, silver, platinum, diamond...
            $table->string('purity')->nullable(); // 22K, 18K, 925, etc.
            $table->string('huid_number')->nullable(); // BIS Hallmark Unique ID for gold jewelry

            // Stone / gem details (for studded jewelry or loose stones)
            $table->string('stone_clarity')->nullable();
            $table->string('stone_color')->nullable();
            $table->decimal('stone_carat', 8, 3)->default(0);
            $table->string('certificate_number')->nullable(); // GIA/IGI etc.

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('gross_weight', 10, 3)->default(0);
            $table->decimal('net_weight', 10, 3)->default(0);
            $table->decimal('stone_weight', 10, 3)->default(0);

            // How `rate` is applied to compute the base value of one unit —
            // see App\Enums\RateType and InvoiceCalculationService.
            $table->string('rate_type')->default('per_gram');
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('base_value', 12, 2)->default(0);

            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
