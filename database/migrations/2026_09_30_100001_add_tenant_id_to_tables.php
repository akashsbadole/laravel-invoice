<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'users',
        'business_settings',
        'invoice_templates',
        'customers',
        'customer_notes',
        'customer_followups',
        'invoices',
        'invoice_items',
        'payments',
        'invoice_share_links',
        'invoice_events',
        'reminders',
        'message_logs',
        'activity_logs',
        'catalog_items',
        'charge_types',
        'metal_rates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->index()->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('business_settings', function (Blueprint $table) {
            $table->unique('tenant_id');
        });

        // Backfill: everything created so far belongs to the default tenant.
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'My Jewellery Store',
            'slug' => 'my-jewellery-store',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id']);
        });

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        Schema::dropIfExists('tenants');
    }
};
