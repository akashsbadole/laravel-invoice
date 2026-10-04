<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 12mm; }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1F2937; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .wrap { padding: 8px 10px 10px; }

        /* Masthead: shop identity on the left, the word QUOTE on the right. */
        .masthead td { vertical-align: top; }
        .shop-name { font-size: 17px; font-weight: bold; color: #7C3AED; letter-spacing: 0.02em; }
        .shop-meta { font-size: 9px; color: #6B7280; line-height: 1.45; margin-top: 3px; }
        .doc-title { font-size: 24px; font-weight: bold; letter-spacing: 0.14em; color: #DC2626; text-align: right; }
        .doc-meta { font-size: 9px; text-align: right; margin-top: 4px; line-height: 1.5; }
        .doc-meta b { color: #111827; }
        .rule { height: 2px; background: #7C3AED; margin-top: 8px; }

        .band {
            background: #7C3AED; color: #FFFFFF; font-size: 9px;
            font-weight: bold; letter-spacing: 0.1em; padding: 3px 8px; margin-top: 14px;
        }
        .section-body { padding: 7px 8px; border: 1px solid #E5E7EB; border-top: 0; }

        .items-table th {
            background: #7C3AED; color: #FFFFFF; font-size: 8.5px; text-transform: uppercase;
            letter-spacing: 0.06em; text-align: left; padding: 5px 6px;
        }
        .items-table th.right { text-align: right; }
        .items-table td { padding: 5px 6px; border-bottom: 1px solid #F3F4F6; font-size: 10px; vertical-align: top; }
        .items-table td.right { text-align: right; }
        .muted { color: #6B7280; }

        .totals td { padding: 2.5px 0; font-size: 10px; }
        .totals .label { color: #6B7280; text-align: right; padding-right: 10px; }
        .totals .amount { text-align: right; white-space: nowrap; }
        .totals .grand td { border-top: 2px solid #7C3AED; padding-top: 5px; font-weight: bold; font-size: 11px; }

        .terms-title {
            background: #7C3AED; color: #FFFFFF; font-size: 9px; font-weight: bold;
            letter-spacing: 0.08em; padding: 3px 8px;
        }
        .terms-body { border: 1px solid #E5E7EB; border-top: 0; padding: 7px 8px; font-size: 9.5px; line-height: 1.5; }
        .accept-line { border-top: 1px dotted #9CA3AF; margin-top: 22px; padding-top: 3px; font-size: 8.5px; color: #6B7280; }

        .footer { margin-top: 14px; text-align: center; font-size: 10px; font-weight: bold; color: #7C3AED; }
    </style>
</head>
<body>
<div class="wrap">

    <table class="masthead">
        <tr>
            <td style="width: 64%;">
                @if($business->logo_path)
                    <img src="{{ public_path('storage/' . $business->logo_path) }}" style="max-height: 34px; max-width: 100%; margin-bottom: 4px;">
                @endif
                <div class="shop-name">{{ $business->business_name }}</div>
                <div class="shop-meta">
                    @if($business->address){{ $business->address }}<br>@endif
                    @if($business->phone)Tel: {{ $business->phone }}<br>@endif
                    @if($business->email)Email: {{ $business->email }}<br>@endif
                    @if($business->website)Web: {{ $business->website }}@endif
                </div>
            </td>
            <td style="width: 36%;">
                <div class="doc-title">QUOTE</div>
                <div class="doc-meta">
                    <span class="muted">Quote #</span> <b>{{ $invoice->invoice_number }}</b><br>
                    <span class="muted">Date</span> <b>{{ $invoice->invoice_date->format('d-m-Y') }}</b><br>
                    @if($invoice->quotation_valid_until)
                        <span class="muted">Valid until</span> <b>{{ $invoice->quotation_valid_until->format('d-m-Y') }}</b><br>
                    @endif
                    @if($invoice->revision_number > 1)
                        <span class="muted">Revision</span> <b>{{ $invoice->revision_number }}</b><br>
                    @endif
                    @if($invoice->rate_locked_at)
                        <span class="muted">Rate as on</span> <b>{{ $invoice->rate_locked_at->format('d-m-Y') }}</b>
                    @endif
                </div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <div class="band">CUSTOMER</div>
    <div class="section-body">
        <table>
            <tr>
                <td style="width: 60%;">
                    <b>{{ $invoice->customer->full_name }}</b><br>
                    @if($invoice->customer->address){{ $invoice->customer->address }}<br>@endif
                    @if($invoice->customer->mobile_number)Phone: {{ $invoice->customer->mobile_number }}@endif
                </td>
                <td style="width: 40%;" class="muted">
                    @if($invoice->customer->tax_number)GSTIN: {{ $invoice->customer->tax_number }}<br>@endif
                    @if($invoice->salesperson)Salesperson: {{ $invoice->salesperson->name }}<br>@endif
                    @if($invoice->reference_number)Ref: {{ $invoice->reference_number }}@endif
                </td>
            </tr>
        </table>
    </div>

    <div class="band">DESCRIPTION</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 52%;">Description</th>
                <th style="width: 12%;" class="right">Qty</th>
                <th style="width: 18%;" class="right">Rate</th>
                <th style="width: 18%;" class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                @php
                    // Casts live here, never inside a directive: Blade's
                    // parenthesis matching silently truncates an if-directive
                    // whose expression starts with a cast.
                    $hasWeight = $showWeights && $item->metal_type;
                    $hasGrams = (float) $item->net_weight > 0;

                    // Assembled in PHP, never interleaved with directives: a
                    // separator straight after an inline @if is not recognised
                    // and the closing @endif leaks out as visible text.
                    $specParts = array_filter([
                        $hasWeight ? trim($item->metal_type.($item->purity ? ' '.$item->purity : '')) : null,
                        $hasGrams ? number_format((float) $item->net_weight, 3).'g' : null,
                    ]);
                @endphp
                <tr>
                    <td>
                        <b>{{ $item->item_name }}</b>
                        @if($item->line_type === 'exchange_credit')<br><span class="muted">Exchange credit (old gold)</span>@endif
                        @if($specParts !== [])
                            <br><span class="muted">{{ implode(' · ', $specParts) }}</span>
                        @endif
                        @if(! empty($item->attributes))
                            <br><span class="muted">{{ collect($item->attributes)->map(fn ($v, $k) => $k.': '.$v)->implode(' · ') }}</span>
                        @endif
                    </td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                    <td class="right">{{ number_format((float) $item->rate, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No items on this quotation.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 52%; vertical-align: top;">
                <div class="terms-title">TERMS AND CONDITIONS</div>
                <div class="terms-body">
                    @php
                        $termLines = array_filter(
                            preg_split('/\r\n|\r|\n/', (string) ($invoice->terms ?: $business->invoice_terms ?: '1. This quotation is valid until the date stated above.')),
                            fn ($line) => trim($line) !== '',
                        );
                    @endphp
                    @forelse($termLines as $line)
                        <div>{{ $line }}</div>
                    @empty
                        <div>1. This quotation is valid until the date stated above.</div>
                    @endforelse

                    <div class="accept-line">Customer Acceptance (sign below)</div>
                    <div class="accept-line">Name</div>
                    <div class="accept-line">Signature &amp; Date</div>
                </div>
            </td>
            <td style="width: 48%; padding-left: 12px; vertical-align: top;">
                <table class="totals">
                    @php
                        $hasDiscount = (float) $invoice->discount > 0;
                        $hasRoundOff = (float) $invoice->round_off != 0;
                    @endphp
                    <tr><td class="label">Subtotal</td><td class="amount">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                    @foreach($invoice->charges_summary ?? [] as $row)
                        @php $isCharge = (float) $row['amount'] >= 0; @endphp
                        @if($isCharge)
                            <tr><td class="label">{{ $row['label'] }}</td><td class="amount">{{ number_format((float) $row['amount'], 2) }}</td></tr>
                        @endif
                    @endforeach
                    @if($hasDiscount)
                        <tr><td class="label">Discount</td><td class="amount">- {{ number_format((float) $invoice->discount, 2) }}</td></tr>
                    @endif
                    @if(! empty($invoice->tax_breakdown))
                        @foreach($invoice->tax_breakdown as $taxRow)
                            <tr><td class="label">{{ $taxRow['label'] }}</td><td class="amount">{{ number_format((float) $taxRow['amount'], 2) }}</td></tr>
                        @endforeach
                    @else
                        <tr><td class="label">Tax</td><td class="amount">{{ number_format((float) $invoice->tax, 2) }}</td></tr>
                    @endif
                    @if($hasRoundOff)
                        <tr><td class="label">Round off</td><td class="amount">{{ number_format((float) $invoice->round_off, 2) }}</td></tr>
                    @endif
                    <tr class="grand">
                        <td class="label">TOTAL</td>
                        <td class="amount">{{ number_format((float) $invoice->grand_total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">Thank You For Your Business</div>

</div>
</body>
</html>
