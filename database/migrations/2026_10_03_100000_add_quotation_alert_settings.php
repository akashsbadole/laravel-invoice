<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // Tell staff the moment a customer opens, accepts or declines a
            // shared quotation. Defaults on: a shop that never learns its quote
            // was answered is the exact pain this feature exists to remove.
            $table->boolean('quotation_alerts_owner')->default(true)->after('quotation_show_updates');
            // A second copy by email, for an owner who is not sitting in the
            // app. Off by default so enabling it is a deliberate choice.
            $table->boolean('quotation_alerts_email')->default(false)->after('quotation_alerts_owner');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['quotation_alerts_owner', 'quotation_alerts_email']);
        });
    }
};
