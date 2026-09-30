<p>Hi,</p>

<p>
    Thanks for subscribing <strong>{{ $businessName }}</strong> to the
    <strong>{{ $plan->name }}</strong> plan
    ({{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}/{{ $plan->interval }}).
</p>

@if($subscription->current_period_ends_at)
    <p>Your subscription is active until {{ $subscription->current_period_ends_at->format('d M Y') }}.</p>
@endif

<p>Regards,<br>Jewelry Invoice Billing</p>
