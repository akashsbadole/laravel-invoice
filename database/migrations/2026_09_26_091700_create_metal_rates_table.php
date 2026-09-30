<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->string('metal_type');
            $table->string('purity');
            $table->date('rate_date');
            $table->decimal('rate_per_gram', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['metal_type', 'purity', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_rates');
    }
};
