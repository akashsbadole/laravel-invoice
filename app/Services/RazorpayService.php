<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService
{
    public function configured(): bool
    {
        return (bool) (config('services.razorpay.key_id') && config('services.razorpay.key_secret'));
    }

    protected function api(): Api
    {
        return new Api(
            (string) config('services.razorpay.key_id'),
            (string) config('services.razorpay.key_secret'),
        );
    }

    public function keyId(): ?string
    {
        return config('services.razorpay.key_id');
    }

    /**
     * Create a one-time checkout order for a plan.
     *
     * @return array{order_id: string, amount: int, currency: string, key_id: string}
     */
    public function createPlanOrder(Tenant $tenant, Plan $plan): array
    {
        $amountPaise = (int) round((float) $plan->price * 100);

        $order = $this->api()->order->create([
            'amount' => $amountPaise,
            'currency' => $plan->currency,
            'receipt' => "sub-{$tenant->id}-{$plan->slug}-".now()->format('YmdHis'),
            'notes' => [
                'tenant_id' => (string) $tenant->id,
                'plan_slug' => $plan->slug,
            ],
        ]);

        return [
            'order_id' => $order['id'],
            'amount' => $amountPaise,
            'currency' => $plan->currency,
            'key_id' => (string) config('services.razorpay.key_id'),
        ];
    }

    public function fetchOrder(string $orderId): ?array
    {
        try {
            $order = $this->api()->order->fetch($orderId);

            return [
                'id' => $order['id'] ?? $orderId,
                'amount' => isset($order['amount']) ? (int) $order['amount'] : null,
                'notes' => isset($order['notes']) ? (array) $order['notes'] : [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $this->api()->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        if ($secret === '') {
            return false;
        }

        try {
            $this->api()->utility->verifyWebhookSignature($payload, $signature, $secret);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }
}
