<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // all authenticated roles can browse customers
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->role->canWrite();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function export(User $user): bool
    {
        return $user->role->canWrite();
    }
}
