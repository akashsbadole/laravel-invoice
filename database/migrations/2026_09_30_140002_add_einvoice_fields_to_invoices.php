<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('einvoice_status')->default('not_required');
            $table->string('irn', 64)->nullable()->unique();
            $table->string('irn_ack_no')->nullable();
            $table->timestamp('irn_ack_date')->nullable();
            $table->string('eway_bill_no', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['irn']);
            $table->dropColumn(['einvoice_status', 'irn', 'irn_ack_no', 'irn_ack_date', 'eway_bill_no']);
        });
    }
};
