<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // The day the metal rate behind a quotation's prices was
            // struck. A quotation accepted at one rate must bill at
            // that same rate, so the date has to survive conversion
            // rather than reset to the billing day.
            $table->date('rate_locked_at')->nullable()->after('quotation_valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('rate_locked_at');
        });
    }
};
