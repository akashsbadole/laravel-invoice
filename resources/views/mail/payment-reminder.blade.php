Hi {{ $invoice->customer->full_name }},

A friendly reminder that invoice **{{ $invoice->invoice_number }}** still has
**{{ $business->default_currency }} {{ $amount }}** outstanding@if($dueDate), due by
{{ $dueDate->format('d M Y') }}@endif.

@if ($shareUrl)
[View the invoice and pay online]({{ $shareUrl }})
@else
If you have already paid, please ignore this message.
@endif

Regards,
{{ $business->business_name }}@if($business->phone),
{{ $business->phone }}@endif