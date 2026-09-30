<?php

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'starter'),
];
