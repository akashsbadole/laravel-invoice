<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canDo(Permission::ViewCustomers);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->sameTenant($user, $customer->tenant_id)
            && $user->canDo(Permission::ViewCustomers);
    }

    public function create(User $user): bool
    {
        return $user->canDo(Permission::ManageCustomers);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->sameTenant($user, $customer->tenant_id)
            && $user->canDo(Permission::ManageCustomers);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->sameTenant($user, $customer->tenant_id)
            && $user->canDo(Permission::DeleteInvoices);
    }

    public function export(User $user): bool
    {
        return $user->canDo(Permission::ViewReports);
    }

    protected function sameTenant(User $user, ?int $tenantId): bool
    {
        return $tenantId !== null && $user->tenant_id === $tenantId;
    }
}
