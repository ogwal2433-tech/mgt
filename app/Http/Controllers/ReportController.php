<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $from = $request->string('from')->toString() ?: now()->startOfYear()->toDateString();
        $to = $request->string('to')->toString() ?: now()->toDateString();

        $requisitions = Requisition::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        if (! $user->hasPermission(\App\Support\Permissions::REPORTS_EXECUTIVE)) {
            $requisitions->visibleTo($user);
        }

        $summary = [
            'requested' => (clone $requisitions)->sum('requested_amount'),
            'approved' => (clone $requisitions)->whereNotNull('approved_amount')->sum('approved_amount'),
            'disbursed' => Payment::where('status', 'completed')
                ->whereBetween('payment_date', [$from, $to])->sum('amount'),
            'count' => (clone $requisitions)->count(),
            'rejected' => (clone $requisitions)->where('status', 'like', 'REJECTED%')->count(),
        ];

        $byStatus = (clone $requisitions)
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(requested_amount),0) as amount')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
                'amount' => (string) $row->amount,
            ]);

        $byDepartment = Department::query()
            ->withCount(['employees as employee_count' => fn ($q) => $q->where('is_active', true)])
            ->withSum(['requisitions as requested_sum' => fn ($q) => $q->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)], 'requested_amount')
            ->get(['id', 'code', 'name', 'employee_count', 'requested_sum']);

        $driver = \DB::connection()->getDriverName();
        $monthExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m', payment_date)",
            'pgsql' => "to_char(payment_date, 'YYYY-MM')",
            default => "DATE_FORMAT(payment_date, '%Y-%m')",
        };

        $monthly = Payment::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw("{$monthExpression} as month, COALESCE(SUM(amount),0) as total, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => $summary,
            'byStatus' => $byStatus,
            'byDepartment' => $byDepartment,
            'monthly' => $monthly,
            'currency' => \App\Support\Settings::get('finance.currency', 'USD'),
            'extra' => [
                'contracts_expiring' => EmployeeContract::expiringSoon(90)->count(),
                'active_projects' => Project::whereIn('status', ['active', 'planning'])->count(),
                'overdue_tasks' => Task::overdue()->count(),
                'receipts' => PaymentReceipt::whereBetween('generated_at', [$from, $to])->count(),
                'employees' => Employee::active()->count(),
            ],
        ]);
    }
}
