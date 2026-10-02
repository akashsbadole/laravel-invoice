<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // Chase a sent-but-unopened or about-to-lapse quotation on its own,
            // so a shop does not have to remember which quotes it is still
            // waiting on. Off by default: it messages the shop's customers.
            $table->boolean('quotation_followup_enabled')->default(false)->after('quotation_alerts_email');
            // How long to wait before the first nudge.
            $table->unsignedSmallInteger('quotation_followup_days')->default(2)->after('quotation_followup_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['quotation_followup_enabled', 'quotation_followup_days']);
        });
    }
};
