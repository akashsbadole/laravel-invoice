<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            // A customer tapping "Accept" on a shared link used to silently
            // create a sales invoice here — with no owner present, no rate
            // re-check and no payment taken. Acceptance now only records the
            // decision; staff convert at the counter, so the flag is dead.
            $table->dropColumn('quotation_auto_convert');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            $table->boolean('quotation_auto_convert')->default(false)->after('quotation_followup_days');
        });
    }
};
