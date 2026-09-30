<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\BusinessSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Plan::ensureDefaults();

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'my-jewellery-store'],
            [
                'name' => 'My Jewellery Store',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(14),
            ],
        );

        BusinessSetting::forTenant($tenant->id);

        Subscription::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => Plan::query()->where('slug', 'starter')->firstOrFail()->id,
                'status' => 'trialing',
                'trial_ends_at' => $tenant->trial_ends_at,
            ],
        );

        $admin = User::query()->where('email', 'admin@example.com')->first();

        if (! $admin) {
            $admin = new User([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);
            $admin->tenant_id = $tenant->id;
            $admin->email_verified_at = now();
            $admin->save();
        }

        $existingStaff = User::query()->where('tenant_id', $tenant->id)->where('email', '!=', 'admin@example.com')->count();

        if ($existingStaff === 0) {
            User::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        }
    }
}
