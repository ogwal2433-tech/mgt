<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 0; }
        .header { border-bottom: 3px solid #16a34a; padding-bottom: 14px; margin-bottom: 20px; }
        .logo { max-height: 60px; }
        h1 { font-size: 20px; margin: 0 0 4px; color: #065f46; letter-spacing: .5px; }
        .muted { color: #6b7280; font-size: 11px; }
        .badge { display: inline-block; background: #16a34a; color: #fff; padding: 4px 10px; border-radius: 3px; font-size: 11px; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; width: 32%; font-weight: 600; color: #374151; }
        .amount { font-size: 18px; font-weight: bold; color: #065f46; }
        .footer { margin-top: 36px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 10px; color: #6b7280; }
        .signatures { margin-top: 48px; }
        .sig { width: 45%; display: inline-block; text-align: center; }
        .sig .line { border-top: 1px solid #111827; margin-top: 44px; padding-top: 6px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <table style="border: none;">
            <tr>
                <td style="border: none;">
                    <h1>{{ $company['name'] }}</h1>
                    <div class="muted">{{ $company['address'] }}</div>
                    <div class="muted">{{ $company['email'] }} &middot; {{ $company['phone'] }}</div>
                </td>
                <td style="border: none; text-align: right;">
                    <div class="badge">OFFICIAL PAYMENT RECEIPT</div>
                    <div style="margin-top: 10px; font-size: 16px; font-weight: bold;">{{ $receipt->receipt_number }}</div>
                    <div class="muted">Generated {{ $receipt->generated_at?->format('d M Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <tr>
            <th>Receipt number</th>
            <td>{{ $receipt->receipt_number }}</td>
            <th>Payment reference</th>
            <td>{{ $receipt->payment_reference ?: $receipt->payment?->payment_number }}</td>
        </tr>
        <tr>
            <th>Requisition number</th>
            <td>{{ $receipt->requisition?->requisition_number }}</td>
            <th>Payment date</th>
            <td>{{ $receipt->payment_date?->format('d M Y') }}</td>
        </tr>
        <tr>
            <th>Requester</th>
            <td>{{ $receipt->requester?->displayName() }} ({{ $receipt->requisition?->requesterEmployee?->employee_code }})</td>
            <th>Payee</th>
            <td>{{ $receipt->payee_name }}</td>
        </tr>
        <tr>
            <th>Payment method</th>
            <td>{{ ucwords(str_replace('_', ' ', (string) $receipt->payment_method)) }}</td>
            <th>Payment number</th>
            <td>{{ $receipt->payment?->payment_number }}</td>
        </tr>
        <tr>
            <th>Purpose</th>
            <td colspan="3">{{ $receipt->purpose }}</td>
        </tr>
        <tr>
            <th>Amount paid</th>
            <td colspan="3" class="amount">{{ $receipt->amount }} {{ $receipt->currency }}</td>
        </tr>
        <tr>
            <th>Remaining approved balance</th>
            <td colspan="3">{{ $receipt->remaining_balance }} {{ $receipt->currency }}</td>
        </tr>
        <tr>
            <th>Recorded by</th>
            <td colspan="3">{{ $receipt->generatedBy?->displayName() }}</td>
        </tr>
    </table>

    <div class="signatures">
        <div class="sig">
            <div class="line">Authorised Signature (HR Finance &amp; Accounts)</div>
        </div>
        <div class="sig" style="float: right;">
            <div class="line">Received By (Signature)</div>
        </div>
    </div>

    <div class="footer">
        This receipt is generated automatically by the {{ $company['name'] }} Management System and is valid without a physical stamp.
        Verification reference: {{ $receipt->receipt_number }} / {{ $receipt->payment?->payment_number }}.
        Financial corrections are recorded as reversals and never overwrite this document.
    </div>
</body>
</html>
