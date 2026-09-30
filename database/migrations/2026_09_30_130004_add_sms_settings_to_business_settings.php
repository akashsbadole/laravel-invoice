<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('sms_driver')->default('log');
            $table->string('sms_country_code')->default('91');
            $table->string('sms_twilio_sid')->nullable();
            $table->string('sms_twilio_token')->nullable();
            $table->string('sms_twilio_from')->nullable();
            $table->string('sms_http_url')->nullable();
            $table->string('sms_http_token')->nullable();
            $table->string('sms_http_to_field')->default('to');
            $table->string('sms_http_message_field')->default('message');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sms_driver', 'sms_country_code',
                'sms_twilio_sid', 'sms_twilio_token', 'sms_twilio_from',
                'sms_http_url', 'sms_http_token', 'sms_http_to_field', 'sms_http_message_field',
            ]);
        });
    }
};
