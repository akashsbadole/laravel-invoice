<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // nearest_rupee (default) | two_decimals
            $table->string('rounding_mode')->default('nearest_rupee');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tds_rate', 5, 2)->default(0);
            $table->decimal('tds_amount', 12, 2)->default(0);
            $table->decimal('tcs_rate', 5, 2)->default(0);
            $table->decimal('tcs_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('rounding_mode');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tds_rate', 'tds_amount', 'tcs_rate', 'tcs_amount']);
        });
    }
};
