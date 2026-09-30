<?php

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->default(InvoiceStatus::Unpaid->value);
            $table->string('pricing_mode')->default(PricingMode::JewelryCalculated->value);
            $table->string('tax_mode')->default('single'); // single | cgst_sgst | igst
            $table->json('tax_breakdown')->nullable();
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->foreignId('salesperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_template_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 12, 2)->default(0);

            // Sum of all item + invoice level charges, tax, and discount is
            // kept queryable via invoice_item_charges / invoice_charges;
            // this is a point-in-time snapshot for fast reads and so the
            // printed invoice never changes if a charge type is edited later.
            $table->json('charges_summary')->nullable();

            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'invoice_date']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
