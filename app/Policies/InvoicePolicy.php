<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->sameTenant($user, $invoice->tenant_id)) {
            return false;
        }

        if ($invoice->status->value === 'cancelled') {
            return $user->role === UserRole::Admin;
        }

        return $user->role->canWrite();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id) && $user->role === UserRole::Admin;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id) && $user->role->canWrite();
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id) && $user->role->canWrite();
    }

    public function share(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id) && $user->role->canWrite();
    }

    protected function sameTenant(User $user, ?int $tenantId): bool
    {
        return $tenantId !== null && $user->tenant_id === $tenantId;
    }
}
