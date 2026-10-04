<?php

/*
|--------------------------------------------------------------------------
| Role permissions
|--------------------------------------------------------------------------
|
| Which granular capabilities each role grants. Roles are additive — a role
| only ever holds the permissions listed here, so removing a line tightens
| access immediately without a code change.
|
| The super admin row holds the platform capabilities (managing tenants,
| plans and subscriptions). Everything else it can do inside a tenant is
| granted too, so a super admin never hits a wall.
|
*/

return [

    'super_admin' => [
        'view_dashboard', 'view_customers', 'manage_customers',
        'view_catalog', 'manage_catalog', 'manage_inventory',
        'view_quotations', 'manage_quotations',
        'view_invoices', 'create_invoices', 'edit_invoices', 'delete_invoices',
        'record_payments', 'send_messages', 'view_reports', 'view_costs',
        'manage_settings', 'manage_users',
        'manage_tenants', 'manage_plans', 'manage_subscriptions',
        'impersonate_tenants', 'view_platform_reports',
    ],

    'admin' => [
        'view_dashboard', 'view_customers', 'manage_customers', 'delete_customers',
        'view_catalog', 'manage_catalog', 'manage_inventory',
        'view_quotations', 'manage_quotations',
        'view_invoices', 'create_invoices', 'edit_invoices', 'delete_invoices',
        'record_payments', 'send_messages', 'view_reports', 'view_costs',
        'manage_settings', 'manage_users',
    ],

    // Runs the day-to-day: everything operational, nothing destructive to
    // settings or people.
    'manager' => [
        'view_dashboard', 'view_customers', 'manage_customers', 'delete_customers',
        'view_catalog', 'manage_catalog', 'manage_inventory',
        'view_quotations', 'manage_quotations',
        'view_invoices', 'create_invoices', 'edit_invoices', 'delete_invoices',
        'record_payments', 'send_messages', 'view_reports',
    ],

    // Quotes and invoices in, but no deletes and no cost visibility.
    'invoice_creator' => [
        'view_dashboard', 'view_customers', 'manage_customers',
        'view_catalog',
        'view_quotations', 'manage_quotations',
        'view_invoices', 'create_invoices', 'edit_invoices',
        'record_payments',
    ],

    // Read-only across the board.
    'viewer' => [
        'view_dashboard', 'view_customers',
        'view_catalog',
        'view_quotations', 'view_invoices', 'view_reports',
    ],

];
