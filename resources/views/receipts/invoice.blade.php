<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
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
    @if($business->tax_number)<div class="c small">GSTIN: {{ $business->tax_number }}</div>@endif
    <hr>
    <div class="row"><span>Bill no</span><span>{{ $invoice->invoice_number }}</span></div>
    <div class="row"><span>Date</span><span>{{ $invoice->invoice_date->format('d-m-Y') }}</span></div>
    <div class="row"><span>Customer</span><span>{{ $invoice->customer->full_name }}</span></div>
    <div class="row"><span>Mobile</span><span>{{ $invoice->customer->mobile_number }}</span></div>
    <hr>
    @foreach($invoice->items as $item)
        <div class="item">
            <div class="b">{{ $item->item_name }}</div>
            <div class="row small">
                @if($showWeights)
                    <span>{{ rtrim(rtrim(number_format((float) $item->net_weight, 3), '0'), '.') }}g {{ $item->metal_type }} {{ $item->purity }} x{{ $item->quantity }}</span>
                @elseif($item->length && $item->width)
                    <span>{{ number_format((float) $item->length * (float) $item->width / 929.0304, 2) }} sq ft {{ $item->size_label }} {{ $item->brand }} x{{ $item->quantity }}</span>
                @else
                    <span>{{ collect([$item->brand, $item->size_label, $item->specification])->filter()->implode(' ') }} x{{ $item->quantity }}</span>
                @endif
                <span>{{ number_format((float) $item->base_value, 2) }}</span>
            </div>
            @if($showWeights && $item->huid_number)<div class="small">HUID {{ $item->huid_number }}</div>@endif
        </div>
    @endforeach
    <hr>
    <div class="row"><span>Subtotal</span><span>{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
    @foreach($invoice->charges_summary ?? [] as $row)
        <div class="row"><span>{{ $row['label'] }}</span><span>{{ number_format((float) $row['amount'], 2) }}</span></div>
    @endforeach
    @if((float) $invoice->discount > 0)
        <div class="row"><span>Discount</span><span>-{{ number_format((float) $invoice->discount, 2) }}</span></div>
    @endif
    @if(!empty($invoice->tax_breakdown))
        @foreach($invoice->tax_breakdown as $taxRow)
            <div class="row"><span>{{ $taxRow['label'] }}</span><span>{{ number_format((float) $taxRow['amount'], 2) }}</span></div>
        @endforeach
    @elseif((float) $invoice->tax > 0)
        <div class="row"><span>Tax</span><span>{{ number_format((float) $invoice->tax, 2) }}</span></div>
    @endif
    @if((float) $invoice->round_off != 0.0)
        <div class="row"><span>Round off</span><span>{{ number_format((float) $invoice->round_off, 2) }}</span></div>
    @endif
    <hr>
    <div class="row grand"><span>TOTAL</span><span>Rs. {{ number_format((float) $invoice->grand_total, 2) }}</span></div>
    <div class="row"><span>Paid</span><span>{{ number_format((float) $invoice->paid_amount, 2) }}</span></div>
    <div class="row b"><span>Balance</span><span>{{ number_format((float) $invoice->balance_amount, 2) }}</span></div>
    @if($invoice->payments->isNotEmpty())
        <hr>
        @foreach($invoice->payments as $payment)
            <div class="row small"><span>{{ $payment->payment_date->format('d-m') }} {{ $payment->payment_method->label() }}</span><span>{{ number_format((float) $payment->amount, 2) }}</span></div>
        @endforeach
    @endif
    <hr>
    <div class="c small">{{ $business->footer_text ?: 'Thank you! Visit again.' }}</div>
</div>
</body>
</html>
