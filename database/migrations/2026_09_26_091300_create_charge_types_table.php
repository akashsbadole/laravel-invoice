<?php

use App\Enums\ChargeAppliesTo;
use App\Enums\ChargeCalculationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('calculation_type')->default(ChargeCalculationType::Fixed->value);
            $table->string('applies_to')->default(ChargeAppliesTo::Item->value);
            $table->decimal('default_rate', 12, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Sensible jewelry-store defaults so invoicing works immediately.
        // `is_system` ones can be renamed/deactivated but not deleted —
        // enforced in the model, not the schema, so a shop can still
        // fully customise labels and rates.
        $now = now();

        DB::table('charge_types')->insert([
            [
                'name' => 'Making Charge', 'code' => 'making_charge',
                'calculation_type' => ChargeCalculationType::Percentage->value,
                'applies_to' => ChargeAppliesTo::Item->value,
                'default_rate' => 10, 'is_taxable' => true, 'is_system' => true,
                'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Wastage', 'code' => 'wastage_charge',
                'calculation_type' => ChargeCalculationType::Percentage->value,
                'applies_to' => ChargeAppliesTo::Item->value,
                'default_rate' => 5, 'is_taxable' => true, 'is_system' => true,
                'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Stone Charge', 'code' => 'stone_charge',
                'calculation_type' => ChargeCalculationType::Fixed->value,
                'applies_to' => ChargeAppliesTo::Item->value,
                'default_rate' => 0, 'is_taxable' => true, 'is_system' => true,
                'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Hallmarking Charge', 'code' => 'hallmarking_charge',
                'calculation_type' => ChargeCalculationType::Fixed->value,
                'applies_to' => ChargeAppliesTo::Item->value,
                'default_rate' => 45, 'is_taxable' => true, 'is_system' => false,
                'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Certification Charge', 'code' => 'certification_charge',
                'calculation_type' => ChargeCalculationType::Fixed->value,
                'applies_to' => ChargeAppliesTo::Item->value,
                'default_rate' => 0, 'is_taxable' => false, 'is_system' => false,
                'is_active' => false, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Polish / Rhodium Charge', 'code' => 'polish_charge',
                'calculation_type' => ChargeCalculationType::Fixed->value,
                'applies_to' => ChargeAppliesTo::Item->value,
                'default_rate' => 0, 'is_taxable' => true, 'is_system' => false,
                'is_active' => false, 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Other Charge', 'code' => 'other_charge',
                'calculation_type' => ChargeCalculationType::Fixed->value,
                'applies_to' => ChargeAppliesTo::Item->value,
                'default_rate' => 0, 'is_taxable' => true, 'is_system' => true,
                'is_active' => true, 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Packing Charge', 'code' => 'packing_charge',
                'calculation_type' => ChargeCalculationType::Fixed->value,
                'applies_to' => ChargeAppliesTo::Invoice->value,
                'default_rate' => 0, 'is_taxable' => true, 'is_system' => false,
                'is_active' => false, 'sort_order' => 8, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'Delivery Charge', 'code' => 'delivery_charge',
                'calculation_type' => ChargeCalculationType::Fixed->value,
                'applies_to' => ChargeAppliesTo::Invoice->value,
                'default_rate' => 0, 'is_taxable' => true, 'is_system' => false,
                'is_active' => false, 'sort_order' => 9, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_types');
    }
};
