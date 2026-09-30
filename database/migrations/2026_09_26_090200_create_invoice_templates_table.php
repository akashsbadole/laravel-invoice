<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_default')->default(false);
            $table->json('layout_config')->nullable();
            $table->timestamps();
        });

        // See App\Models\InvoiceTemplate::defaultLayoutConfig() for the
        // meaning of each key — kept in sync with the PDF/public views.
        DB::table('invoice_templates')->insert([
            'name' => 'Default',
            'slug' => 'default',
            'is_default' => true,
            'layout_config' => json_encode([
                'accent_color' => '#0f172a',
                'header_alignment' => 'left',
                'show_huid' => true,
                'show_hsn' => true,
                'show_stone_details' => true,
                'show_bank_details' => true,
                'show_signature' => true,
                'show_stamp' => true,
                'show_qr_code' => true,
                'footer_note' => '',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
