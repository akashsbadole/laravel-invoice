<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canDo(Permission::ViewInvoices);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id)
            && $user->canDo(Permission::ViewInvoices);
    }

    public function create(User $user): bool
    {
        return $user->canDo(Permission::CreateInvoices);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->sameTenant($user, $invoice->tenant_id)) {
            return false;
        }

        // Reopening a cancelled invoice changes the ledger, so it needs the
        // same right as deleting one.
        if ($invoice->status->value === 'cancelled') {
            return $user->canDo(Permission::DeleteInvoices);
        }

        return $user->canDo(Permission::EditInvoices);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id)
            && $user->canDo(Permission::DeleteInvoices);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id)
            && $user->canDo(Permission::EditInvoices);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id)
            && $user->canDo(Permission::RecordPayments)
            && $invoice->document_type->isPayable();
    }

    public function share(User $user, Invoice $invoice): bool
    {
        return $this->sameTenant($user, $invoice->tenant_id)
            && $user->canDo(Permission::SendMessages);
    }

    protected function sameTenant(User $user, ?int $tenantId): bool
    {
        return $tenantId !== null && $user->tenant_id === $tenantId;
    }
}
