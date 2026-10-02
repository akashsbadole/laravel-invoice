<?php

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            // Consumed portion: amount - applied_amount stays available to
            // settle any later invoice for this customer.
            $table->decimal('applied_amount', 12, 2)->default(0);
            $table->date('advance_date');
            $table->string('payment_method')->default(PaymentMethod::Cash->value);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            // available | applied | refunded
            $table->string('status')->default('available');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_advances');
    }
};
