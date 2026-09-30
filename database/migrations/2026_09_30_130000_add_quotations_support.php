<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_type')->default('jewelry_invoice')->index()->after('uuid');
            $table->foreignId('converted_to_id')->nullable()->after('created_by')
                ->constrained('invoices')->nullOnDelete();
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('quotation_prefix')->default('QT');
            $table->unsignedInteger('next_quotation_sequence')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['quotation_prefix', 'next_quotation_sequence']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_to_id');
            $table->dropColumn('document_type');
        });
    }
};
