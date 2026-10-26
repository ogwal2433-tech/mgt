<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Requisition / Accountability Form — {{ $requisition->requisition_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10.5px; color: #141a1f; margin: 0; }

        /* Company banner */
        .banner { background: #0b1014; color: #fff; padding: 14px 18px; }
        .banner table { width: 100%; border-collapse: collapse; }
        .banner td { border: none; vertical-align: middle; padding: 0; }
        .logo-mark { display: inline-block; width: 34px; height: 34px; line-height: 34px; text-align: center; background: #16a34a; border-radius: 8px; font-size: 17px; font-weight: 800; color: #fff; }
        .logo-name { font-size: 21px; font-weight: 800; letter-spacing: -0.5px; margin: 0; }
        .logo-tag { font-size: 8px; letter-spacing: 2px; text-transform: uppercase; color: #86efac; margin: 2px 0 0; }
        .form-title { text-align: right; }
        .form-title .t1 { font-size: 9px; letter-spacing: 1.6px; text-transform: uppercase; color: #4ade80; margin: 0; }
        .form-title .t2 { font-size: 15px; font-weight: 800; margin: 3px 0 0; }
        .form-title .t3 { font-size: 9px; color: #c3cbd0; margin: 2px 0 0; }

        .body { padding: 14px 18px 0; }

        table.grid { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .grid th, .grid td { border: 1px solid #c3cbd0; padding: 5px 7px; text-align: left; vertical-align: middle; }
        .grid th { background: #eef0f1; font-weight: 700; color: #37414a; font-size: 9.5px; letter-spacing: 0.3px; }
        .lbl { background: #f4f6f7; font-weight: 700; color: #47535d; font-size: 9px; letter-spacing: 0.4px; text-transform: uppercase; width: 18%; }
        .num { text-align: right; }
        .items th { background: #065f46; color: #fff; text-transform: uppercase; letter-spacing: 0.6px; font-size: 9px; }
        .items .total td { background: #f0fdf4; font-weight: 700; }
        .section-head { background: #065f46; color: #fff; font-size: 9.5px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; padding: 5px 8px; margin: 12px 0 0; }
        .sig { width: 33.33%; }
        .sig .line { border-top: 1px solid #9aa5ac; margin-top: 30px; padding-top: 3px; font-size: 8.5px; color: #61707c; }
        .sig-img { max-height: 52px; margin-top: 4px; }
        .footer { margin: 14px 18px 16px; border-top: 1px solid #dfe3e5; padding-top: 8px; font-size: 8px; color: #8b98a1; }
        .pill { display: inline-block; border: 1px solid #065f46; color: #065f46; border-radius: 3px; padding: 1px 8px; font-size: 8.5px; letter-spacing: 1px; text-transform: uppercase; }
    </style>
</head>
<body>

    <!-- Company banner -->
    <div class="banner">
        <table>
            <tr>
                <td style="width: 60%;">
                    <table>
                        <tr>
                            <td style="width: 42px;"><span class="logo-mark">B</span></td>
                            <td>
                                <p class="logo-name">Biiteek</p>
                                <p class="logo-tag">Reliable, Fast &amp; Secure</p>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="form-title">
                    <p class="t1">Requisition / Accountability Form</p>
                    <p class="t2">{{ $requisition->requisition_number }}</p>
                    <p class="t3">Date: {{ $requisition->date->format('d M Y') }} · Verification: {{ $requisition->requisition_number }}-VER</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">

        <!-- Header fields -->
        <table class="grid">
            <tr>
                <td class="lbl">Requested by</td>
                <td style="width: 32%;">{{ $requisition->requested_by_name ?: ($requisition->external_contact ?: '—') }}</td>
                <td class="lbl">Payee</td>
                <td>{{ $requisition->payee_name ?: $requisition->external_party_name }}</td>
            </tr>
            <tr>
                <td class="lbl">Dept / party</td>
                <td>{{ $requisition->department_text ?: $requisition->external_party_name }}</td>
                <td class="lbl">Mode of payment</td>
                <td><strong>{{ ucwords(str_replace('_', ' ', (string) ($requisition->mode_of_payment ?: '—'))) }}</strong></td>
            </tr>
        </table>

        <!-- Activity -->
        <div class="section-head">Activity description</div>
        <table class="grid">
            <tr><td style="min-height: 42px; line-height: 1.6;">{!! nl2br(e($requisition->purpose)) !!}</td></tr>
        </table>

        <table class="grid">
            <tr>
                <td class="lbl" style="width: 20%;">Date of activity</td>
                <td style="width: 30%;">{{ $requisition->date_of_activity?->format('d M Y') ?: '—' }}</td>
                <td class="lbl" style="width: 24%;">Activity budgeted for</td>
                <td class="num">{{ $requisition->activity_budget !== null ? number_format((float) $requisition->activity_budget, 2) . ' ' . $requisition->currency : '—' }}</td>
            </tr>
        </table>

        <!-- Items -->
        <div class="section-head">Item description — one line for each activity</div>
        <table class="grid items">
            <tr>
                <th style="width: 40%;">Item description</th>
                <th class="num" style="width: 20%;">Estimated cost</th>
                <th class="num" style="width: 20%;">Actual cost</th>
                <th class="num" style="width: 20%;">Balance</th>
            </tr>
            @foreach($requisition->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="num">{{ number_format((float) $item->total_price, 2) }}</td>
                <td class="num">{{ $item->actual_cost !== null ? number_format((float) $item->actual_cost, 2) : '—' }}</td>
                <td class="num">{{ $item->actual_cost !== null ? number_format((float) $item->total_price - (float) $item->actual_cost, 2) : '—' }}</td>
            </tr>
            @endforeach
            <tr class="total">
                <td class="num">TOTAL</td>
                <td class="num">{{ number_format((float) $requisition->total_amount, 2) }}</td>
                <td class="num">{{ number_format((float) $requisition->actual_total, 2) }}</td>
                <td class="num">{{ number_format((float) $requisition->unspent_funds, 2) }}</td>
            </tr>
        </table>

        <!-- Signatures -->
        <div class="section-head">Authorisation</div>
        <table class="grid">
            <tr>
                <td class="sig">
                    @php($signatureBinary = $requisition->signature ? @file_get_contents(storage_path('app/private/' . $requisition->signature->path)) : false)
                    @if($signatureBinary)
                        <img class="sig-img" src="{{ 'data:image/png;base64,' . base64_encode($signatureBinary) }}" alt="signature">
                    @endif
                    <div class="line">Requested by / Signature &nbsp;·&nbsp; {{ $requisition->signed_by_name ?: ($requisition->requested_by_name ?: '—') }}</div>
                    <div class="line">Date: {{ $requisition->signed_at?->format('d M Y') ?: ($requisition->date->format('d M Y')) }}</div>
                </td>
                <td class="sig">
                    <div class="line">Reviewed by (Technical Lead)</div>
                    <div class="line">Date: {{ $requisition->approvals->firstWhere('stage', 'technical_lead')?->decided_at?->format('d M Y') }}</div>
                </td>
                <td class="sig">
                    <div class="line">Authorised by (Managing Director)</div>
                    <div class="line">Date: {{ $requisition->approvals->firstWhere('stage', 'md')?->decided_at?->format('d M Y') }}</div>
                </td>
            </tr>
        </table>

        <!-- Accountability + Finance -->
        <table class="grid">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div class="section-head" style="margin: 0;">Accountability</div>
                    <table class="grid" style="margin: 0;">
                        <tr><td class="lbl">Submitted by</td><td>{{ $requisition->signed_by_name ?: '—' }}</td></tr>
                        <tr><td class="lbl">Date</td><td>{{ $requisition->signed_at?->format('d M Y') ?: '—' }}</td></tr>
                        <tr><td class="lbl">Approved by</td><td>{{ $requisition->approvals->firstWhere('stage', 'hr')?->user?->name ?: '—' }}</td></tr>
                        <tr><td class="lbl">Unspent funds</td><td class="num">{{ number_format((float) $requisition->unspent_funds, 2) }} {{ $requisition->currency }}</td></tr>
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="section-head" style="margin: 0;">Finance &amp; Ops Dept.</div>
                    <table class="grid" style="margin: 0;">
                        <tr><td class="lbl">Reviewed by (FIN)</td><td>____________________</td></tr>
                        <tr><td class="lbl">Received by</td><td>_______________ Date: ________</td></tr>
                        <tr><td class="lbl">Balance returned ({{ $requisition->currency }})</td><td class="num">{{ number_format((float) $requisition->unspent_funds, 2) }}</td></tr>
                        <tr><td class="lbl">Receipt No. / JV No.</td><td>____________ / ____________</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="grid">
            <tr>
                <td style="text-align: right;">
                    <span class="pill">Posted to: Advance / Expense</span>
                    &nbsp;&nbsp;
                    <span class="pill">Status: {{ strtoupper(str_replace('_', ' ', $requisition->status)) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        This form is generated by the Biiteek Management System and is valid without a physical stamp.
        {{ $requisition->requisition_number }}-VER
        · Prepared by {{ $requisition->preparedBy?->employee?->full_name ?? $requisition->preparedBy?->name ?? 'Biiteek' }}.
        Financial corrections are recorded as adjustments — this document is never altered after signing.
    </div>
</body>
</html>
