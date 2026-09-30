<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receiptNumber }}</title>
    @include('receipts._style')
</head>
<body>
@unless($isPdf)
    <div class="toolbar no-print">
        <button onclick="window.print()">Print</button>
        <a href="{{ request()->fullUrlWithQuery(['width' => '58', 'pdf' => null]) }}" class="{{ $width === '58' ? 'on' : '' }}">58 mm</a>
        <a href="{{ request()->fullUrlWithQuery(['width' => '80', 'pdf' => null]) }}" class="{{ $width === '80' ? 'on' : '' }}">80 mm</a>
        <a href="{{ request()->fullUrlWithQuery(['pdf' => 1]) }}">PDF</a>
    </div>
@endunless
<div class="receipt">
    <div class="c b big">{{ $business->business_name }}</div>
    @if($business->address)<div class="c small">{{ $business->address }}</div>@endif
    @if($business->phone)<div class="c small">Ph: {{ $business->phone }}</div>@endif
    <hr>
    <div class="c b">PAYMENT RECEIPT</div>
    <hr>
    <div class="row"><span>Receipt</span><span>{{ $receiptNumber }}</span></div>
    <div class="row"><span>Date</span><span>{{ $payment->payment_date->format('d-m-Y') }}</span></div>
    <div class="row"><span>Received from</span><span>{{ $invoice->customer->full_name }}</span></div>
    <div class="row"><span>Against bill</span><span>{{ $invoice->invoice_number }}</span></div>
    <div class="row"><span>Mode</span><span>{{ $payment->payment_method->label() }}</span></div>
    @if($payment->reference_number)<div class="row"><span>Ref</span><span>{{ $payment->reference_number }}</span></div>@endif
    <hr>
    <div class="row grand"><span>AMOUNT</span><span>Rs. {{ number_format((float) $payment->amount, 2) }}</span></div>
    <hr>
    <div class="row"><span>Bill total</span><span>{{ number_format((float) $invoice->grand_total, 2) }}</span></div>
    <div class="row"><span>Paid so far</span><span>{{ number_format((float) $invoice->paid_amount, 2) }}</span></div>
    <div class="row b"><span>Balance due</span><span>{{ number_format((float) $invoice->balance_amount, 2) }}</span></div>
    @if($payment->receiver)<div class="small" style="margin-top:6px">Received by: {{ $payment->receiver->name }}</div>@endif
    <hr>
    <div class="c small">{{ $business->footer_text ?: 'Thank you!' }}</div>
</div>
</body>
</html>
