<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            // One limit for the whole business: any discount above
            // this share of the bill needs an explicit approval before
            // a quotation can become an invoice. Null disables it.
            $table->decimal('discount_approval_threshold', 5, 2)->nullable()->after('quotation_followup_days');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Who OK'd the discount, when, and for how much — so the
            // approval can't be mistaken for a later, bigger discount.
            $table->foreignId('discount_approved_by')->nullable()->after('revision_note')->constrained('users')->nullOnDelete();
            $table->timestamp('discount_approved_at')->nullable()->after('discount_approved_by');
            $table->decimal('discount_approved_discount', 12, 2)->nullable()->after('discount_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            $table->dropColumn('discount_approval_threshold');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropColumn(['discount_approved_at', 'discount_approved_discount']);
        });
    }
};
