<?php

namespace App\Http\Middleware;

use App\Models\BusinessSetting;
use App\Support\Industry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
                'firms' => fn () => $request->user()
                    ? $request->user()->firms()->orderBy('tenants.name')->get(['tenants.id', 'tenants.name', 'tenants.slug'])->values()
                    : [],
                'tenant' => function () use ($request) {
                    $user = $request->user();

                    if (! $user || ! $user->tenant) {
                        return null;
                    }

                    $tenant = $user->tenant;
                    $subscription = $tenant->subscription;

                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'status' => $tenant->status,
                        'trial_ends_at' => $tenant->trial_ends_at,
                        'subscription' => $subscription ? [
                            'status' => $subscription->status,
                            'plan_name' => $subscription->plan?->name,
                            'trial_ends_at' => $subscription->trial_ends_at,
                            'current_period_ends_at' => $subscription->current_period_ends_at,
                        ] : null,
                    ];
                },
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'toast' => fn () => $request->session()->get('toast'),
                'status' => fn () => $request->session()->get('status'),
            ],
            // What this tenant's trade can actually use — mirrors
            // EnsureIndustryAllows so nav and routes stay in sync.
            'capabilities' => fn (): array => $request->user()
                ? [
                    'metal_rates' => Industry::usesMetalRates($this->industryKey($request)),
                ]
                : [],
        ]);
    }

    protected function industryKey(Request $request): ?string
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return null;
        }

        return BusinessSetting::forTenant($tenantId)->industryKey();
    }
}
