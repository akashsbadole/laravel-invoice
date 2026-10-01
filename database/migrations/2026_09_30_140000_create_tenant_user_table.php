<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        // Every existing user is a member of their current tenant with the same role.
        $rows = DB::table('users')->select(['id', 'tenant_id', 'role'])->get();

        foreach ($rows as $row) {
            if ($row->tenant_id === null) {
                continue;
            }

            DB::table('tenant_user')->insertOrIgnore([
                'tenant_id' => $row->tenant_id,
                'user_id' => $row->id,
                'role' => $row->role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
