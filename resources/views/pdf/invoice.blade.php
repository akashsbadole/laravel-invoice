<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 12mm; }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #111827; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .wrap { padding: 8px 10px 10px; }

        /* Masthead: business block left, TAX INVOICE right. */
        .masthead td { vertical-align: top; padding: 7px 8px; border: 1px solid #111827; }
        .shop-name { font-size: 17px; font-weight: bold; letter-spacing: 0.03em; }
        .shop-tagline { font-size: 9px; color: #4B5563; margin-top: 2px; }
        .shop-meta { font-size: 8.5px; color: #374151; line-height: 1.4; margin-top: 4px; }
        .doc-title { font-size: 19px; font-weight: bold; letter-spacing: 0.08em; text-align: right; }
        .doc-flag { font-size: 8px; text-align: right; font-weight: bold; margin-top: 2px; }

        .pan-row td { border: 1px solid #111827; border-top: 0; padding: 4px 8px; font-size: 9px; }

        .party-head td { border: 1px solid #111827; background: #F3F4F6; font-size: 9px; font-weight: bold; padding: 3px 8px; }
        .party-body td { border: 1px solid #111827; vertical-align: top; padding: 6px 8px; font-size: 9px; }
        .label { font-weight: bold; }
        .kv { width: 78px; display: inline-block; }

        /* Items table. The description column is the only flexible one so a
           long product name wraps instead of pushing the numbers off-page. */
        .items-table th {
            background: #E5E7EB; border: 1px solid #111827; font-size: 8.5px; font-weight: bold;
            text-transform: uppercase; letter-spacing: 0.04em; padding: 4px 5px;
        }
        .items-table td { border: 1px solid #111827; padding: 4px 5px; font-size: 9px; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #6B7280; }
        .total-row td { border: 1px solid #111827; font-weight: bold; padding: 4px 5px; font-size: 9.5px; }
        .tax-note { border: 1px solid #111827; border-top: 0; padding: 4px 5px; font-size: 8.5px; text-align: right; }

        .words-row td { border: 1px solid #111827; padding: 4px 8px; font-size: 9px; }
        .words-row .label { width: 130px; }

        .hsn-table th, .hsn-table td { border: 1px solid #111827; padding: 3px 5px; font-size: 8.5px; }
        .hsn-table th { background: #E5E7EB; font-weight: bold; }

        .foot-td { border: 1px solid #111827; vertical-align: top; padding: 6px 8px; font-size: 8.5px; }
        .foot-title { font-weight: bold; text-align: center; font-size: 9px; }
        .sign-line { border-top: 1px solid #111827; margin-top: 40px; padding-top: 3px; font-size: 8px; text-align: center; }
        .thanks { margin-top: 8px; font-size: 9px; }
    </style>
</head>
<body>
<div class="wrap">
<table class="masthead">
    <tr>
        <td style="width: 62%;">
            @if($business->logo_path)
                <img src="{{ public_path('storage/' . $business->logo_path) }}" style="max-height: 30px; max-width: 100%; margin-bottom: 3px;">
            @endif
            <div class="shop-name">{{ strtoupper($business->business_name) }}</div>
            @if($business->footer_text)<div class="shop-tagline">{{ $business->footer_text }}</div>@endif
            <div class="shop-meta">
                @if($business->address){{ $business->address }}<br>@endif
                @if($business->phone)Tel: {{ $business->phone }}@endif
                @if($business->email) &nbsp;·&nbsp; {{ $business->email }}@endif
                @if($business->website)<br>Web: {{ $business->website }}@endif
            </div>
        </td>
        <td style="width: 38%;">
            <div class="doc-title">TAX INVOICE</div>
            <div class="doc-flag">ORIGINAL FOR RECIPIENT</div>
            @if($invoice->irn)
                <div class="doc-meta" style="font-size:8px; margin-top:4px;">
                    <b>IRN:</b> {{ $invoice->irn }}
                </div>
            @endif
        </td>
    </tr>
</table>

<table class="pan-row">
    <tr>
        <td style="width: 50%;">@if($pan)<span class="label">PAN:</span> {{ $pan }}@endif</td>
        <td style="width: 50%;">@if($gstin)<span class="label">GSTIN:</span> {{ $gstin }}@endif</td>
    </tr>
</table>

<table class="party-head">
    <tr><td style="width: 50%;">Customer Detail</td><td style="width: 50%;">Invoice Detail</td></tr>
</table>
<table class="party-body">
    <tr>
        <td style="width: 50%;">
            <div><span class="kv label">M/S</span> {{ $invoice->customer->full_name }}</div>
            @if($invoice->customer->address)<div><span class="kv label">Address</span> {{ $invoice->customer->address }}</div>@endif
            @if($invoice->customer->mobile_number)<div><span class="kv label">Phone</span> {{ $invoice->customer->mobile_number }}</div>@endif
            @if($invoice->customer->tax_number)<div><span class="kv label">GSTIN</span> {{ $invoice->customer->tax_number }}</div>@endif
            @if($placeOfSupply)<div><span class="kv label">Place of Supply</span> {{ $placeOfSupply }}</div>@endif
        </td>
        <td style="width: 50%;">
            <div><span class="kv label">Invoice No.</span> <b>{{ $invoice->invoice_number }}</b></div>
            <div><span class="kv label">Invoice Date</span> {{ $invoice->invoice_date->format('d-M-Y') }}</div>
            @if($invoice->due_date)
                <div><span class="kv label">Due Date</span> {{ $invoice->due_date->format('d-M-Y') }}</div>
            @endif
            @if($invoice->reference_number)
                <div><span class="kv label">Reference</span> {{ $invoice->reference_number }}</div>
            @endif
            @if($invoice->eway_bill_no)
                <div><span class="kv label">E-Way Bill</span> {{ $invoice->eway_bill_no }}</div>
            @endif
            @if($invoice->rate_locked_at)
                <div><span class="kv label">Rate as on</span> {{ $invoice->rate_locked_at->format('d-M-Y') }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th style="width: 5%;" class="center">Sr. No.</th>
            <th style="width: 33%;">Name of Product / Service</th>
            <th style="width: 9%;" class="center">HSN / SAC</th>
            <th style="width: 8%;" class="right">Qty</th>
            <th style="width: 13%;" class="right">Rate</th>
            <th style="width: 16%;" class="right">Taxable Value</th>
            <th style="width: 16%;" class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoice->items as $index => $item)
            @php
                // Casts are resolved here rather than inside a directive:
                // Blade's parenthesis matching breaks on a cast inside an
                // if-directive, silently capturing only the cast as the
                // whole expression.
                $lineTaxable = max((float) $item->base_value - (float) $item->discount, 0);
                $hasWeight = $showWeights && $item->metal_type;
                $hasGrams = (float) $item->net_weight > 0;
                $hasRate = (float) $item->rate > 0;

                // Assembled in PHP rather than interleaved with directives:
                // a separator character directly after an inline @if is not
                // recognised, and the trailing @endif leaks out as text.
                $specParts = array_filter([
                    $hasWeight ? trim($item->metal_type.($item->purity ? ' '.$item->purity : '')) : null,
                    $hasGrams ? number_format((float) $item->net_weight, 3).'g' : null,
                    $hasRate ? trim($item->rate_type->label().' '.number_format((float) $item->rate, 2)) : null,
                ]);
            @endphp
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>
                    <b>{{ $item->item_name }}</b>
                    @if($item->line_type === 'exchange_credit')<br><span class="muted">Exchange credit (old gold)</span>@endif
                    @if($specParts !== [])
                        <br><span class="muted">{{ implode(' · ', $specParts) }}</span>
                    @endif
                    @if($showStones && $item->stone_carat > 0)
                        <br><span class="muted">Stone: {{ number_format((float) $item->stone_carat, 3) }}ct {{ $item->stone_clarity }} {{ $item->stone_color }}</span>
                    @endif
                    @if($template->config('show_huid') && $item->huid_number)<br><span class="muted">HUID: {{ $item->huid_number }}</span>@endif
                    @if($item->certificate_number)<br><span class="muted">Cert: {{ $item->certificate_number }}</span>@endif
                    @if(! empty($item->attributes))
                        <br><span class="muted">{{ collect($item->attributes)->map(fn ($v, $k) => $k.': '.$v)->implode(' · ') }}</span>
                    @endif
                </td>
                <td class="center">{{ $item->hsn_code ?: '—' }}</td>
                <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                <td class="right">{{ number_format((float) $item->rate, 2) }}</td>
                <td class="right">{{ number_format($lineTaxable, 2) }}</td>
                <td class="right">{{ number_format((float) $item->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="center muted">No items on this invoice.</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="3" class="right">Total</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $invoice->items->sum('quantity'), 3), '0'), '.') }}</td>
            <td></td>
            <td class="right">{{ number_format((float) $invoice->subtotal, 2) }}</td>
            <td class="right">{{ number_format((float) $invoice->grand_total, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="tax-note">
    @php $hasRoundOff = (float) $invoice->round_off != 0; @endphp
    @if(! empty($invoice->tax_breakdown))
        @foreach($invoice->tax_breakdown as $taxRow)
            {{ $taxRow['label'] }}: {{ number_format((float) $taxRow['amount'], 2) }} &nbsp;&nbsp;
        @endforeach
    @else
        Tax: {{ number_format((float) $invoice->tax, 2) }}
    @endif
    @if($hasRoundOff)
        &nbsp;&nbsp; Round off: {{ number_format((float) $invoice->round_off, 2) }}
    @endif
    &nbsp;&nbsp; (E &amp; O.E.)
</div>

<table class="words-row" style="margin-top:6px;">
    <tr>
        <td><span class="label">Total in words</span></td>
        <td><b>{{ strtoupper($totalInWords ?: '—') }}</b></td>
    </tr>
</table>

@if(! empty($hsnSummary))
    <table class="hsn-table" style="margin-top:6px;">
        <thead>
            <tr>
                <th style="width: 14%;">HSN / SAC</th>
                <th style="width: 20%;" class="right">Taxable Value</th>
                <th style="width: 10%;" class="right">Rate %</th>
                <th style="width: 14%;" class="right">CGST</th>
                <th style="width: 14%;" class="right">SGST</th>
                <th style="width: 14%;" class="right">IGST</th>
                <th style="width: 14%;" class="right">Total Tax</th>
            </tr>
        </thead>
        <tbody>
            @foreach($hsnSummary as $row)
                <tr>
                    <td>{{ $row['hsn'] }}</td>
                    <td class="right">{{ number_format($row['taxable'], 2) }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($row['rate'], 2), '0'), '.') }}</td>
                    <td class="right">{{ $row['cgst'] > 0 ? number_format($row['cgst'], 2) : '—' }}</td>
                    <td class="right">{{ $row['sgst'] > 0 ? number_format($row['sgst'], 2) : '—' }}</td>
                    <td class="right">{{ $row['igst'] > 0 ? number_format($row['igst'], 2) : '—' }}</td>
                    <td class="right">{{ number_format($row['tax'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="words-row">
        <tr>
            <td><span class="label">Total tax in words</span></td>
            <td><b>{{ strtoupper($taxInWords ?: '—') }}</b></td>
        </tr>
    </table>
@endif

<table style="margin-top:6px;">
    <tr>
        <td style="width: 58%; vertical-align: top;">
            <div class="foot-td">
                @if($template->config('show_bank_details') && ! empty($business->bank_details))
                    <div class="foot-title">Bank Details</div>
                    <div style="margin-top:3px;">
                        @if(! empty($business->bank_details['bank_name']))
                            <span class="label">Name</span> {{ $business->bank_details['bank_name'] }}<br>
                        @endif
                        @if(! empty($business->bank_details['account_number']))
                            <span class="label">A/C No.</span> {{ $business->bank_details['account_number'] }}<br>
                        @endif
                        @if(! empty($business->bank_details['ifsc_code']))
                            <span class="label">IFSC</span> {{ $business->bank_details['ifsc_code'] }}<br>
                        @endif
                        @if(! empty($business->bank_details['upi_id']))
                            <span class="label">UPI ID</span> {{ $business->bank_details['upi_id'] }}
                        @endif
                    </div>
                @endif

                @if($invoice->terms || $business->invoice_terms)
                    <div style="margin-top:8px;">
                        <div class="foot-title">Terms and Conditions</div>
                        <div style="margin-top:3px; line-height:1.45;">
                            @php
                                $footTerms = array_filter(
                                    preg_split('/\r\n|\r|\n/', (string) ($invoice->terms ?: $business->invoice_terms)),
                                    fn ($line) => trim($line) !== '',
                                );
                            @endphp
                            @forelse($footTerms as $line)
                                <div>{{ $line }}</div>
                            @empty
                                <div>Goods once sold will not be taken back.</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </td>
        <td style="width: 42%; vertical-align: top; padding-left:6px;">
            <div class="foot-td">
                <div class="center" style="font-size:8.5px;">Certified that the particulars given above are true and correct.</div>
                <div class="foot-title" style="margin-top:6px;">For {{ $business->business_name }}</div>
                @if($template->config('show_signature') && $business->signature_image_path)
                    <div style="text-align:center; margin-top:6px;">
                        <img src="{{ public_path('storage/' . $business->signature_image_path) }}" style="max-height:44px;">
                    </div>
                @endif
                @if($template->config('show_stamp') && $business->stamp_image_path)
                    <div style="text-align:center;">
                        <img src="{{ public_path('storage/' . $business->stamp_image_path) }}" style="max-height:52px;">
                    </div>
                @endif
                <div class="sign-line">Authorised Signatory</div>
            </div>
        </td>
    </tr>
</table>

<div class="thanks">Thank you for your business!</div>

@if($publicUrl || $qrSvg)
    <div style="margin-top:6px; font-size:8px; color:#6B7280;">
        @if($publicUrl)Verify this invoice online: {{ $publicUrl }}@endif
        @if($qrSvg)
            <div style="margin-top:4px;">
                <img src="{{ $qrSvg }}" style="width:66px; height:66px;">
            </div>
        @endif
    </div>
@endif

</div>
</body>
</html>
