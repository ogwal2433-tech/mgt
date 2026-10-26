<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 0; }
        .header { border-bottom: 3px solid #16a34a; padding-bottom: 14px; margin-bottom: 18px; }
        h1 { font-size: 22px; margin: 0 0 4px; color: #065f46; letter-spacing: .5px; }
        .muted { color: #6b7280; font-size: 11px; }
        .badge { display: inline-block; background: #065f46; color: #fff; padding: 4px 10px; border-radius: 3px; font-size: 11px; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #e5e7eb; padding: 7px 9px; text-align: left; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        .items th { background: #065f46; color: #fff; }
        .num { text-align: right; }
        .total td { font-weight: bold; background: #f0fdf4; }
        .footer { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <table style="border: none;">
            <tr>
                <td style="border: none;">
                    <h1>INVOICE</h1>
                    <div class="muted">{{ $company['name'] }} · {{ $company['address'] }}</div>
                    <div class="muted">{{ $company['email'] }} · {{ $company['phone'] }}</div>
                </td>
                <td style="border: none; text-align: right;">
                    <div class="badge">{{ strtoupper($invoice->status) }}</div>
                    <div style="margin-top: 10px; font-size: 16px; font-weight: bold;">{{ $invoice->invoice_number }}</div>
                    <div class="muted">Issued: {{ $invoice->issue_date->format('d M Y') }}</div>
                    <div class="muted">Due: {{ $invoice->due_date->format('d M Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <tr>
            <th style="width:22%;">Bill to</th>
            <td>
                <strong>{{ $invoice->client_name }}</strong><br>
                {{ $invoice->client_contact ?? '' }}<br>
                {{ $invoice->client_address ?? '' }}<br>
                {{ $invoice->client_email ?? '' }}
            </td>
            <th style="width:18%;">Project</th>
            <td>{{ $invoice->project?->name ?? '—' }}</td>
        </tr>
    </table>

    <table class="items">
        <tr>
            <th class="num" style="width:5%;">#</th>
            <th>Description</th>
            <th class="num" style="width:10%;">Qty</th>
            <th class="num" style="width:15%;">Unit price</th>
            <th class="num" style="width:15%;">Total</th>
        </tr>
        @foreach($invoice->items as $i => $item)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td>{{ $item->description }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
            <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
            <td class="num">{{ number_format((float) $item->total_price, 2) }}</td>
        </tr>
        @endforeach
        <tr class="total">
            <td colspan="4" class="num">Subtotal</td>
            <td class="num">{{ number_format((float) $invoice->subtotal, 2) }}</td>
        </tr>
        @if((float) $invoice->tax_amount > 0)
        <tr>
            <td colspan="4" class="num">Tax ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2), '0'), '.') }}%)</td>
            <td class="num">{{ number_format((float) $invoice->tax_amount, 2) }}</td>
        </tr>
        @endif
        <tr class="total">
            <td colspan="4" class="num">Total due</td>
            <td class="num">{{ number_format((float) $invoice->total_amount, 2) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td colspan="4" class="num">Paid to date</td>
            <td class="num">{{ number_format((float) $invoice->amount_paid, 2) }}</td>
        </tr>
        <tr class="total">
            <td colspan="4" class="num">Balance due</td>
            <td class="num">{{ number_format((float) $invoice->amount_due, 2) }} {{ $invoice->currency }}</td>
        </tr>
    </table>

    @if($invoice->terms)
    <h2 style="font-size: 12px; margin: 14px 0 4px; color: #065f46; text-transform: uppercase; letter-spacing: 1px;">Payment terms</h2>
    <p style="white-space: pre-line; margin: 0;">{{ $invoice->terms }}</p>
    @endif

    @if($invoice->notes)
    <h2 style="font-size: 12px; margin: 14px 0 4px; color: #065f46; text-transform: uppercase; letter-spacing: 1px;">Notes</h2>
    <p style="white-space: pre-line; margin: 0;">{{ $invoice->notes }}</p>
    @endif

    <div class="footer">
        Please pay by the due date to avoid service interruption.
        Verification reference: {{ $invoice->invoice_number }}.
        This invoice was generated by the {{ $company['name'] }} Management System.
    </div>
</body>
</html>
