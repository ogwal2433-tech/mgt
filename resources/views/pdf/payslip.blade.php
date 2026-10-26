<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip {{ $line->payslip_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11.5px; color: #111827; margin: 0; }
        .header { border-bottom: 3px solid #16a34a; padding-bottom: 12px; margin-bottom: 16px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #065f46; letter-spacing: .5px; }
        .muted { color: #6b7280; font-size: 10.5px; }
        .badge { display: inline-block; background: #16a34a; color: #fff; padding: 3px 9px; border-radius: 3px; font-size: 10px; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 9px; text-align: left; }
        th { background: #f9fafb; width: 30%; font-weight: 600; color: #374151; }
        .items th { background: #065f46; color: #fff; width: auto; }
        .items td.num, .items th.num { text-align: right; }
        .total td { font-weight: bold; background: #f0fdf4; }
        .net td { font-weight: bold; background: #065f46; color: #fff; font-size: 13px; }
        .footer { margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 9.5px; color: #6b7280; }
        .two-col { width: 100%; }
        .two-col td { border: none; padding: 2px 0; }
    </style>
</head>
<body>
    <div class="header">
        <table style="border: none;">
            <tr>
                <td style="border: none;">
                    <h1>{{ $company['name'] }}</h1>
                    <div class="muted">{{ $company['address'] }} · {{ $company['email'] }}</div>
                </td>
                <td style="border: none; text-align: right;">
                    <div class="badge">OFFICIAL PAYSLIP</div>
                    <div style="margin-top: 8px; font-size: 14px; font-weight: bold;">{{ $line->payslip_number }}</div>
                    <div class="muted">Period: {{ $period->name }} · Generated {{ $line->payslip_generated_at?->format('d M Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <tr>
            <th>Employee</th>
            <td>{{ $line->employee_name }} ({{ $line->employee_code }})</td>
            <th>Pay period</th>
            <td>{{ $period->name }} ({{ $period->start_date->format('d M') }} – {{ $period->end_date->format('d M Y') }})</td>
        </tr>
        <tr>
            <th>Payment date</th>
            <td>{{ $payment->payment_date?->format('d M Y') ?? '—' }}</td>
            <th>Payment method</th>
            <td>{{ ucwords(str_replace('_', ' ', (string) ($payment->payment_method ?? '—'))) }}</td>
        </tr>
        <tr>
            <th>Payment reference</th>
            <td>{{ $payment->transaction_reference ?? $payment->payment_number ?? '—' }}</td>
            <th>Department</th>
            <td>{{ $line->employee?->department?->name ?? '—' }}</td>
        </tr>
    </table>

    <h2 style="font-size: 12px; margin: 14px 0 4px; color: #065f46; text-transform: uppercase; letter-spacing: 1px;">Earnings</h2>
    <table class="items">
        <tr><th>Description</th><th class="num">Amount ({{ $currency }})</th></tr>
        <tr><td>Basic salary</td><td class="num">{{ number_format((float) $line->basic_salary, 2) }}</td></tr>
        @foreach(($line->breakdown['earnings'] ?? []) as $earning)
        <tr><td>{{ $earning['name'] }}</td><td class="num">{{ number_format((float) $earning['amount'], 2) }}</td></tr>
        @endforeach
        <tr class="total"><td>Gross pay</td><td class="num">{{ number_format((float) $line->gross_amount, 2) }}</td></tr>
    </table>

    <h2 style="font-size: 12px; margin: 14px 0 4px; color: #065f46; text-transform: uppercase; letter-spacing: 1px;">Deductions</h2>
    <table class="items">
        <tr><th>Description</th><th class="num">Amount ({{ $currency }})</th></tr>
        @foreach(($line->breakdown['deductions'] ?? []) as $deduction)
        <tr><td>{{ $deduction['name'] }}</td><td class="num">{{ number_format((float) $deduction['amount'], 2) }}</td></tr>
        @endforeach
        @if(empty($line->breakdown['deductions']))
        <tr><td colspan="2" class="muted">No deductions</td></tr>
        @endif
        <tr class="total"><td>Total deductions</td><td class="num">{{ number_format((float) $line->total_deductions, 2) }}</td></tr>
    </table>

    <table class="items">
        <tr class="net"><td>NET PAY</td><td class="num">{{ number_format((float) $line->net_amount, 2) }} {{ $currency }}</td></tr>
    </table>

    @if(!empty($line->breakdown['outstanding_advance']) && (float) $line->breakdown['outstanding_advance'] > 0)
    <p class="muted">Outstanding advance balance after this recovery: {{ number_format((float) $line->breakdown['outstanding_advance'], 2) }} {{ $currency }}</p>
    @endif

    <div class="footer">
        This payslip is generated automatically by the {{ $company['name'] }} Management System and is valid without a physical stamp.
        Verification reference: {{ $line->payslip_number }} / {{ $run->run_number }}.
        Salary corrections are issued as adjustments in a later period — this payslip is never altered.
    </div>
</body>
</html>
