<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformActivityLog;
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

        PlatformActivityLog::record(
            $suspended ? 'tenant.reactivated' : 'tenant.suspended',
            $tenant,
            $suspended
                ? "Reactivated {$tenant->name}"
                : "Suspended {$tenant->name} — its staff can no longer sign in",
        );

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

        $before = [
            'plan_id' => $subscription->exists ? $subscription->plan_id : null,
            'status' => $subscription->exists ? $subscription->status : null,
        ];

        $subscription->fill([
            'plan_id' => $validated['plan_id'],
            'status' => $validated['status'],
            'trial_ends_at' => $validated['trial_ends_at'] ?? null,
            'current_period_ends_at' => $validated['current_period_ends_at'] ?? null,
        ])->save();

        PlatformActivityLog::record(
            'subscription.updated',
            $tenant,
            sprintf(
                '%s moved to %s (%s)',
                $tenant->name,
                Plan::query()->findOrFail($validated['plan_id'])->name,
                $validated['status'],
            ),
            ['tenant_id' => $tenant->id, 'from' => $before, 'to' => [
                'plan_id' => (int) $validated['plan_id'],
                'status' => $validated['status'],
            ]],
        );

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

        $activating = ! $user->is_active;

        $user->update(['is_active' => $activating]);

        PlatformActivityLog::record(
            $activating ? 'user.activated' : 'user.deactivated',
            $user,
            ($activating ? 'Reactivated ' : 'Deactivated ').$user->name.' ('.$user->email.')',
            ['tenant_id' => $user->tenant_id],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    public function plans(Request $request): Response
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
     * Edit a plan's commercial terms and visibility.
     */
    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'max_staff' => ['required', 'integer', 'min:1'],
            // Blank means unlimited, which is how the column stores it.
            'max_invoices_per_month' => ['nullable', 'integer', 'min:1'],
            // Deliberately not `required`: an unchecked checkbox is simply
            // absent from the payload, which must mean "hide this plan".
            'is_active' => ['boolean'],
        ]);

        $isActive = $request->boolean('is_active');

        // The free plan is what every tenant falls back to, so it must stay
        // active and stay free.
        abort_if(
            $plan->isFree() && (! $isActive || (float) $validated['price'] > 0),
            422,
            'The free plan must stay active and free.',
        );

        $before = ['price' => $plan->price, 'max_staff' => $plan->max_staff, 'is_active' => $plan->is_active];

        $plan->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'max_staff' => $validated['max_staff'],
            'max_invoices_per_month' => $validated['max_invoices_per_month'] ?? null,
            'is_active' => $isActive,
        ]);

        PlatformActivityLog::record(
            'plan.updated',
            $plan,
            "Updated the {$plan->name} plan",
            ['tenant_id' => null, 'from' => $before, 'to' => [
                'price' => (float) $validated['price'],
                'max_staff' => (int) $validated['max_staff'],
                'is_active' => $isActive,
            ]],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return back();
    }

    /**
     * The platform audit trail.
     */
    public function activity(Request $request): Response
    {
        $action = $request->string('action')->toString();

        return Inertia::render('admin/activity', [
            'entries' => PlatformActivityLog::query()
                ->with(['actor', 'tenant'])
                ->when($action, fn ($q) => $q->where('action', $action))
                ->latest('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (PlatformActivityLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'action_label' => $log->actionLabel(),
                    'description' => $log->description,
                    'actor' => $log->actor?->name ?? 'System',
                    'tenant' => $log->tenant?->name,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->diffForHumans(),
                ]),
            'filters' => ['action' => $action],
            'actions' => PlatformActivityLog::actionLabels(),
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
            ->orderByRaw('CASE WHEN role = ? THEN 0 WHEN role = ? THEN 1 ELSE 2 END', [
                UserRole::Admin->value,
                UserRole::Manager->value,
            ])
            ->first();

        abort_unless($target, 422, 'This tenant has no active staff member to sign in as.');

        $superAdminId = $request->user()->id;

        DB::transaction(function () use ($request, $tenant, $target, $superAdminId): void {
            $request->session()->put('impersonating', [
                'super_admin_id' => $superAdminId,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'user_id' => $target->id,
                'user_name' => $target->name,
            ]);
        });

        // Logged before the auth switch, so the actor is the super admin
        // rather than the tenant user we are about to become.
        PlatformActivityLog::create([
            'user_id' => $superAdminId,
            'action' => 'tenant.impersonated',
            'subject_type' => $tenant->getMorphClass(),
            'subject_id' => $tenant->id,
            'tenant_id' => $tenant->id,
            'description' => "Viewing {$tenant->name} as {$target->name}",
            'properties' => ['target_user_id' => $target->id, 'target_user' => $target->name],
            'ip_address' => $request->ip(),
        ]);

        // The acting admin stays signed in; the tenant context is what changes.
        auth()->login($target);
        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You are now viewing :tenant as :user.', [
                'tenant' => $tenant->name,
                'user' => $target->name,
            ]),
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Ending an impersonation session.
     *
     * This deliberately sits outside EnsureSuperAdmin: while impersonating,
     * the signed-in user is the tenant's staff, so the super-admin gate would
     * lock the operator out of the only route that can undo it.
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $session = $request->session()->pull('impersonating');

        if ($session) {
            $superAdmin = User::query()->withoutGlobalScopes()->find($session['super_admin_id']);

            if ($superAdmin) {
                auth()->login($superAdmin);
                $request->session()->regenerate();
            }

            // Recorded after the auth switch back, so the actor resolves to the
            // super admin even though auth() is now the tenant user again.
            PlatformActivityLog::create([
                'user_id' => $session['super_admin_id'],
                'action' => 'tenant.impersonation_ended',
                'subject_type' => (new Tenant)->getMorphClass(),
                'subject_id' => $session['tenant_id'],
                'tenant_id' => $session['tenant_id'],
                'description' => "Stopped viewing {$session['tenant_name']}",
                'properties' => ['impersonated_user' => $session['user_name']],
                'ip_address' => $request->ip(),
            ]);
        }

        return redirect()->route('admin.dashboard');
    }
}
