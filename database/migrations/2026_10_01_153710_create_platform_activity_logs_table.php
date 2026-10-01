<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for platform-level (super admin) actions.
     *
     * Deliberately separate from `activity_logs`, which is tenant-scoped and
     * records what a business did inside its own workspace. A super admin has
     * no tenant, so suspending a tenant or changing a plan has nowhere to go
     * in the tenant-scoped table — and that is exactly the history an operator
     * most needs to review.
     */
    public function up(): void
    {
        Schema::create('platform_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // tenant.suspended, subscription.updated, user.deactivated,
            // tenant.impersonated, tenant.impersonation_ended, plan.updated
            $table->string('action');

            // What was acted on, e.g. the Tenant or User involved.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Which tenant the action concerned, for filtering.
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_activity_logs');
    }
};
