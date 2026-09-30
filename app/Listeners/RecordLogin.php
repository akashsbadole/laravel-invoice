<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Auto-discovered by Laravel (typed handle() on app/Listeners).
 */
class RecordLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'auth.login',
            'description' => "{$user->name} signed in",
            'ip_address' => request()?->ip(),
        ]);
    }
}
