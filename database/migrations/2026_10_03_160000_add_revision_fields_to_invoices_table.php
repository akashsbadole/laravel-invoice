<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // One number per real change: the customer should always
            // be able to see *which* version of a quotation they are
            // looking at. Starts at 1; draft edits do not advance it.
            $table->unsignedInteger('revision_number')->default(1)->after('rate_locked_at');
            // The one-line reason for this revision, e.g. "Customer
            // asked for 20g instead of 15g". Plates "why did the
            // total change" on every later view.
            $table->string('revision_note')->nullable()->after('revision_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['revision_number', 'revision_note']);
        });
    }
};
