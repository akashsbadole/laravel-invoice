<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingWebhookAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_payment_with_mismatched_amount(): void
    {
        config(['billing.mode' => 'paid']);
        Plan::ensureDefaults();

        $tenant = Tenant::factory()->create();
        $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

        $this->mock(RazorpayService::class, function (MockInterface $mock) {
            $mock->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mock->shouldReceive('fetchPayment')->andReturn([
                'id' => 'pay_123',
                'amount' => 1,
            ]);
        });

        $payload = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_123',
                        'notes' => [
                            'tenant_id' => (string) $tenant->id,
                            'plan_slug' => $starter->slug,
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->post(route('billing.webhook'), [], [
            'Content-Type' => 'application/json',
            'X-Razorpay-Signature' => 'valid',
        ] + ['content' => $payload]);

        $response->assertOk();

        $this->assertNull(Subscription::where('tenant_id', $tenant->id)->first());
    }
}
