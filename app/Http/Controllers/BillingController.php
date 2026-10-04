<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Mail\SubscriptionReceiptMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly RazorpayService $razorpay,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->canDo(Permission::ManageUsers), 403);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $subscription = $this->subscriptions->subscriptionFor($tenant);
        $subscription?->load('plan');

        // While the product is free there is nothing to buy, so the page
        // reports current usage instead of inviting a purchase. Paid plans
        // are not shown as available upgrades in this mode.
        $freeMode = $this->subscriptions->isFreeMode();

        return Inertia::render('billing', [
            'freeMode' => $freeMode,
            'plans' => $freeMode
                ? Plan::query()->where('slug', Plan::FREE_SLUG)->get()
                : Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'subscription' => $subscription,
            'trialDaysLeft' => $subscription && $subscription->isTrialing()
                ? now()->diffInDays($subscription->trial_ends_at)
                : 0,
            'usage' => [
                'staff' => $tenant->users()->count(),
                'invoicesThisMonth' => $tenant->invoices()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'gatewayConfigured' => $this->razorpay->configured(),
            'razorpayKeyId' => $this->razorpay->keyId(),
        ]);
    }

    public function checkout(Request $request): RedirectResponse|SymfonyResponse
    {
        // Nothing to buy while the product is free.
        abort_if($this->subscriptions->isFreeMode(), 404);

        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
        ]);

        abort_unless($request->user()->canDo(Permission::ManageUsers), 403);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $plan = Plan::query()->where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();

        if (! $this->razorpay->configured()) {
            return back()->withErrors(['plan' => 'Online payments are not configured yet. Please contact support.']);
        }

        $order = $this->razorpay->createPlanOrder($tenant, $plan);

        return response()->json([
            'order' => $order,
            'plan' => $plan->only(['id', 'name', 'slug', 'price', 'currency']),
            'customer' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_if($this->subscriptions->isFreeMode(), 404);

        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        abort_unless($request->user()->canDo(Permission::ManageUsers), 403);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $plan = Plan::query()->where('slug', $validated['plan'])->firstOrFail();

        // 1. Prevent payment replay attack
        if (Subscription::query()->where('gateway_subscription_id', $validated['razorpay_payment_id'])->exists()) {
            return back()->withErrors(['plan' => 'This payment has already been processed.']);
        }

        // 2. Verify payment signature
        if (! $this->razorpay->verifyPaymentSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        )) {
            return back()->withErrors(['plan' => 'Payment verification failed. Please try again.']);
        }

        // 3. Verify order notes and paid amount match plan and tenant
        if ($this->razorpay->configured()) {
            $order = $this->razorpay->fetchOrder($validated['razorpay_order_id']);
            if ($order) {
                $notes = $order['notes'] ?? [];
                $expectedAmount = (int) round((float) $plan->price * 100);

                if (isset($notes['plan_slug']) && $notes['plan_slug'] !== $plan->slug) {
                    return back()->withErrors(['plan' => 'Payment does not match the selected plan.']);
                }

                if (isset($notes['tenant_id']) && (int) $notes['tenant_id'] !== (int) $tenant->id) {
                    return back()->withErrors(['plan' => 'Payment belongs to another account.']);
                }

                if (isset($order['amount']) && $order['amount'] !== null && (int) $order['amount'] !== $expectedAmount) {
                    return back()->withErrors(['plan' => 'Paid amount does not match the plan price.']);
                }
            }
        }

        $subscription = $this->activate($tenant, $plan, $validated['razorpay_payment_id']);
        $this->sendReceipt($subscription, $plan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Subscribed to {$plan->name}. Thank you!")]);

        return to_route('billing.index');
    }

    public function cancel(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageUsers), 403);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $subscription = $this->subscriptions->subscriptionFor($tenant);

        if ($subscription && $subscription->status === 'active') {
            $subscription->update(['status' => 'cancelled']);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscription cancelled. Access continues until the period ends.')]);
        }

        return to_route('billing.index');
    }

    public function webhook(Request $request): SymfonyResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if (! $this->razorpay->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Razorpay webhook signature mismatch.');

            return response('Invalid signature.', 400);
        }

        $event = json_decode($payload, true);
        $type = $event['event'] ?? '';

        if ($type === 'payment.captured') {
            $notes = $event['payload']['payment']['entity']['notes'] ?? [];
            $this->activateFromNotes($notes, $event['payload']['payment']['entity']['id'] ?? null);
        }

        if ($type === 'payment.failed') {
            $notes = $event['payload']['payment']['entity']['notes'] ?? [];
            $this->markPastDue($notes);
        }

        return response('OK', 200);
    }

    protected function activateFromNotes(array $notes, ?string $gatewayId): void
    {
        $tenant = isset($notes['tenant_id']) ? Tenant::query()->find($notes['tenant_id']) : null;
        $plan = isset($notes['plan_slug']) ? Plan::query()->where('slug', $notes['plan_slug'])->first() : null;

        if (! $tenant || ! $plan) {
            Log::warning('Razorpay webhook for unknown tenant/plan.', $notes);

            return;
        }

        $subscription = $this->activate($tenant, $plan, $gatewayId);
        $this->sendReceipt($subscription, $plan);
    }

    protected function markPastDue(array $notes): void
    {
        if (! isset($notes['tenant_id'])) {
            return;
        }

        $subscription = Subscription::query()->where('tenant_id', $notes['tenant_id'])->first();

        if ($subscription && $subscription->status === 'active') {
            $subscription->update(['status' => 'past_due']);
        }
    }

    protected function activate(Tenant $tenant, Plan $plan, ?string $gatewayId): Subscription
    {
        $subscription = Subscription::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $subscription->plan_id = $plan->id;
        $subscription->status = 'active';
        $subscription->trial_ends_at = null;
        $subscription->current_period_ends_at = now()->addMonth();
        $subscription->gateway = 'razorpay';
        $subscription->gateway_subscription_id = $gatewayId;
        $subscription->save();

        return $subscription->refresh();
    }

    protected function sendReceipt(Subscription $subscription, Plan $plan): void
    {
        $admins = User::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->get(['email', 'name']);

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new SubscriptionReceiptMail($subscription, $plan));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
