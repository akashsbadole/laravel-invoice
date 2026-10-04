<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_replay_is_rejected(): void
    {
        config(['billing.mode' => 'paid']);
        Plan::ensureDefaults();

        $user = $this->adminFor();
        $plan = Plan::query()->where('slug', 'starter')->firstOrFail();

        // Update existing subscription with payment ID 'pay_replay_123'
        Subscription::query()->where('tenant_id', $user->tenant_id)->update([
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway_subscription_id' => 'pay_replay_123',
        ]);

        $this->actingAs($user)
            ->post(route('billing.verify'), [
                'plan' => 'starter',
                'razorpay_order_id' => 'order_123',
                'razorpay_payment_id' => 'pay_replay_123',
                'razorpay_signature' => 'sig_123',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['plan']);
    }

    public function test_plan_mismatch_is_rejected(): void
    {
        config(['billing.mode' => 'paid']);
        Plan::ensureDefaults();

        $user = $this->adminFor();
        $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
        $pro = Plan::query()->where('slug', 'professional')->firstOrFail();

        // Mock RazorpayService to verify signature and return order for starter plan
        $this->mock(RazorpayService::class, function (MockInterface $mock) use ($user, $starter) {
            $mock->shouldReceive('verifyPaymentSignature')->andReturn(true);
            $mock->shouldReceive('configured')->andReturn(true);
            $mock->shouldReceive('fetchOrder')->andReturn([
                'id' => 'order_cheap_123',
                'amount' => (int) round((float) $starter->price * 100),
                'notes' => [
                    'tenant_id' => (string) $user->tenant_id,
                    'plan_slug' => $starter->slug,
                ],
            ]);
        });

        // Attempting to claim 'professional' plan using the order for 'starter' plan
        $this->actingAs($user)
            ->post(route('billing.verify'), [
                'plan' => 'professional',
                'razorpay_order_id' => 'order_cheap_123',
                'razorpay_payment_id' => 'pay_new_456',
                'razorpay_signature' => 'sig_456',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['plan']);
    }
}
