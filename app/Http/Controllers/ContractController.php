<?php

namespace App\Http\Controllers;

use App\Models\ContractReminder;
use App\Models\ContractVersion;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Support\AuditLogger;
use App\Support\DocumentService;
use App\Support\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = EmployeeContract::with(['employee:id,employee_code,first_name,last_name', 'department:id,name']);

        if (! $user->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE)) {
            $query->where('employee_id', $user->employee_id);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => $e->search($search));
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $contracts = $query->orderByDesc('id')->paginate(15)->withQueryString();

        $expiring = EmployeeContract::expiringSoon(90)
            ->with('employee:id,employee_code,first_name,last_name')
            ->get();

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
            'expiring' => $expiring,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $user->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE),
            'employees' => $user->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE)
                ? Employee::active()->orderBy('first_name')->get(['id', 'employee_code', 'first_name', 'last_name', 'department_id'])
                : collect(),
            'departments' => Department::get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE)) {
            abort(403);
        }

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'contract_type' => ['required', Rule::in(['permanent', 'fixed_term', 'probation', 'consulting', 'internship'])],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'probation_end_date' => ['nullable', 'date'],
            'renewal_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'pending_signature', 'active', 'expiring', 'expired', 'renewed', 'terminated'])],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'working_hours' => ['nullable', 'string', 'max:60'],
            'signature_status' => ['nullable', Rule::in(['pending', 'signed', 'not_required'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'contract_file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx'],
        ]);

        $contract = DB::transaction(function () use ($request, $data) {
            $contract = EmployeeContract::create([
                'contract_number' => $this->nextNumber(),
                'employee_id' => $data['employee_id'],
                'contract_type' => $data['contract_type'],
                'job_title' => $data['job_title'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'renewal_date' => $data['renewal_date'] ?? null,
                'status' => $data['status'],
                'salary' => $data['salary'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'working_hours' => $data['working_hours'] ?? null,
                'signature_status' => $data['signature_status'] ?? 'pending',
                'current_version' => 1,
                'notes' => $data['notes'] ?? null,
                'is_confidential' => true,
                'created_by' => $request->user()->id,
            ]);

            if ($request->hasFile('contract_file')) {
                $document = DocumentService::store($request->file('contract_file'), 'employment_contract', $contract, true);
                ContractVersion::create([
                    'contract_id' => $contract->id,
                    'version_number' => 1,
                    'document_id' => $document->id,
                    'change_summary' => 'Initial contract upload',
                    'file_label' => 'Signed contract',
                    'uploaded_by' => $request->user()->id,
                ]);
            }

            $this->scheduleReminders($contract);

            AuditLogger::log('contract.created', EmployeeContract::class, $contract->id,
                "Created contract {$contract->contract_number}", null, ['status' => $contract->status]);

            return $contract;
        });

        return redirect()->route('contracts.show', $contract->id)->with('success', 'Contract saved.');
    }

    public function show(EmployeeContract $contract)
    {
        $user = request()->user();

        if (! $user->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE)
            && $contract->employee_id !== $user->employee_id
            && ! $user->hasPermission(\App\Support\Permissions::CONTRACTS_VIEW_CONFIDENTIAL)) {
            abort(403, 'You can only access your own employment contract.');
        }

        $contract->load(['employee:id,employee_code,first_name,last_name,email', 'department:id,name', 'versions.document', 'reminders']);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
            'canManage' => $user->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE),
        ]);
    }

    public function addVersion(Request $request, EmployeeContract $contract)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE)) {
            abort(403);
        }

        $data = $request->validate([
            'change_summary' => ['required', 'string', 'max:500'],
            'contract_file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx'],
        ]);

        $version = DB::transaction(function () use ($request, $data, $contract) {
            $document = DocumentService::store($request->file('contract_file'), 'employment_contract', $contract, true);
            $version = ContractVersion::create([
                'contract_id' => $contract->id,
                'version_number' => $contract->current_version + 1,
                'document_id' => $document->id,
                'change_summary' => $data['change_summary'],
                'file_label' => 'Contract version ' . ($contract->current_version + 1),
                'uploaded_by' => $request->user()->id,
            ]);

            // Previous versions are preserved; never overwrite a signed contract.
            $contract->update(['current_version' => $contract->current_version + 1]);

            AuditLogger::log('contract.version_added', EmployeeContract::class, $contract->id,
                "Added contract version {$version->version_number}", ['version' => $contract->getOriginal('current_version')], ['version' => $contract->current_version]);

            return $version;
        });

        return back()->with('success', 'Contract version added. Previous versions are preserved.');
    }

    public function updateStatus(Request $request, EmployeeContract $contract)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::CONTRACTS_MANAGE)) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'pending_signature', 'active', 'expiring', 'expired', 'renewed', 'terminated'])],
            'end_date' => ['nullable', 'date'],
            'renewal_date' => ['nullable', 'date'],
        ]);

        $old = $contract->status;
        $contract->update($data);

        AuditLogger::log('contract.status_changed', EmployeeContract::class, $contract->id,
            "Contract status changed to {$data['status']}", ['status' => $old], ['status' => $data['status']]);

        return back()->with('success', 'Contract updated.');
    }

    private function scheduleReminders(EmployeeContract $contract): void
    {
        if (! $contract->end_date) {
            return;
        }

        $daysList = array_map('intval', explode(',', (string) \App\Support\Settings::get('contracts.reminder_days', '90,60,30')));

        foreach ($daysList as $days) {
            $remindOn = $contract->end_date->copy()->subDays($days);
            if ($remindOn->isPast()) {
                continue;
            }

            ContractReminder::create([
                'contract_id' => $contract->id,
                'remind_on' => $remindOn->toDateString(),
                'days_before' => $days,
                'status' => 'pending',
            ]);
        }
    }

    private function nextNumber(): string
    {
        return \App\Support\Sequence::next('contract', 'CON');
    }
}
