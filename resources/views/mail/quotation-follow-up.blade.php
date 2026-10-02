Hi {{ $quotation->customer->full_name }},

Your quotation **{{ $quotation->invoice_number }}** from {{ $business->business_name }} is still open.
@if($validUntil)
It is valid until **{{ $validUntil->format('d M Y') }}**.
@endif
@if($shareUrl)
[View the quotation]({{ $shareUrl }})
@endif

If you would like to go ahead, just reply to this message and we will take it from there.

Regards,
{{ $business->business_name }}
@if($business->phone)
{{ $business->phone }}
@endif
