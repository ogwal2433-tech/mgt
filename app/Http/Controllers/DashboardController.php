<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Models\AccountabilitySubmission;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'stats' => $this->statsFor($user),
            'recent' => $this->recentFor($user),
        ]);
    }

    private function statsFor($user): array
    {
        if ($user->isManagingDirector()) {
            return $this->mdStats();
        }
        if ($user->isHr()) {
            return $this->hrStats();
        }
        if ($user->isTechnicalLead() || $user->hasPermission(\App\Support\Permissions::DASHBOARD_TECHNICAL)) {
            return $this->technicalStats();
        }

        return $this->staffStats($user);
    }

    private function mdStats(): array
    {
        return [
            'active_employees' => Employee::active()->count(),
            'employees_by_department' => Department::withCount(['employees as total' => fn ($q) => $q->where('is_active', true)])
                ->get(['id', 'code', 'name', 'total']),
            'active_projects' => Project::whereIn('status', ['active', 'planning', 'on_hold'])->count(),
            'completed_tasks' => Task::where('status', 'completed')->count(),
            'overdue_tasks' => Task::overdue()->count(),
            'pending_technical_lead' => Requisition::where('status', RequisitionStatus::PendingTechnicalLead)->count(),
            'pending_hr' => Requisition::where('status', RequisitionStatus::PendingHrReview)->count(),
            'pending_md' => Requisition::where('status', RequisitionStatus::PendingMdApproval)->count(),
            'awaiting_disbursement' => Requisition::where('status', RequisitionStatus::ApprovedAwaitingDisbursement)->count(),
            'total_requested' => Requisition::whereNotIn('status', [RequisitionStatus::Draft])->sum('requested_amount'),
            'total_approved' => Requisition::whereNotNull('approved_amount')->sum('approved_amount'),
            'total_disbursed' => Payment::where('status', 'completed')->sum('amount'),
            'outstanding_advances' => Requisition::where('accountability_required', true)
                ->whereNotIn('status', [RequisitionStatus::Closed, RequisitionStatus::Cancelled])
                ->selectRaw('COALESCE(SUM(amount_paid - amount_accounted), 0) as outstanding')
                ->value('outstanding'),
            'accountability_pending' => AccountabilitySubmission::where('status', 'pending')->count(),
            'contracts_expiring' => EmployeeContract::expiringSoon(90)->count(),
            'monthly_expenditure' => Payment::where('status', 'completed')
                ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'currency' => \App\Support\Settings::get('finance.currency', 'UGX'),
        ];
    }

    private function hrStats(): array
    {
        return [
            'active_employees' => Employee::active()->count(),
            'accounts_pending_activation' => \App\Models\User::where('is_active', false)->count(),
            'contracts_expiring' => EmployeeContract::expiringSoon(90)->count(),
            'contracts_active' => EmployeeContract::where('status', 'active')->count(),
            'pending_leave' => \App\Models\LeaveRequest::where('status', 'pending')->count(),
            'pending_hr_review' => Requisition::where('status', RequisitionStatus::PendingHrReview)->count(),
            'awaiting_disbursement' => Requisition::whereIn('status', [RequisitionStatus::ApprovedAwaitingDisbursement, RequisitionStatus::PartiallyDisbursed])->count(),
            'payments_made' => Payment::where('status', 'completed')->count(),
            'receipts_generated' => PaymentReceipt::count(),
            'accountability_outstanding' => AccountabilitySubmission::where('status', 'pending')->count(),
            'approved_expenses' => Requisition::whereNotNull('approved_amount')->sum('approved_amount'),
            'total_disbursed' => Payment::where('status', 'completed')->sum('amount'),
            'outstanding_advances' => Requisition::where('accountability_required', true)
                ->whereNotIn('status', [RequisitionStatus::Closed, RequisitionStatus::Cancelled])
                ->selectRaw('COALESCE(SUM(amount_paid - amount_accounted), 0) as outstanding')
                ->value('outstanding'),
            'currency' => \App\Support\Settings::get('finance.currency', 'UGX'),
        ];
    }

    private function technicalStats(): array
    {
        return [
            'technical_employees' => Employee::active()->whereHas('department', fn ($q) => $q->where('code', 'TECH'))->count(),
            'active_projects' => Project::whereIn('status', ['active', 'planning', 'on_hold'])->count(),
            'tasks_assigned' => Task::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'completed_tasks' => Task::where('status', 'completed')->count(),
            'overdue_tasks' => Task::overdue()->count(),
            'pending_lead_action' => Requisition::where('status', RequisitionStatus::PendingTechnicalLead)->count(),
            'awaiting_hr' => Requisition::where('status', RequisitionStatus::PendingHrReview)->count(),
            'rejected' => Requisition::whereIn('status', [
                RequisitionStatus::RejectedByTechnicalLead,
                RequisitionStatus::RejectedByHr,
                RequisitionStatus::RejectedByMd,
            ])->count(),
            'external_forms' => \App\Models\ExternalRequisition::count(),
            'support_tickets' => SupportTicket::whereNotIn('status', ['closed', 'resolved'])->count(),
            'currency' => \App\Support\Settings::get('finance.currency', 'UGX'),
        ];
    }

    private function staffStats($user): array
    {
        $myRequisitions = Requisition::where('requester_user_id', $user->id);

        return [
            'my_requisitions' => (clone $myRequisitions)->count(),
            'my_pending' => (clone $myRequisitions)->where('status', 'like', 'PENDING%')->count(),
            'my_approved' => (clone $myRequisitions)->where('status', 'like', '%DISBURSED%')->count(),
            'my_rejected' => (clone $myRequisitions)->where('status', 'like', 'REJECTED%')->count(),
            'my_receipts' => PaymentReceipt::where('requester_user_id', $user->id)->count(),
            'my_tasks' => Task::where('assigned_to_employee_id', $user->employee_id)->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'currency' => \App\Support\Settings::get('finance.currency', 'UGX'),
        ];
    }

    private function recentFor($user): array
    {
        $requisitions = Requisition::with(['requester:id,username', 'requesterEmployee:id,first_name,last_name,employee_code'])
            ->visibleTo($user)
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'requisition_number' => $r->requisition_number,
                'title' => $r->title,
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'requested_amount' => (string) $r->requested_amount,
                'currency' => $r->currency,
                'requester' => $r->requesterEmployee?->full_name ?? $r->requester?->username,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        $payments = Payment::with(['requisition:id,requisition_number', 'recordedBy:id,username'])
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'requisition_number' => $p->requisition?->requisition_number,
                'amount' => (string) $p->amount,
                'currency' => $p->currency,
                'payment_date' => $p->payment_date->toDateString(),
                'payee_name' => $p->payee_name,
            ]);

        return [
            'requisitions' => $requisitions,
            'payments' => $payments,
        ];
    }
}
