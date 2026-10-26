<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\PayPeriod;
use App\Models\PayrollLine;
use App\Models\PayrollLineItem;
use App\Models\PayrollPayment;
use App\Models\PayrollRun;
use App\Models\Requisition;
use App\Models\SalaryComponent;
use App\Models\TaxBracket;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\NotificationService;
use App\Support\Sequence;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll computation and lifecycle.
 *
 *   gross  = basic salary + stipends/allowances
 *   net    = gross − taxes − advance recoveries − statutory − other deductions
 *
 * All arithmetic uses bcmath on DECIMAL(18,2) values — never floating point.
 * Every salary payment is tracked and every paid line gets a payslip delivered
 * by email and inside the employee's account.
 */
class PayrollService
{
    public function __construct(private readonly PdfService $pdf) {}

    /**
     * Compute (or recompute) the payroll run for a pay period.
     */
    public function compute(PayPeriod $period, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($period, $actor) {
            $run = PayrollRun::query()->where('pay_period_id', $period->id)->lockForUpdate()->first();

            if ($run && $run->isPaid()) {
                throw ValidationException::withMessages([
                    'period' => 'This payroll has already been paid. Use an adjustment run instead of recomputing.',
                ]);
            }

            if ($run && $run->status === 'closed') {
                throw ValidationException::withMessages([
                    'period' => 'This payroll is closed and cannot be recomputed.',
                ]);
            }

            $run ??= PayrollRun::create([
                'run_number' => Sequence::next('payroll_run', 'PAYR', 4),
                'pay_period_id' => $period->id,
                'status' => 'draft',
            ]);

            // Recomputation replaces the previous draft lines.
            $run->lines()->delete();

            $totals = ['basic' => '0.00', 'gross' => '0.00', 'deductions' => '0.00', 'net' => '0.00'];

            $employees = Employee::active()
                ->whereNotNull('basic_salary')
                ->orderBy('employee_code')
                ->get();

            foreach ($employees as $employee) {
                $line = $this->computeLine($run, $employee, $period);
                if (! $line) {
                    continue;
                }

                $totals['basic'] = Money::add($totals['basic'], $line->basic_salary);
                $totals['gross'] = Money::add($totals['gross'], $line->gross_amount);
                $totals['deductions'] = Money::add($totals['deductions'], $line->total_deductions);
                $totals['net'] = Money::add($totals['net'], $line->net_amount);
            }

            $run->update([
                'status' => 'computed',
                'total_basic' => $totals['basic'],
                'total_gross' => $totals['gross'],
                'total_deductions' => $totals['deductions'],
                'total_net' => $totals['net'],
                'computed_by' => $actor->id,
                'computed_at' => now(),
            ]);

            $period->update(['status' => 'computed']);

            AuditLogger::log('payroll.computed', PayrollRun::class, $run->id,
                "Computed payroll {$run->run_number} for {$period->name}",
                null,
                ['employees' => $run->lines()->count(), 'total_net' => $totals['net']]);

            return $run->refresh();
        });
    }

    public function approve(PayrollRun $run, User $actor): PayrollRun
    {
        return DB::transaction(function () use ($run, $actor) {
            /** @var PayrollRun $run */
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if (! in_array($run->status, ['draft', 'computed'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only a computed payroll can be approved.',
                ]);
            }

            if ($run->lines()->count() === 0) {
                throw ValidationException::withMessages([
                    'status' => 'There are no payroll lines to approve.',
                ]);
            }

            $run->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $run->period->update(['status' => 'approved']);

            AuditLogger::log('payroll.approved', PayrollRun::class, $run->id,
                "Approved payroll {$run->run_number}", ['status' => 'computed'], ['status' => 'approved']);

            return $run->refresh();
        });
    }

    /**
     * Record salary payments for the run, generate payslips and deliver them.
     */
    public function markPaid(
        PayrollRun $run,
        User $actor,
        string $paymentDate,
        string $method,
        ?string $reference,
        ?string $notes = null,
    ): PayrollRun {
        return DB::transaction(function () use ($run, $actor, $paymentDate, $method, $reference, $notes) {
            /** @var PayrollRun $run */
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Payroll must be approved before payments can be recorded.',
                ]);
            }

            if ($run->payments()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Salary payments have already been recorded for this payroll.',
                ]);
            }

            $totalPaid = '0.00';

            foreach ($run->lines()->where('status', '!=', 'paid')->get() as $line) {
                // The unique index on payroll_line_id blocks duplicate salary payments.
                $payment = PayrollPayment::create([
                    'payment_number' => Sequence::next('salary_payment', 'SAL', 4),
                    'payroll_run_id' => $run->id,
                    'payroll_line_id' => $line->id,
                    'employee_id' => $line->employee_id,
                    'amount' => Money::normalize($line->net_amount),
                    'currency' => Settings::get('finance.currency', 'UGX'),
                    'payment_date' => $paymentDate,
                    'payment_method' => $method,
                    'transaction_reference' => $reference,
                    'recorded_by' => $actor->id,
                    'status' => 'completed',
                    'notes' => $notes,
                ]);

                $line->update(['status' => 'paid']);
                $totalPaid = Money::add($totalPaid, $payment->amount);

                AuditLogger::log('payroll.paid', PayrollPayment::class, $payment->id,
                    "Salary payment {$payment->payment_number} to {$line->employee_name}",
                    null, ['amount' => $payment->amount, 'reference' => $reference]);
            }

            $run->update([
                'status' => 'paid',
                'total_paid' => $totalPaid,
                'paid_at' => now(),
            ]);
            $run->period->update(['status' => 'paid']);

            AuditLogger::log('payroll.marked_paid', PayrollRun::class, $run->id,
                "Marked payroll {$run->run_number} as paid", ['status' => 'approved'], ['total_paid' => $totalPaid]);

            // Payslips: PDF + email + in-account, for every paid line.
            foreach ($run->lines()->get() as $line) {
                $this->issuePayslip($line);
            }

            return $run->refresh();
        });
    }

    public function close(PayrollRun $run, User $actor): PayrollRun
    {
        $run->update(['status' => 'closed', 'closed_at' => now()]);
        $run->period->update(['status' => 'closed']);

        AuditLogger::log('payroll.closed', PayrollRun::class, $run->id, "Closed payroll {$run->run_number}");

        return $run->refresh();
    }

    /**
     * Generate the payslip PDF, store it, notify the employee in-app and by email.
     */
    public function issuePayslip(PayrollLine $line): PayrollLine
    {
        if ($line->payslip_document_id) {
            return $line;
        }

        $line->loadMissing(['run.period', 'items', 'payment', 'employee.user']);

        $document = $this->pdf->storePayslipPdf($line);

        $line->update([
            'payslip_number' => Sequence::next('payslip', 'PAYSLIP', 4),
            'payslip_document_id' => $document->id,
            'payslip_generated_at' => now(),
        ]);

        $employee = $line->employee;
        $user = $employee?->user;

        if ($user) {
            $periodName = $line->run->period->name;
            $reference = $line->payment?->transaction_reference ?: $line->payment?->payment_number;

            NotificationService::notify(
                $user,
                'payslip.generated',
                "Payslip {$line->payslip_number} available",
                "Your payslip for {$periodName} has been generated. Net pay: {$line->net_amount}. Download it from My Payslips.",
                '/my-payslips',
                ['payslip_number' => $line->payslip_number],
                false
            );

            $body = "Hello {$line->employee_name},\n\nYour salary for {$periodName} has been paid.\n\n"
                . "Net pay: {$line->net_amount}\nPayment reference: {$reference}\n\n"
                . 'Your payslip is attached to your account under My Payslips.';

            $delivery = NotificationService::email(
                $user,
                "Your payslip for {$periodName}",
                $body,
                'payslip',
                ['Payslip' => (string) $line->payslip_number, 'Net pay' => (string) $line->net_amount],
                PayrollLine::class,
                $line->id
            );

            $line->update([
                'payslip_delivery_status' => $delivery->status === 'sent' ? 'sent' : 'failed',
                'payslip_emailed_at' => $delivery->sent_at,
                'payslip_email_error' => $delivery->error,
            ]);
        } else {
            // No linked user account yet — the payslip waits in the system.
            $line->update(['payslip_delivery_status' => 'no_account']);
        }

        return $line->refresh();
    }

    public function retryPayslipEmail(PayrollLine $line): PayrollLine
    {
        $user = $line->employee?->user;
        if (! $user) {
            return $line;
        }

        $delivery = NotificationService::email(
            $user,
            "Your payslip for {$line->run->period->name}",
            "Hello {$line->employee_name},\n\nYour payslip {$line->payslip_number} is available in your account under My Payslips.",
            'payslip_retry',
            ['Payslip' => (string) $line->payslip_number],
            PayrollLine::class,
            $line->id
        );

        return $line->update([
            'payslip_delivery_status' => $delivery->status === 'sent' ? 'sent' : 'failed',
            'payslip_emailed_at' => $delivery->sent_at,
            'payslip_email_error' => $delivery->error,
        ]) ? $line->refresh() : $line;
    }

    /**
     * Outstanding advance balance for an employee:
     * Σ(amount_paid − amount_accounted − amount_refunded) on open advance/accountable requisitions.
     */
    public function outstandingAdvanceFor(Employee $employee): string
    {
        $userIds = User::where('employee_id', $employee->id)->pluck('id');

        if ($userIds->isEmpty()) {
            return '0.00';
        }

        $total = '0.00';

        $requisitions = Requisition::whereIn('requester_user_id', $userIds)
            ->where(function ($q) {
                $q->where('is_advance', true)->orWhere('accountability_required', true);
            })
            ->whereNotIn('status', ['CLOSED', 'CANCELLED', 'DRAFT'])
            ->get();

        foreach ($requisitions as $requisition) {
            $balance = Money::sub(
                Money::sub((string) $requisition->amount_paid, (string) $requisition->amount_accounted),
                (string) $requisition->amount_refunded
            );

            if (Money::compare($balance, '0.00') > 0) {
                $total = Money::add($total, $balance);
            }
        }

        return $total;
    }

    /**
     * Progressive monthly tax from the configured brackets.
     */
    public function computeTax(string $taxable): string
    {
        if (! Settings::get('payroll.tax_enabled', true)) {
            return '0.00';
        }

        $tax = '0.00';

        foreach (TaxBracket::active()->get() as $bracket) {
            $lower = Money::normalize($bracket->monthly_lower);
            $upper = $bracket->monthly_upper !== null ? Money::normalize($bracket->monthly_upper) : null;

            if (Money::compare($taxable, $lower) <= 0) {
                continue;
            }

            $bandTop = $upper === null ? $taxable : Money::min($taxable, $upper);
            $bandAmount = Money::sub($bandTop, $lower);

            if (Money::compare($bandAmount, '0.00') > 0) {
                // band amount × rate% (bcmath, no floating point)
                $tax = Money::add($tax, bcdiv(bcmul($bandAmount, Money::normalize($bracket->rate_percent), 6), '100', 2));
            }
        }

        return $tax;
    }

    private function computeLine(PayrollRun $run, Employee $employee, PayPeriod $period): ?PayrollLine
    {
        $basic = Money::normalize($employee->basic_salary ?? 0);
        if (Money::compare($basic, '0.00') <= 0) {
            return null;
        }

        $earnings = [];
        $deductions = [];
        $nonTaxableEarnings = '0.00';
        $gross = $basic;
        $statutory = '0.00';
        $otherDeductions = '0.00';

        $components = SalaryComponent::active()->orderBy('sort_order')->get();

        foreach ($components as $component) {
            if (in_array($component->code, ['TAX', 'ADVANCE'], true)) {
                continue; // computed separately below
            }

            $amount = $this->componentAmount($component, $employee, $basic, $period);

            if (Money::compare($amount, '0.00') === 0) {
                continue;
            }

            if ($component->type === 'earning') {
                $gross = Money::add($gross, $amount);
                if (! $component->is_taxable) {
                    $nonTaxableEarnings = Money::add($nonTaxableEarnings, $amount);
                }
                $earnings[] = ['code' => $component->code, 'name' => $component->name, 'amount' => $amount, 'type' => 'earning'];
            } else {
                if ($component->is_statutory) {
                    $statutory = Money::add($statutory, $amount);
                } else {
                    $otherDeductions = Money::add($otherDeductions, $amount);
                }
                $deductions[] = ['code' => $component->code, 'name' => $component->name, 'amount' => $amount, 'type' => 'deduction'];
            }
        }

        // Tax on taxable pay (gross − non-taxable earnings).
        $taxable = Money::sub($gross, $nonTaxableEarnings);
        $tax = $this->computeTax($taxable);
        if (Money::gt($tax, '0.00')) {
            $deductions[] = ['code' => 'TAX', 'name' => 'Income tax (PAYE)', 'amount' => $tax, 'type' => 'deduction'];
        }

        // Advance recovery — capped so net pay never goes negative.
        $outstanding = $this->outstandingAdvanceFor($employee);
        $recovery = $this->advanceRecovery($outstanding);
        $alreadyDeducted = Money::add(Money::add($tax, $statutory), $otherDeductions);
        $maxRecovery = Money::sub($gross, $alreadyDeducted);
        if (Money::compare($maxRecovery, '0.00') < 0) {
            $maxRecovery = '0.00';
        }
        $recovery = Money::min($recovery, $maxRecovery);
        if (Money::gt($recovery, '0.00')) {
            $deductions[] = [
                'code' => 'ADVANCE',
                'name' => 'Advance recovery',
                'amount' => $recovery,
                'type' => 'deduction',
                'source_type' => 'outstanding_advance',
                'outstanding' => $outstanding,
            ];
        }

        $totalDeductions = Money::sum(array_map(fn ($d) => $d['amount'], $deductions));
        $net = Money::sub($gross, $totalDeductions);

        $line = PayrollLine::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->full_name,
            'basic_salary' => $basic,
            'gross_amount' => $gross,
            'tax_amount' => $tax,
            'advance_amount' => $recovery,
            'statutory_amount' => $statutory,
            'other_deduction_amount' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_amount' => $net,
            'breakdown' => [
                'earnings' => $earnings,
                'deductions' => $deductions,
                'taxable_pay' => $taxable,
                'outstanding_advance' => $outstanding,
            ],
            'status' => 'unpaid',
        ]);

        foreach ($earnings as $item) {
            PayrollLineItem::create([
                'payroll_line_id' => $line->id,
                'payroll_run_id' => $run->id,
                'component_code' => $item['code'],
                'component_name' => $item['name'],
                'type' => 'earning',
                'amount' => $item['amount'],
            ]);
        }

        foreach ($deductions as $item) {
            PayrollLineItem::create([
                'payroll_line_id' => $line->id,
                'payroll_run_id' => $run->id,
                'component_code' => $item['code'],
                'component_name' => $item['name'],
                'type' => 'deduction',
                'amount' => $item['amount'],
                'source_type' => $item['source_type'] ?? ($item['code'] === 'TAX' ? 'tax_brackets' : null),
            ]);
        }

        return $line;
    }

    private function componentAmount(SalaryComponent $component, Employee $employee, string $basic, PayPeriod $period): string
    {
        $compensation = EmployeeCompensation::query()
            ->where('employee_id', $employee->id)
            ->where('salary_component_id', $component->id)
            ->where(function ($q) use ($period) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $period->end_date);
            })
            ->where(function ($q) use ($period) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->start_date);
            })
            ->orderByDesc('id')
            ->first();

        if ($compensation) {
            return Money::normalize($compensation->amount);
        }

        return match ($component->calculation_type) {
            'percent_basic' => bcdiv(bcmul($basic, Money::normalize($component->default_amount), 6), '100', 2),
            'percent_gross' => bcdiv(bcmul($basic, Money::normalize($component->default_amount), 6), '100', 2),
            default => Money::normalize($component->default_amount),
        };
    }

    private function advanceRecovery(string $outstanding): string
    {
        if (Money::compare($outstanding, '0.00') <= 0) {
            return '0.00';
        }

        $mode = Settings::get('payroll.advance_recovery', 'percent');

        if ($mode === 'full') {
            return $outstanding;
        }

        $percent = Money::normalize(Settings::get('payroll.advance_recovery_percent', 50));

        return bcdiv(bcmul($outstanding, $percent, 6), '100', 2);
    }
}
