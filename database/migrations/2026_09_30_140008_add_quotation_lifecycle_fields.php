<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Quotation lifecycle — null for sales invoices.
            $table->string('quotation_status', 20)->nullable()->after('converted_to_id');
            $table->text('quotation_response')->nullable()->after('quotation_status');
            $table->timestamp('quotation_responded_at')->nullable()->after('quotation_response');
            $table->date('quotation_valid_until')->nullable()->after('quotation_responded_at');
        });

        Schema::table('business_settings', function (Blueprint $table) {
            // Whether customers may accept/reject a shared quotation.
            $table->boolean('quotation_customer_decisions')->default(false)->after('sms_anniversary_wishes');
            $table->boolean('quotation_show_updates')->default(true)->after('quotation_customer_decisions');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['quotation_customer_decisions', 'quotation_show_updates']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'quotation_status',
                'quotation_response',
                'quotation_responded_at',
                'quotation_valid_until',
            ]);
        });
    }
};
