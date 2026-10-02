<?php

namespace App\Enums;

/**
 * Granular capabilities, granted to roles through config/permissions.php.
 *
 * Controllers ask "can this user do X" rather than "is this user a Manager",
 * so a role can be reshaped in config without touching call sites.
 */
enum Permission: string
{
    case ViewDashboard = 'view_dashboard';
    case ViewCustomers = 'view_customers';
    case ManageCustomers = 'manage_customers';
    case ViewCatalog = 'view_catalog';
    case ManageCatalog = 'manage_catalog';
    case ManageInventory = 'manage_inventory';
    case ViewQuotations = 'view_quotations';
    case ManageQuotations = 'manage_quotations';
    case ViewInvoices = 'view_invoices';
    case CreateInvoices = 'create_invoices';
    case EditInvoices = 'edit_invoices';
    case DeleteInvoices = 'delete_invoices';
    case RecordPayments = 'record_payments';
    case SendMessages = 'send_messages';
    case ViewReports = 'view_reports';
    case ViewCosts = 'view_costs';
    case ManageSettings = 'manage_settings';
    case ManageUsers = 'manage_users';

    // Platform-level, granted only to the super admin.
    case ManageTenants = 'manage_tenants';
    case ManagePlans = 'manage_plans';
    case ManageSubscriptions = 'manage_subscriptions';
    case ImpersonateTenants = 'impersonate_tenants';
    case ViewPlatformReports = 'view_platform_reports';

    /**
     * Platform capabilities never apply inside a tenant, so a super admin
     * working with tenant data behaves exactly like the tenant's own staff.
     */
    public function isPlatform(): bool
    {
        return in_array($this, [
            self::ManageTenants,
            self::ManagePlans,
            self::ManageSubscriptions,
            self::ImpersonateTenants,
            self::ViewPlatformReports,
        ], true);
    }
}
