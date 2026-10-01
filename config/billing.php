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

];