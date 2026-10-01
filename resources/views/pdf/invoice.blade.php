<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1c2420; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .letterhead { background: #0b3d2e; color: #faf7f0; padding: 26px 32px 20px; }
        .letterhead .business-name { font-size: 21px; font-weight: bold; letter-spacing: 0.06em; color: #faf7f0; }
        .letterhead .muted { color: #c9bfae; }
        .gold-band { height: 3px; background: #c9a227; }
        .body-wrap { padding: 20px 32px 28px; }
        .header-table td { vertical-align: top; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        h2 { font-size: 12px; margin: 18px 0 6px; letter-spacing: 0.12em; text-transform: uppercase; color: #0b3d2e; }
        .invoice-title { font-size: 20px; font-weight: bold; letter-spacing: 0.18em; color: #0b3d2e; margin: 0 0 4px; }
        .invoice-no { font-size: 13px; font-weight: bold; color: #8c6e1a; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 4px;
            font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em;
            background: #f4ecd8; color: #8c6e1a; border: 1px solid #c9a227;
        }
        .items-table th {
            background: #0b3d2e; color: #faf7f0;
            padding: 7px 5px; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.06em;
        }
        .items-table td { padding: 6px 5px; border-bottom: 1px solid #e7e0d0; font-size: 10.5px; }
        .items-table tr:nth-child(even) td { background: #faf7f0; }
        .totals-table td { padding: 3px 0; }
        .totals-table .label { color: #6b7280; }
        .grand-total { font-size: 14px; font-weight: bold; border-top: 2px solid #c9a227; padding-top: 6px !important; }
        .grand-total td { color: #0b3d2e; }
        .footer { margin-top: 24px; font-size: 9.5px; color: #6b7280; border-top: 1px solid #e7e0d0; padding-top: 10px; }
        .signature-block { margin-top: 40px; }
        .signature-block img { max-height: 50px; }
        .sign-line { border-top: 1px solid #0b3d2e; margin-top: 34px; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="letterhead">
        <table class="header-table">
            <tr>
                <td style="width: 62%;">
                    @if($business->logo_path)
                        <img src="{{ public_path('storage/' . $business->logo_path) }}" style="max-height: 44px; margin-bottom: 8px;">
                    @endif
                    <div class="business-name">{{ $business->business_name }}</div>
                    <div class="muted" style="margin-top: 4px;">
                        {{ $business->address }}<br>
                        @if($business->phone) Phone: {{ $business->phone }} @endif
                        @if($business->email) &nbsp;·&nbsp; {{ $business->email }} @endif
                        <br>
                        @if($business->tax_number) GSTIN: {{ $business->tax_number }} @endif
                    </div>
                </td>
                <td style="width: 38%; text-align: right;">
                    <div class="invoice-title">{{ $invoice->document_type?->label() ? mb_strtoupper($invoice->document_type->label()) : 'INVOICE' }}</div>
                    <div class="invoice-no">{{ $invoice->invoice_number }}</div>
                    <div class="muted" style="margin-top: 6px;">Date: {{ $invoice->invoice_date->format('d M Y') }}</div>
                    @if($invoice->due_date)
                        <div class="muted">Due: {{ $invoice->due_date->format('d M Y') }}</div>
                    @endif
                    <div style="margin-top: 6px;"><span class="badge">{{ str_replace('_', ' ', $invoice->status->value) }}</span></div>
                </td>
            </tr>
        </table>
    </div>
    <div class="gold-band"></div>
    <div class="body-wrap">

    <table class="header-table" style="margin-top: 18px;">
        <tr>
            <td style="width: 60%;">
                <div class="muted">Billed to</div>
                <strong>{{ $invoice->customer->full_name }}</strong><br>
                {{ $invoice->customer->mobile_number }}
                @if($invoice->customer->email) &nbsp;·&nbsp; {{ $invoice->customer->email }} @endif
                <br>
                {{ $invoice->customer->address }}
                @if($invoice->customer->tax_number)
                    <br>GSTIN: {{ $invoice->customer->tax_number }}
                @endif
            </td>
            <td style="width: 40%;" class="right muted">
                @if($invoice->reference_number)
                    Ref: {{ $invoice->reference_number }}<br>
                @endif
                @if($invoice->salesperson)
                    Salesperson: {{ $invoice->salesperson->name }}
                @endif
            </td>
        </tr>
    </table>

    <table class="items-table" style="margin-top: 16px;">
        <thead>
            <tr>
                <th>Item</th>
                <th>Metal / Purity</th>
                <th class="right">Weight (g)</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Charges</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->item_name }}</strong>
                        @if($template->config('show_huid') && $item->huid_number)<br><span class="muted">HUID: {{ $item->huid_number }}</span>@endif
                        @if($template->config('show_hsn') && $item->hsn_code)<br><span class="muted">HSN: {{ $item->hsn_code }}</span>@endif
                        @if($template->config('show_stone_details'))
                            @if($item->certificate_number)<br><span class="muted">Cert: {{ $item->certificate_number }}</span>@endif
                            @if($item->stone_carat > 0)<br><span class="muted">Stone: {{ number_format((float) $item->stone_carat, 3) }} ct {{ $item->stone_clarity }} {{ $item->stone_color }}</span>@endif
                        @endif
                    </td>
                    <td>{{ $item->metal_type }} @if($item->purity) / {{ $item->purity }} @endif</td>
                    <td class="right">{{ number_format((float) $item->net_weight, 3) }}</td>
                    <td class="right">{{ $item->quantity }}</td>
                    <td class="right">Rs. {{ number_format((float) $item->rate, 2) }}</td>
                    <td class="right">Rs. {{ number_format((float) $item->charges->sum('amount') * $item->quantity, 2) }}</td>
                    <td class="right">Rs. {{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 16px;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <table class="totals-table">
                    <tr><td class="label">Subtotal</td><td class="right">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
                    @foreach($invoice->charges_summary ?? [] as $row)
                        <tr><td class="label">{{ $row['label'] }}</td><td class="right">Rs. {{ number_format((float) $row['amount'], 2) }}</td></tr>
                    @endforeach
                    <tr><td class="label">Discount</td><td class="right">- Rs. {{ number_format((float) $invoice->discount, 2) }}</td></tr>
                    @if(!empty($invoice->tax_breakdown))
                        @foreach($invoice->tax_breakdown as $taxRow)
                            <tr><td class="label">{{ $taxRow['label'] }}</td><td class="right">Rs. {{ number_format((float) $taxRow['amount'], 2) }}</td></tr>
                        @endforeach
                    @else
                        <tr><td class="label">Tax</td><td class="right">Rs. {{ number_format((float) $invoice->tax, 2) }}</td></tr>
                    @endif
                    <tr><td class="label">Round off</td><td class="right">Rs. {{ number_format((float) $invoice->round_off, 2) }}</td></tr>
                    <tr class="grand-total"><td>Grand Total</td><td class="right">Rs. {{ number_format((float) $invoice->grand_total, 2) }}</td></tr>
                    <tr><td class="label">Paid</td><td class="right">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
                    <tr><td class="label"><strong>Balance due</strong></td><td class="right"><strong>Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($invoice->notes)
        <h2>Notes</h2>
        <div>{{ $invoice->notes }}</div>
    @endif

    @if($invoice->terms || $business->invoice_terms)
        <h2>Terms &amp; Conditions</h2>
        <div class="muted">{{ $invoice->terms ?: $business->invoice_terms }}</div>
    @endif

    <table class="signature-block">
        <tr>
            <td style="width: 60%;" class="muted">
                @if($template->config('show_bank_details') && $business->bank_details)
                    <strong>Bank details</strong><br>
                    {{ $business->bank_details['bank_name'] ?? '' }}
                    @if(!empty($business->bank_details['account_number']))
                        <br>A/C: {{ $business->bank_details['account_number'] }}
                    @endif
                    @if(!empty($business->bank_details['ifsc_code']))
                        &nbsp; IFSC: {{ $business->bank_details['ifsc_code'] }}
                    @endif
                    @if(!empty($business->bank_details['upi_id']))
                        <br>UPI: {{ $business->bank_details['upi_id'] }}
                    @endif
                @endif
            </td>
            <td style="width: 40%;" class="center">
                @if($template->config('show_signature') && $business->signature_image_path)
                    <img src="{{ public_path('storage/' . $business->signature_image_path) }}"><br>
                @endif
                @if($template->config('show_stamp') && $business->stamp_image_path)
                    <img src="{{ public_path('storage/' . $business->stamp_image_path) }}"><br>
                @endif
                <div class="muted">Authorized signatory</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <table>
            <tr>
                <td>
                    {{ $business->footer_text }}
                    @if($template->config('footer_note'))<br>{{ $template->config('footer_note') }}@endif
                    @if($publicUrl)<br>Verify this invoice online: {{ $publicUrl }}@endif
                </td>
                @if($qrSvg)
                    <td class="right" style="width: 100px;"><img src="{{ $qrSvg }}" style="width: 80px; height: 80px;"></td>
                @endif
            </tr>
        </table>
    </div>
    </div>
</body>
</html>
