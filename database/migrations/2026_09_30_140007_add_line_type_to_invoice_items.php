<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // 'sale' bills the customer; 'exchange_credit' is old gold (or
            // scrap) handed in against the invoice, so it reduces what is
            // owed and is never a taxable supply.
            $table->string('line_type', 20)->default('sale')->after('item_name');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('line_type');
        });
    }
};
