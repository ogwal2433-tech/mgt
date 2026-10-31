<?php

namespace App\Http\Middleware;

use App\Support\Permissions as P;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends \Inertia\Middleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return ['user' => null];
                }

                $user->load(['roles.permissions', 'employee.department', 'employee.position']);

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'must_change_password' => $user->must_change_password,
                        'employee' => $user->employee ? [
                            'id' => $user->employee->id,
                            'employee_code' => $user->employee->employee_code,
                            'full_name' => $user->employee->full_name,
                            'job_title' => $user->employee->job_title,
                            'department' => $user->employee->department?->only(['id', 'code', 'name']),
                        ] : null,
                        'roles' => $user->roles->map(fn ($role) => ['slug' => $role->slug, 'name' => $role->name])->values(),
                        'permissions' => $user->permissionSlugs(),
                    ],
                ];
            },
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'invite' => $request->session()->get('invite'),
            ],
            'unreadNotifications' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return 0;
                }

                return $user->notificationsLog()->unread()->count();
            },
            'meetings' => fn () => [
                'upcoming' => $this->upcomingMeetings($request->user()),
            ],
            'navigation' => fn () => $this->navigationFor($request->user()),            'app' => [
                'name' => config('app.name'),
                'timezone' => config('app.timezone'),
            ],
        ]);
    }

    /**
     * Upcoming meetings the user hosts or is invited to — drives the countdown banner.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingMeetings(?object $user): array
    {
        if (! $user) {
            return [];
        }

        return \App\Models\Meeting::query()
            ->where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)
                    ->orWhereHas('invitations', fn ($i) => $i->where('user_id', $user->id));
            })
            ->where('status', '!=', \App\Models\Meeting::STATUS_ENDED)
            ->where('link_expires_at', '>', now())
            ->where(function ($q) {
                $q->whereNull('scheduled_at')->orWhere('scheduled_at', '>=', now()->subHour());
            })
            ->orderByRaw('COALESCE(scheduled_at, created_at) ASC')
            ->limit(3)
            ->get(['id', 'title', 'public_id', 'host_user_id', 'scheduled_at', 'link_expires_at', 'status'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'scheduled_at' => $m->scheduled_at?->toIso8601String(),
                'starts_in_seconds' => $m->scheduled_at ? max(0, (int) now()->diffInSeconds($m->scheduled_at, false)) : 0,
                'is_host' => $m->host_user_id === $user->id,
                'join_url' => '/meetings/' . $m->id . '/room',
                'detail_url' => '/meetings/' . $m->id,
            ])
            ->values()
            ->all();
    }

    /**
     * Sidebar entries filtered by permission, with live counts on the tabs.
     */
    private function navigationFor(?object $user): array
    {
        if (! $user) {
            return [];
        }

        $can = fn (string $permission) => $user->hasPermission($permission);
        $any = fn (array $permissions) => $user->hasAnyPermission($permissions);

        // Live tab badges — requisition counts shown when available.
        $requisitionCount = \App\Models\Requisition::visibleTo($user)
            ->whereIn('status', [
                \App\Enums\RequisitionStatus::Draft,
                \App\Enums\RequisitionStatus::PendingTechnicalLead,
                \App\Enums\RequisitionStatus::PendingHrReview,
                \App\Enums\RequisitionStatus::PendingMdApproval,
                \App\Enums\RequisitionStatus::ReturnedForCorrection,
            ])
            ->count();

        $approvalCount = \App\Models\Requisition::where('requester_user_id', '!=', $user->id)
            ->where(function ($q) use ($user) {
                if ($user->hasPermission(P::REQUISITIONS_REVIEW_TECHNICAL)) {
                    $q->orWhere('status', \App\Enums\RequisitionStatus::PendingTechnicalLead);
                }
                if ($user->hasPermission(P::REQUISITIONS_REVIEW_HR)) {
                    $q->orWhere('status', \App\Enums\RequisitionStatus::PendingHrReview);
                }
                if ($user->hasPermission(P::REQUISITIONS_APPROVE_MD)) {
                    $q->orWhere('status', \App\Enums\RequisitionStatus::PendingMdApproval);
                }
            })
            ->count();

        $disbursementCount = \App\Models\Requisition::whereIn('status', [
                \App\Enums\RequisitionStatus::ApprovedAwaitingDisbursement,
                \App\Enums\RequisitionStatus::PartiallyDisbursed,
            ])->count();

        $meetingCount = \App\Models\Meeting::query()
            ->where(function ($q) use ($user) {
                $q->where('host_user_id', $user->id)
                    ->orWhereHas('invitations', fn ($i) => $i->where('user_id', $user->id));
            })
            ->whereIn('status', [\App\Models\Meeting::STATUS_SCHEDULED, \App\Models\Meeting::STATUS_LIVE])
            ->where('link_expires_at', '>', now())
            ->count();

        $items = [
            ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard', 'show' => true],
            ['label' => 'Employees', 'href' => '/employees', 'icon' => 'users', 'show' => $any([P::EMPLOYEES_VIEW, P::EMPLOYEES_MANAGE])],
            ['label' => 'HR Management', 'href' => '/hr', 'icon' => 'briefcase', 'show' => $can(P::HR_OPERATIONS) || $can(P::EMPLOYEES_MANAGE)],
            ['label' => 'Employment Contracts', 'href' => '/contracts', 'icon' => 'file-text', 'show' => $any([P::CONTRACTS_VIEW, P::CONTRACTS_MANAGE])],
            ['label' => 'Technical Department', 'href' => '/technical', 'icon' => 'cpu', 'show' => $any([P::PROJECTS_VIEW, P::PROJECTS_MANAGE, P::DASHBOARD_TECHNICAL])],
            ['label' => 'Projects & Tasks', 'href' => '/projects', 'icon' => 'layers', 'show' => $any([P::PROJECTS_VIEW, P::PROJECTS_MANAGE])],
            ['label' => 'Internal Requisitions', 'href' => '/requisitions', 'icon' => 'file-plus', 'show' => $any([P::REQUISITIONS_CREATE, P::REQUISITIONS_VIEW_DEPARTMENT, P::REQUISITIONS_VIEW_ALL]), 'badge' => $requisitionCount],
            ['label' => 'External Requisitions', 'href' => '/external-requisitions', 'icon' => 'send', 'show' => $any([P::EXTERNAL_REQUISITIONS_MANAGE, P::EXTERNAL_REQUISITIONS_VIEW])],
            ['label' => 'Approval Center', 'href' => '/approvals', 'icon' => 'check-circle', 'show' => $any([P::REQUISITIONS_REVIEW_TECHNICAL, P::REQUISITIONS_REVIEW_HR, P::REQUISITIONS_APPROVE_MD]), 'badge' => $approvalCount],
            ['label' => 'Finance & Accounts', 'href' => '/finance', 'icon' => 'wallet', 'show' => $any([P::FINANCE_VIEW, P::FINANCE_DISBURSE])],
            ['label' => 'Client Invoicing', 'href' => '/invoices', 'icon' => 'send', 'show' => $any([P::INVOICES_VIEW, P::INVOICES_MANAGE])],
            ['label' => 'Payroll', 'href' => '/payroll', 'icon' => 'cash', 'show' => $any([P::PAYROLL_VIEW, P::PAYROLL_RUN])],
            ['label' => 'My Payslips', 'href' => '/my-payslips', 'icon' => 'receipt', 'show' => true],
                ['label' => 'Document Submissions', 'href' => '/documents-submissions', 'icon' => 'file-text', 'show' => $any([P::DOCS_SUBMIT, P::DOCS_REVIEW])],
                ['label' => 'Meetings', 'href' => '/meetings', 'icon' => 'video', 'show' => $any([P::MEETINGS_VIEW, P::MEETINGS_CREATE, P::MEETINGS_MANAGE]), 'badge' => $meetingCount],
            ['label' => 'Disbursements', 'href' => '/disbursements', 'icon' => 'cash', 'show' => $any([P::FINANCE_DISBURSE, P::FINANCE_VIEW]), 'badge' => $disbursementCount],
            ['label' => 'Payment Receipts', 'href' => '/receipts', 'icon' => 'receipt', 'show' => $any([P::FINANCE_VIEW, P::FINANCE_DISBURSE, P::REQUISITIONS_CREATE])],
            ['label' => 'Accountability', 'href' => '/accountability', 'icon' => 'clipboard-check', 'show' => $any([P::FINANCE_ACCOUNTABILITY_REVIEW, P::REQUISITIONS_CREATE, P::FINANCE_VIEW])],
            ['label' => 'Procurement & Suppliers', 'href' => '/procurement', 'icon' => 'truck', 'show' => $can(P::PROCUREMENT_MANAGE)],
            ['label' => 'Inventory & Assets', 'href' => '/inventory', 'icon' => 'box', 'show' => $any([P::INVENTORY_MANAGE, P::ASSETS_MANAGE])],
            ['label' => 'Attendance & Leave', 'href' => '/attendance', 'icon' => 'calendar', 'show' => $can(P::HR_OPERATIONS)],
            ['label' => 'Reports', 'href' => '/reports', 'icon' => 'chart', 'show' => $any([P::REPORTS_EXECUTIVE, P::REPORTS_FINANCIAL, P::REPORTS_HR, P::REPORTS_TECHNICAL])],
            ['label' => 'Finance Reports', 'href' => '/reports/finance', 'icon' => 'chart', 'show' => $any([P::REPORTS_FINANCIAL, P::FINANCE_VIEW])],
            ['label' => 'Notifications', 'href' => '/notifications', 'icon' => 'bell', 'show' => true],
            ['label' => 'Users & Permissions', 'href' => '/users', 'icon' => 'shield', 'show' => $any([P::SYSTEM_USERS, P::SYSTEM_ROLES])],
            ['label' => 'Audit Logs', 'href' => '/audit', 'icon' => 'history', 'show' => $can(P::AUDIT_VIEW)],
            ['label' => 'System Settings', 'href' => '/settings', 'icon' => 'settings', 'show' => $can(P::SYSTEM_SETTINGS)],
        ];

        return array_values(array_filter($items, fn ($item) => $item['show']));
    }
}
