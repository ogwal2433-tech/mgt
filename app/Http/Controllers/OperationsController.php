<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\InventoryItem;
use App\Models\LeaveRequest;
use App\Models\PerformanceReview;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SupportTicket;
use App\Models\Supplier;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * HR / Technical / Projects / Procurement / Inventory / Attendance hubs.
 * Each page is backed by real records.
 */
class OperationsController extends Controller
{
    public function hr(Request $request)
    {
        $this->denyUnless($request, ['employees.view', 'employees.manage', 'hr.operations']);

        return Inertia::render('Operations/HR', [
            'stats' => [
                'employees' => Employee::active()->count(),
                'probation' => Employee::where('employment_status', 'probation')->count(),
                'contracts_active' => EmployeeContract::where('status', 'active')->count(),
                'contracts_expiring' => EmployeeContract::expiringSoon(90)->count(),
                'leave_pending' => LeaveRequest::where('status', 'pending')->count(),
                'reviews_due' => PerformanceReview::where('status', 'draft')->count(),
            ],
            'expiringContracts' => EmployeeContract::expiringSoon(90)
                ->with('employee:id,employee_code,first_name,last_name')
                ->limit(10)->get(),
            'recentLeave' => LeaveRequest::with('employee:id,employee_code,first_name,last_name')
                ->orderByDesc('id')->limit(10)->get(),
            'recentHires' => Employee::active()->orderByDesc('hire_date')->limit(8)->get(),
        ]);
    }

    public function technical(Request $request)
    {
        $this->denyUnless($request, ['dashboard.technical', 'projects.view', 'projects.manage']);

        return Inertia::render('Operations/Technical', [
            'stats' => [
                'projects' => Project::whereIn('status', ['active', 'planning', 'on_hold'])->count(),
                'tasks_open' => Task::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'tasks_overdue' => Task::overdue()->count(),
                'tickets' => SupportTicket::whereNotIn('status', ['closed', 'resolved'])->count(),
                'team' => Employee::active()->whereHas('department', fn ($q) => $q->where('code', 'TECH'))->count(),
            ],
            'projects' => Project::with('lead:id,first_name,last_name')->orderByDesc('id')->limit(10)->get(),
            'recentTasks' => Task::with(['assignee:id,first_name,last_name', 'project:id,name'])
                ->orderByDesc('id')->limit(10)->get(),
            'tickets' => SupportTicket::orderByDesc('id')->limit(8)->get(),
        ]);
    }

    public function projects(Request $request)
    {
        $this->denyUnless($request, ['projects.view', 'projects.manage']);

        $query = Project::with(['lead:id,first_name,last_name,employee_code', 'department:id,name']);

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('project_code', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%"));
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $tasks = Task::with(['assignee:id,first_name,last_name', 'project:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('due_date')
            ->limit(25)
            ->get();

        return Inertia::render('Operations/Projects', [
            'projects' => $query->orderByDesc('id')->paginate(12)->withQueryString(),
            'tasks' => $tasks,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $request->user()->hasPermission(\App\Support\Permissions::PROJECTS_MANAGE),
        ]);
    }

    public function procurement(Request $request)
    {
        $this->denyUnless($request, ['procurement.manage']);

        return Inertia::render('Operations/Procurement', [
            'suppliers' => Supplier::orderBy('name')->paginate(12),
            'orders' => PurchaseOrder::with('supplier:id,name')->orderByDesc('id')->limit(15)->get(),
            'stats' => [
                'suppliers' => Supplier::where('status', 'active')->count(),
                'open_orders' => PurchaseOrder::whereNotIn('status', ['received', 'cancelled', 'closed'])->count(),
                'pending_receipt' => PurchaseOrder::where('status', 'approved')->count(),
            ],
        ]);
    }

    public function inventory(Request $request)
    {
        $this->denyUnless($request, ['inventory.manage', 'assets.manage']);

        return Inertia::render('Operations/Inventory', [
            'items' => InventoryItem::orderBy('name')->paginate(12),
            'lowStock' => InventoryItem::lowStock()->limit(10)->get(),
            'assets' => Asset::with('assignee:id,first_name,last_name,employee_code')->orderByDesc('id')->limit(15)->get(),
            'stats' => [
                'items' => InventoryItem::count(),
                'low_stock' => InventoryItem::lowStock()->count(),
                'assets' => Asset::count(),
                'assigned' => Asset::where('status', 'assigned')->count(),
            ],
        ]);
    }

    public function attendance(Request $request)
    {
        $this->denyUnless($request, ['hr.operations']);

        return Inertia::render('Operations/Attendance', [
            'attendance' => AttendanceRecord::with('employee:id,employee_code,first_name,last_name')
                ->whereDate('date', '>=', now()->subDays(30))
                ->orderByDesc('date')
                ->limit(50)
                ->get(),
            'leave' => LeaveRequest::with('employee:id,employee_code,first_name,last_name')
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
            'stats' => [
                'present_today' => AttendanceRecord::whereDate('date', now())->where('status', 'present')->count(),
                'leave_pending' => LeaveRequest::where('status', 'pending')->count(),
                'leave_approved' => LeaveRequest::where('status', 'approved')->count(),
            ],
        ]);
    }

    private function denyUnless(Request $request, array $permissions): void
    {
        if (! $request->user()->hasAnyPermission($permissions)) {
            abort(403, 'You are not authorised to access this module.');
        }
    }
}
