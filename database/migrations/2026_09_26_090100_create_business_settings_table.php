<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row table: the application always reads/writes id = 1.
     */
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('state_code', 2)->nullable();
            $table->string('receipt_width', 2)->default('80'); // thermal paper width in mm: 58 | 80
            $table->boolean('sms_payment_reminders')->default(false);
            $table->boolean('sms_birthday_wishes')->default(false);
            $table->json('bank_details')->nullable();
            $table->string('invoice_prefix')->default('JWL');
            $table->unsignedInteger('invoice_number_start')->default(1);
            $table->unsignedInteger('next_invoice_sequence')->default(1);
            $table->decimal('default_tax_rate', 5, 2)->default(3.00);
            $table->string('default_currency', 3)->default('INR');
            $table->text('invoice_terms')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->string('stamp_image_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
