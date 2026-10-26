<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Payment;
use App\Models\PayrollPayment;
use App\Models\Refund;
use App\Support\Money;

/**
 * Finance reporting: cashbook (unified cash ledger) and receivables aging.
 *
 * All figures come from real transaction tables:
 *   in  : invoice payments (client receipts), refunds received
 *   out : requisition disbursements, salary payments, refunds paid out
 */
class FinanceReportService
{
    /**
     * Chronological cashbook with a running balance for the period.
     *
     * @return array{entries: list<array<string, mixed>>, opening_balance: string, closing_balance: string,
     *              total_in: string, total_out: string}
     */
    public function cashbook(string $from, string $to, ?string $direction = null): array
    {
        $entries = [];

        foreach (InvoicePayment::whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->get() as $payment) {
            $entries[] = [
                'date' => $payment->payment_date->toDateString(),
                'reference' => $payment->payment_number,
                'description' => 'Client receipt — ' . ($payment->invoice?->client_name ?? $payment->received_from) . ' (' . $payment->invoice?->invoice_number . ')',
                'category' => 'client_receipt',
                'method' => $payment->payment_method,
                'money_in' => (string) $payment->amount,
                'money_out' => '0.00',
            ];
        }

        foreach (Payment::where('status', 'completed')->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->get() as $payment) {
            // Reversals are negative amounts — money returning to the company.
            $isReversal = Money::compare((string) $payment->amount, '0.00') < 0 || $payment->is_reversal;
            $amount = Money::normalize($isReversal ? bcabs((string) $payment->amount) : $payment->amount);

            $entries[] = [
                'date' => $payment->payment_date->toDateString(),
                'reference' => $payment->payment_number,
                'description' => ($isReversal ? 'Reversal — ' : 'Disbursement — ') . $payment->payee_name . ' (' . $payment->requisition?->requisition_number . ')',
                'category' => $isReversal ? 'reversal' : 'disbursement',
                'method' => $payment->payment_method,
                'money_in' => $isReversal ? $amount : '0.00',
                'money_out' => $isReversal ? '0.00' : $amount,
            ];
        }

        foreach (PayrollPayment::whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->get() as $payment) {
            $entries[] = [
                'date' => $payment->payment_date->toDateString(),
                'reference' => $payment->payment_number,
                'description' => 'Salary payment — ' . $payment->employee?->full_name,
                'category' => 'payroll',
                'method' => $payment->payment_method,
                'money_in' => '0.00',
                'money_out' => (string) $payment->amount,
            ];
        }

        foreach (Refund::whereDate('refund_date', '>=', $from)->whereDate('refund_date', '<=', $to)->get() as $refund) {
            $entries[] = [
                'date' => $refund->refund_date->toDateString(),
                'reference' => $refund->reference ?: 'REFUND-' . $refund->id,
                'description' => 'Advance refund — ' . $refund->requisition?->requisition_number,
                'category' => 'refund',
                'method' => $refund->method,
                'money_in' => (string) $refund->amount,
                'money_out' => '0.00',
            ];
        }

        // Balance brought forward (everything before the period).
        $opening = '0.00';
        foreach (InvoicePayment::where('payment_date', '<', $from)->get() as $payment) {
            $opening = Money::add($opening, $payment->amount);
        }
        foreach (Refund::where('refund_date', '<', $from)->get() as $refund) {
            $opening = Money::add($opening, $refund->amount);
        }
        foreach (Payment::where('status', 'completed')->where('payment_date', '<', $from)->get() as $payment) {
            $opening = Money::sub($opening, $payment->amount);
        }
        foreach (PayrollPayment::where('payment_date', '<', $from)->get() as $payment) {
            $opening = Money::sub($opening, $payment->amount);
        }

        usort($entries, fn ($a, $b) => [$a['date'], $a['reference']] <=> [$b['date'], $b['reference']]);

        if ($direction === 'in') {
            $entries = array_values(array_filter($entries, fn ($e) => Money::compare($e['money_in'], '0.00') > 0));
        } elseif ($direction === 'out') {
            $entries = array_values(array_filter($entries, fn ($e) => Money::compare($e['money_out'], '0.00') > 0));
        }

        $balance = $opening;
        $totalIn = '0.00';
        $totalOut = '0.00';
        $categoryTotals = [];

        foreach ($entries as &$entry) {
            $balance = Money::add(Money::sub($balance, $entry['money_out']), $entry['money_in']);
            $entry['balance'] = $balance;
            $totalIn = Money::add($totalIn, $entry['money_in']);
            $totalOut = Money::add($totalOut, $entry['money_out']);

            $category = $entry['category'];
            $categoryTotals[$category] ??= ['money_in' => '0.00', 'money_out' => '0.00', 'count' => 0];
            $categoryTotals[$category]['money_in'] = Money::add($categoryTotals[$category]['money_in'], $entry['money_in']);
            $categoryTotals[$category]['money_out'] = Money::add($categoryTotals[$category]['money_out'], $entry['money_out']);
            $categoryTotals[$category]['count']++;
        }
        unset($entry);

        return [
            'entries' => $entries,
            'opening_balance' => $opening,
            'closing_balance' => $balance,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'category_totals' => $categoryTotals,
        ];
    }

    /**
     * Receivables aging: unpaid invoice balances grouped by age bucket.
     *
     * @return array{buckets: array<string, string>, rows: list<array<string, mixed>>, totals: array<string, string>, grand_total: string}
     */
    public function aging(?string $asOf = null): array
    {
        $asOfDate = $asOf ? \Carbon\Carbon::parse($asOf) : now();

        $bucketLabels = [
            'current' => 'Current (not yet due)',
            '1_30' => '1 – 30 days overdue',
            '31_60' => '31 – 60 days overdue',
            '61_90' => '61 – 90 days overdue',
            '90_plus' => 'Over 90 days overdue',
        ];

        $rows = [];
        $totals = array_fill_keys(array_keys($bucketLabels), '0.00');
        $grand = '0.00';

        $invoices = Invoice::query()
            ->where('status', '!=', 'void')
            ->whereColumn('amount_due', '>', 0)
            ->orderBy('client_name')
            ->get();

        foreach ($invoices as $invoice) {
            $bucket = $this->bucketFor($invoice, $asOfDate);
            $rows[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'client_name' => $invoice->client_name,
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'days_overdue' => (int) $invoice->due_date->startOfDay()->diffInDays($asOfDate->startOfDay(), false),
                'total_amount' => (string) $invoice->total_amount,
                'amount_due' => (string) $invoice->amount_due,
                'currency' => $invoice->currency,
                'bucket' => $bucket,
            ];

            $totals[$bucket] = Money::add($totals[$bucket], (string) $invoice->amount_due);
            $grand = Money::add($grand, (string) $invoice->amount_due);
        }

        return [
            'buckets' => $bucketLabels,
            'rows' => $rows,
            'totals' => $totals,
            'grand_total' => $grand,
        ];
    }

    /**
     * Income / expense summary for a period.
     *
     * @return array<string, string|int>
     */
    public function summary(string $from, string $to): array
    {
        $invoiced = Invoice::whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)->where('status', '!=', 'void')->sum('total_amount');
        $collected = InvoicePayment::whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->sum('amount');
        $outstanding = Invoice::where('status', '!=', 'void')->sum('amount_due');
        $disbursed = Payment::where('status', 'completed')->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->sum('amount');
        $payroll = PayrollPayment::whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->sum('amount');
        $refundsIn = Refund::whereDate('refund_date', '>=', $from)->whereDate('refund_date', '<=', $to)->sum('amount');
        $invoiceCount = Invoice::whereDate('issue_date', '>=', $from)->whereDate('issue_date', '<=', $to)->where('status', '!=', 'void')->count();
        $overdueCount = Invoice::where('status', '!=', 'void')
            ->whereColumn('amount_due', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        $netCash = Money::sub(Money::add((string) $collected, (string) $refundsIn), Money::add((string) $disbursed, (string) $payroll));

        $reversalsIn = Payment::where('status', 'completed')
            ->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->get()
            ->filter(fn ($p) => Money::compare((string) $p->amount, '0.00') < 0 || $p->is_reversal)
            ->reduce(fn ($carry, $p) => Money::add($carry, bcabs((string) $p->amount)), '0.00');

        return [
            'invoiced' => Money::normalize($invoiced),
            'collected' => Money::normalize($collected),
            'outstanding' => Money::normalize($outstanding),
            'disbursed' => Money::normalize($disbursed),
            'payroll' => Money::normalize($payroll),
            'refunds_in' => Money::normalize($refundsIn),
            'reversals_in' => $reversalsIn,
            'net_cash_flow' => $netCash,
            'invoice_count' => $invoiceCount,
            'overdue_count' => $overdueCount,
            'categories' => [
                'Client receipts' => Money::normalize($collected),
                'Advance refunds' => Money::normalize($refundsIn),
                'Payment reversals' => $reversalsIn,
                'Requisition disbursements' => Money::normalize($disbursed),
                'Salary payments' => Money::normalize($payroll),
            ],
        ];
    }

    private function bucketFor(Invoice $invoice, \Carbon\Carbon $asOf): string
    {
        // Positive = days overdue (due date already passed).
        $overdue = (int) $invoice->due_date->startOfDay()->diffInDays($asOf->startOfDay(), false);

        return match (true) {
            $overdue <= 0 => 'current',
            $overdue <= 30 => '1_30',
            $overdue <= 60 => '31_60',
            $overdue <= 90 => '61_90',
            default => '90_plus',
        };
    }
}
