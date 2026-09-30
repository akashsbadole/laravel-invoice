<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('mobile_number')->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('state_code', 2)->nullable(); // GST state code, e.g. 27 = Maharashtra
            $table->date('birthday')->nullable();
            $table->date('anniversary')->nullable();
            $table->text('notes')->nullable();
            $table->string('customer_type')->default('individual');
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['full_name', 'mobile_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
