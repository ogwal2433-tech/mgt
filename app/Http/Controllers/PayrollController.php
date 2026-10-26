<?php

namespace App\Http\Controllers;

use App\Models\PayPeriod;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Services\PayrollService;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_VIEW)) {
            abort(403);
        }

        $runs = PayrollRun::with(['period'])->orderByDesc('id')->get()->map(fn ($run) => [
            'id' => $run->id,
            'run_number' => $run->run_number,
            'period' => $run->period?->name,
            'status' => $run->status,
            'total_basic' => (string) $run->total_basic,
            'total_gross' => (string) $run->total_gross,
            'total_deductions' => (string) $run->total_deductions,
            'total_net' => (string) $run->total_net,
            'total_paid' => (string) $run->total_paid,
            'computed_at' => $run->computed_at?->toIso8601String(),
            'approved_at' => $run->approved_at?->toIso8601String(),
            'paid_at' => $run->paid_at?->toIso8601String(),
        ]);

        $periods = PayPeriod::orderByDesc('year')->orderByDesc('month')->get();

        return Inertia::render('Payroll/Index', [
            'runs' => $runs,
            'periods' => $periods,
            'components' => SalaryComponent::active()->orderBy('sort_order')->get(),
            'can' => [
                'run' => $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_RUN),
                'approve' => $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_APPROVE),
                'pay' => $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_PAY),
            ],
        ]);
    }

    public function storePeriod(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_RUN)) {
            abort(403);
        }

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'pay_date' => ['nullable', 'date'],
        ]);

        $period = PayPeriod::firstOrCreate(
            ['year' => $data['year'], 'month' => $data['month']],
            [
                'name' => sprintf('%04d-%02d', $data['year'], $data['month']),
                'start_date' => sprintf('%04d-%02d-01', $data['year'], $data['month']),
                'end_date' => \Carbon\Carbon::create($data['year'], $data['month'], 1)->endOfMonth()->toDateString(),
                'pay_date' => $data['pay_date'] ?? \Carbon\Carbon::create($data['year'], $data['month'], 1)->endOfMonth()->toDateString(),
                'status' => 'open',
                'created_by' => $request->user()->id,
            ]
        );

        return back()->with('success', "Pay period {$period->name} is ready.");
    }

    public function compute(Request $request, PayPeriod $period)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_RUN)) {
            abort(403);
        }

        $this->payroll->compute($period, $request->user());

        return back()->with('success', "Payroll computed for {$period->name}.");
    }

    public function show(PayrollRun $run, Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_VIEW)) {
            abort(403);
        }

        $run->load(['period', 'lines.items', 'lines.payment', 'lines.employee:id,employee_code,first_name,last_name', 'payments.employee:id,employee_code,first_name,last_name', 'payments.recorder:id,username']);

        return Inertia::render('Payroll/Show', [
            'run' => $run,
            'can' => [
                'run' => $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_RUN),
                'approve' => $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_APPROVE),
                'pay' => $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_PAY),
            ],
        ]);
    }

    public function approve(Request $request, PayrollRun $run)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_APPROVE)) {
            abort(403);
        }

        $this->payroll->approve($run, $request->user());

        return back()->with('success', "Payroll {$run->run_number} approved.");
    }

    public function pay(Request $request, PayrollRun $run)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_PAY)) {
            abort(403);
        }

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->payroll->markPaid(
            $run,
            $request->user(),
            $data['payment_date'],
            $data['payment_method'],
            $data['transaction_reference'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with('success', 'Salary payments recorded. Payslips generated and delivered.');
    }

    public function close(Request $request, PayrollRun $run)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_APPROVE)) {
            abort(403);
        }

        $this->payroll->close($run, $request->user());

        return back()->with('success', "Payroll {$run->run_number} closed.");
    }

    public function retryPayslip(Request $request, PayrollLine $line)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::PAYROLL_PAY)) {
            abort(403);
        }

        $this->payroll->retryPayslipEmail($line);

        return back()->with('success', 'Payslip email resent.');
    }

    public function myPayslips(Request $request)
    {
        $user = $request->user();

        $lines = PayrollLine::with(['run.period', 'payment'])
            ->whereHas('employee', fn ($q) => $q->where('id', $user->employee_id))
            ->whereNotNull('payslip_number')
            ->orderByDesc('id')
            ->paginate(12);

        return Inertia::render('Payroll/MyPayslips', ['payslips' => $lines]);
    }

    public function payslipPdf(Request $request, PayrollLine $line)
    {
        $user = $request->user();

        $isOwner = $user->employee_id === $line->employee_id;
        $canView = $user->hasPermission(\App\Support\Permissions::PAYROLL_VIEW);

        if (! $isOwner && ! $canView) {
            abort(403, 'You can only download your own payslips.');
        }

        AuditLogger::log('payslip.downloaded', PayrollLine::class, $line->id, "Downloaded payslip {$line->payslip_number}");

        return app(\App\Services\PdfService::class)->payslipPdf($line)->stream($line->payslip_number . '.pdf');
    }
}
