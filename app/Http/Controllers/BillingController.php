<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\RazorpayService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        abort_unless($request->user()->role->canManageUsers(), 403);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $subscription = $this->subscriptions->subscriptionFor($tenant);
        $subscription?->load('plan');

        return Inertia::render('billing', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
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
        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
        ]);

        abort_unless($request->user()->role->canManageUsers(), 403);

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
        $validated = $request->validate([
            'plan' => ['required', 'exists:plans,slug'],
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        if (! $this->razorpay->verifyPaymentSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        )) {
            return back()->withErrors(['plan' => 'Payment verification failed. Please try again.']);
        }

        abort_unless($request->user()->role->canManageUsers(), 403);

        /** @var Tenant $tenant */
        $tenant = $request->user()->tenant;
        $plan = Plan::query()->where('slug', $validated['plan'])->firstOrFail();

        $this->activate($tenant, $plan, $validated['razorpay_payment_id']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Subscribed to {$plan->name}. Thank you!")]);

        return to_route('billing.index');
    }

    public function cancel(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->canManageUsers(), 403);

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

        $this->activate($tenant, $plan, $gatewayId);
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

    protected function activate(Tenant $tenant, Plan $plan, ?string $gatewayId): void
    {
        $subscription = Subscription::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $subscription->plan_id = $plan->id;
        $subscription->status = 'active';
        $subscription->trial_ends_at = null;
        $subscription->current_period_ends_at = now()->addMonth();
        $subscription->gateway = 'razorpay';
        $subscription->gateway_subscription_id = $gatewayId;
        $subscription->save();
    }
}
