<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstraps the platform super admin — the one account that manages every
 * tenant. It deliberately has no tenant, so it is created outside the
 * registration flow.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'app:make-super-admin
        {email? : Email address; generated if omitted}
        {--name= : Display name}
        {--password= : Password (generated if omitted)}';

    protected $description = 'Create or promote a platform super admin account';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email address');
        $name = $this->option('name') ?: 'Super Admin';
        $password = $this->option('password') ?: Str::password(16);

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->withoutGlobalScopes()->where('email', $email)->first();

        if ($user) {
            // Promoting an existing tenant user would leave them owning a
            // tenant, so hand the ownership over to keep the platform account
            // tenant-less and unambiguous.
            $this->components->info("Promoting existing user [{$email}] to super admin.");

            if ($user->tenant_id !== null) {
                $this->components->warn(
                    'This user owns a tenant. Their tenant is left intact; they keep admin rights there via impersonation.',
                );
            }

            $user->forceFill(['role' => UserRole::SuperAdmin])->save();
        } else {
            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]);
            $user->tenant_id = null;
            $user->email_verified_at = now();
            $user->save();

            $this->components->info("Created super admin [{$email}].");
        }

        $this->newLine();
        $this->components->twoColumnDetail('Email', $email);
        $this->components->twoColumnDetail('Password', $this->option('password') ? $password : '(generated above)');

        if (! $this->option('password')) {
            $this->newLine();
            $this->line('  <fg=yellow>'.$password.'</>');
            $this->newLine();
        }

        $this->components->info('Sign in at /login to reach the admin panel.');

        return self::SUCCESS;
    }
}