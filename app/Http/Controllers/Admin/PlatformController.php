<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform view: every tenant, every user, the plans and the money.
 *
 * These queries deliberately bypass the tenant scope — a super admin is not
 * inside any tenant, so scoping would silently hide the data they exist to
 * manage.
 */
class PlatformController extends Controller
{
    public function dashboard(): Response
    {
        $tenants = Tenant::query();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'tenants' => (clone $tenants)->count(),
                'active_tenants' => (clone $tenants)->where('status', 'active')->count(),
                'suspended_tenants' => (clone $tenants)->where('status', 'suspended')->count(),
                'users' => User::query()->withoutGlobalScopes()->count(),
                'super_admins' => User::query()->withoutGlobalScopes()
                    ->where('role', UserRole::SuperAdmin->value)->count(),
                'invoices' => Invoice::query()->withoutGlobalScopes()->count(),
                'customers' => Customer::query()->withoutGlobalScopes()->count(),
                'on_free_plan' => Subscription::query()
                    ->whereHas('plan', fn ($q) => $q->where('slug', Plan::FREE_SLUG))
                    ->count(),
            ],
            'recentTenants' => Tenant::query()
                ->withCount('users')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'industry' => $tenant->industry,
                    'status' => $tenant->status,
                    'users_count' => $tenant->users_count,
                    'created_at' => $tenant->created_at?->format('d M Y'),
                ]),
            'plans' => Plan::query()->orderBy('sort_order')->get()
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'price' => $plan->price,
                    'is_free' => $plan->isFree(),
                    'is_active' => $plan->is_active,
                    'subscribers' => $plan->subscriptions()->count(),
                ]),
        ]);
    }

    public function tenants(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $industry = $request->string('industry')->toString();

        return Inertia::render('admin/tenants', [
            'tenants' => Tenant::query()
                ->with(['subscription.plan'])
                ->withCount('users')
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%"))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($industry, fn ($q) => $q->where('industry', $industry))
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'industry' => $tenant->industry,
                    'industry_label' => Industry::label($tenant->industry),
                    'status' => $tenant->status,
                    'users_count' => $tenant->users_count,
                    'plan' => $tenant->subscription?->plan?->name,
                    'plan_is_free' => (bool) $tenant->subscription?->plan?->isFree(),
                    'subscription_status' => $tenant->subscription?->status,
                    'created_at' => $tenant->created_at?->format('d M Y'),
                ]),
            'filters' => ['search' => $search, 'status' => $status, 'industry' => $industry],
            'statuses' => ['active', 'suspended'],
            'industries' => collect(Industry::all())
                ->map(fn (array $config, string $key) => ['key' => $key, 'label' => $config['label']])
                ->values()
                ->all(),
        ]);
    }

    public function showTenant(Tenant $tenant): Response
    {
        $tenant->load(['subscription.plan', 'businessSetting']);
        $tenant->loadCount(['users', 'invoices']);

        return Inertia::render('admin/tenant-show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'industry' => $tenant->industry,
                'industry_label' => Industry::label($tenant->industry),
                'status' => $tenant->status,
                'trial_ends_at' => $tenant->trial_ends_at?->format('d M Y'),
                'created_at' => $tenant->created_at?->format('d M Y'),
                'users_count' => $tenant->users_count,
                'invoices_count' => $tenant->invoices_count,
                'business' => $tenant->businessSetting ? [
                    'business_name' => $tenant->businessSetting->business_name,
                    'phone' => $tenant->businessSetting->phone,
                    'email' => $tenant->businessSetting->email,
                    'tax_number' => $tenant->businessSetting->tax_number,
                    'gstin' => $tenant->businessSetting->gstin ?? null,
                ] : null,
                'subscription' => $tenant->subscription ? [
                    'id' => $tenant->subscription->id,
                    'status' => $tenant->subscription->status,
                    'plan_id' => $tenant->subscription->plan_id,
                    'plan_name' => $tenant->subscription->plan?->name,
                    'plan_is_free' => (bool) $tenant->subscription->plan?->isFree(),
                    'trial_ends_at' => $tenant->subscription->trial_ends_at?->format('d M Y'),
                    'current_period_ends_at' => $tenant->subscription->current_period_ends_at?->format('d M Y'),
                ] : null,
            ],
            'users' => User::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'role_label' => $user->role?->label(),
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at?->diffForHumans(),
                ]),
            'plans' => Plan::query()->orderBy('sort_order')->get()
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'is_free' => $plan->isFree(),
                ]),
            'invoiceStats' => $this->invoiceStats($tenant),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    protected function invoiceStats(Tenant $tenant): array
    {
        $invoices = Invoice::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id);

        return [
            'count' => (clone $invoices)->count(),
            'billed' => (float) (clone $invoices)->sum('grand_total'),
            'outstanding' => (float) (clone $invoices)->sum('balance_amount'),
            'this_month' => (clone $invoices)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];
    }

    public function toggleTenantStatus(Tenant $tenant): RedirectResponse
    {
        $suspended = $tenant->status === 'suspended';

        $tenant->update(['status' => $suspended ? 'active' : 'suspended']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $suspended
                ? __(':name reactivated.', ['name' => $tenant->name])
                : __(':name suspended. Its staff can no longer sign in.', ['name' => $tenant->name]),
        ]);

        return back();
    }

    public function updateSubscription(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['required', 'in:trialing,active,past_due,cancelled'],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
        ]);

        $subscription = Subscription::query()->firstOrNew(['tenant_id' => $tenant->id]);

        $subscription->fill([
            'plan_id' => $validated['plan_id'],
            'status' => $validated['status'],
            'trial_ends_at' => $validated['trial_ends_at'] ?? null,
            'current_period_ends_at' => $validated['current_period_ends_at'] ?? null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription updated.')]);

        return back();
    }

    public function users(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();

        return Inertia::render('admin/users', [
            'users' => User::query()->withoutGlobalScopes()
                ->with('tenant')
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->when($role, fn ($q) => $q->where('role', $role))
                ->when($status === 'active', fn ($q) => $q->where('is_active', true))
                ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'role_label' => $user->role?->label(),
                    'is_active' => $user->is_active,
                    'tenant' => $user->tenant?->name,
                    'last_login_at' => $user->last_login_at?->diffForHumans(),
                ]),
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
            'roles' => collect(UserRole::cases())
                ->map(fn (UserRole $case) => ['value' => $case->value, 'label' => $case->label()])
                ->all(),
        ]);
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 422, 'Super admins cannot be deactivated from here.');

        $user->update(['is_active' => ! $user->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    public function plans(): Response
    {
        return Inertia::render('admin/plans', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->get()
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'interval' => $plan->interval,
                    'max_staff' => $plan->max_staff,
                    'max_invoices_per_month' => $plan->max_invoices_per_month,
                    'features' => $plan->features,
                    'is_active' => $plan->is_active,
                    'is_free' => $plan->isFree(),
                    'subscribers' => $plan->subscriptions_count,
                ]),
            'canManage' => $request->user()->canDo(Permission::ManagePlans),
        ]);
    }

    /**
     * Support flow: act as a tenant admin so you can reproduce their problem.
     * Recorded on the impersonating admin so the trail survives the session.
     */
    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->isActive(), 422, 'Reactivate the tenant before impersonating.');

        $target = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN role = ? THEN 0 WHEN role = ? THEN 1 ELSE 2 END", [
                UserRole::Admin->value,
                UserRole::Manager->value,
            ])
            ->first();

        abort_unless($target, 422, 'This tenant has no active staff member to sign in as.');

        DB::transaction(function () use ($request, $tenant, $target): void {
            $request->session()->put('impersonating', [
                'super_admin_id' => $request->user()->id,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'user_id' => $target->id,
                'user_name' => $target->name,
            ]);
        });

        // The acting admin stays signed in; the tenant context is what changes.
        auth()->login($target);
        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("You are now viewing :tenant as :user.", [
                'tenant' => $tenant->name,
                'user' => $target->name,
            ]),
        ]);

        return redirect()->route('dashboard');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $session = $request->session()->pull('impersonating');

        if ($session) {
            $superAdmin = User::query()->withoutGlobalScopes()->find($session['super_admin_id']);

            if ($superAdmin) {
                auth()->login($superAdmin);
                $request->session()->regenerate();
            }
        }

        return redirect()->route('admin.dashboard');
    }
}