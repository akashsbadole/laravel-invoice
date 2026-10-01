<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-form attributes for customers and invoices.
     *
     * The catalog already has this, and it is what lets a new trade be
     * onboarded without a migration: referral source on a customer, IMEI on
     * an electronics line, site reference on a contractor's invoice.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('attributes')->nullable()->after('anniversary');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->json('attributes')->nullable()->after('terms');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('attributes');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('attributes');
        });
    }
};
