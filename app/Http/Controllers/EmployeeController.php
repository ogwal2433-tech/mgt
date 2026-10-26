<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['department:id,name,code', 'position:id,title', 'user:id,username,is_active']);

        if ($search = $request->string('search')->toString()) {
            $query->search($search);
        }
        if ($departmentId = $request->integer('department_id')) {
            $query->where('department_id', $departmentId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('employment_status', $status);
        }

        $employees = $query->orderBy('first_name')->paginate(15)->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'department_id', 'status']),
            'departments' => Department::where('is_active', true)->get(['id', 'name', 'code']),
            'positions' => Position::where('is_active', true)->orderBy('title')->get(['id', 'title', 'department_id']),
            'canManage' => $request->user()->hasPermission(\App\Support\Permissions::EMPLOYEES_MANAGE),
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::EMPLOYEES_MANAGE)) {
            abort(403);
        }

        $data = $this->validated($request);

        $employee = Employee::create($data + [
            'employee_code' => $this->nextCode(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        AuditLogger::log('employee.created', Employee::class, $employee->id, "Registered employee {$employee->employee_code}");

        return redirect()->route('employees.show', $employee->id)->with('success', 'Employee registered.');
    }

    public function show(Employee $employee)
    {
        $employee->load([
            'department:id,name,code',
            'position:id,title',
            'supervisor:id,first_name,last_name',
            'user:id,username,email,is_active',
            'contracts:id,employee_id,contract_number,contract_type,status,start_date,end_date',
        ]);

        return Inertia::render('Employees/Show', ['employee' => $employee]);
    }

    public function update(Request $request, Employee $employee)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::EMPLOYEES_MANAGE)) {
            abort(403);
        }

        $data = $this->validated($request, false);
        $employee->update($data + ['updated_by' => $request->user()->id]);

        AuditLogger::log('employee.updated', Employee::class, $employee->id, "Updated employee {$employee->employee_code}");

        return back()->with('success', 'Employee updated.');
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('employees', 'email')->ignore($request->route('employee')?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'supervisor_id' => ['nullable', 'exists:employees,id'],
            'employment_status' => ['required', Rule::in(['active', 'probation', 'suspended', 'terminated', 'resigned'])],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'hire_date' => ['nullable', 'date'],
            'exit_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    private function nextCode(): string
    {
        $latest = Employee::withTrashed()->orderByDesc('id')->value('employee_code');
        $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return 'EMP-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
