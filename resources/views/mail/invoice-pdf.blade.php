<p>Hi {{ $invoice->customer->full_name }},</p>

<p>
    Please find attached invoice <strong>{{ $invoice->invoice_number }}</strong>
    dated {{ $invoice->invoice_date->format('d M Y') }}
    for {{ $business->default_currency }} {{ number_format((float) $invoice->grand_total, 2) }}.
</p>

@if ($invoice->balance_amount > 0)
    <p>
        Balance due: <strong>{{ $business->default_currency }}
        {{ number_format((float) $invoice->balance_amount, 2) }}</strong>@if($invoice->due_date)
        (due by {{ $invoice->due_date->format('d M Y') }})@endif.
    </p>
@else
    <p>This invoice is fully paid. Thank you!</p>
@endif

@if ($customMessage)
    <p>{{ $customMessage }}</p>
@endif

<p>
    Regards,<br>
    {{ $business->business_name }}@if($business->phone)<br>{{ $business->phone }}@endif
</p>
