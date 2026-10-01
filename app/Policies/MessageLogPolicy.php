<?php

namespace App\Policies;

use App\Models\MessageLog;
use App\Models\User;

class MessageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function view(User $user, MessageLog $log): bool
    {
        return $user->tenant_id === $log->tenant_id;
    }
}
