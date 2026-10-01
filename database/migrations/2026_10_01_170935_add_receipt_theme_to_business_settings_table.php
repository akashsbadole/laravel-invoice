<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thermal receipts previously had width as their only setting, so a brand
     * logo or accent colour on an invoice never carried through to the 58/80 mm
     * slips customers keep. These columns let a tenant theme both.
     */
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // Hex accent used for the header rule, totals and the toolbar.
            $table->string('receipt_accent_color', 7)->default('#0F172A')->after('receipt_width');
            $table->boolean('receipt_show_logo')->default(true)->after('receipt_accent_color');
            $table->boolean('receipt_show_signature')->default(false)->after('receipt_show_logo');
            $table->boolean('receipt_show_stamp')->default(false)->after('receipt_show_signature');
            $table->boolean('receipt_show_gstin')->default(true)->after('receipt_show_stamp');
            $table->string('receipt_footer', 255)->nullable()->after('receipt_show_gstin');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_accent_color',
                'receipt_show_logo',
                'receipt_show_signature',
                'receipt_show_stamp',
                'receipt_show_gstin',
                'receipt_footer',
            ]);
        });
    }
};
