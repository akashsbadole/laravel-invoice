<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credit and debit notes are documents in their own right (own number,
     * own PDF) but only exist because of the invoice they correct, so the
     * link lives on the note rather than on the invoice.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('parent_invoice_id')->nullable()->after('converted_to_id')
                ->constrained('invoices')->nullOnDelete();
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('credit_note_prefix', 10)->default('CN');
            $table->unsignedInteger('next_credit_note_sequence')->default(1);
            $table->string('debit_note_prefix', 10)->default('DN');
            $table->unsignedInteger('next_debit_note_sequence')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'credit_note_prefix', 'next_credit_note_sequence',
                'debit_note_prefix', 'next_debit_note_sequence',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_invoice_id');
        });
    }
};
