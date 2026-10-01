<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing mode
    |--------------------------------------------------------------------------
    |
    | 'free'  — every feature is available to every tenant, with no quotas
    |           and no subscription gate. This is the current setting.
    | 'paid'  — plans gate access: quotas apply and expired subscriptions
    |           are redirected to checkout.
    |
    | Switching to 'paid' requires no code change: the free plan is already
    | seeded, and the paid plans remain in place.
    |
    */

    'mode' => env('BILLING_MODE', 'free'),

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    'trial_plan' => env('BILLING_TRIAL_PLAN', 'starter'),

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    |
    | Shown on the public contact page. Set these per deployment so the page
    | points at the right inbox and number rather than a developer's.
    |
    */

    'support' => [
        'email' => env('SUPPORT_EMAIL', 'support@example.com'),
        'whatsapp' => env('SUPPORT_WHATSAPP'),
        'phone' => env('SUPPORT_PHONE'),
        'hours' => env('SUPPORT_HOURS', 'Monday to Saturday, 10:00 to 19:00 IST'),
    ],

];
