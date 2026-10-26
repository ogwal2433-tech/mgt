<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\RequisitionAttachment;
use App\Models\RequisitionCategory;
use App\Models\RequisitionItem;
use App\Services\RequisitionWorkflowService;
use App\Support\AuditLogger;
use App\Support\DocumentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RequisitionController extends Controller
{
    public function __construct(private readonly RequisitionWorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Requisition::with(['requesterEmployee:id,first_name,last_name,employee_code', 'category:id,name', 'department:id,name'])
            ->visibleTo($user);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('requisition_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($from = $request->string('from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = min((int) $request->integer('per_page', 15), 50);
        $requisitions = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Requisitions/Index', [
            'requisitions' => $requisitions,
            'filters' => $request->only(['search', 'status', 'category_id', 'from', 'to']),
            'categories' => RequisitionCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'statusOptions' => RequisitionStatus::options(),
            'canCreate' => $user->hasPermission(\App\Support\Permissions::REQUISITIONS_CREATE),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Requisitions/Create', [
            'categories' => RequisitionCategory::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'projects' => Project::whereIn('status', ['active', 'planning'])->orderBy('name')->get(['id', 'name', 'project_code']),
            'currencies' => ['USD', 'KES', 'EUR', 'GBP', 'TZS', 'UGX'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $requisition = DB::transaction(function () use ($request, $data) {
            $user = $request->user();

            $requisition = Requisition::create([
                'requisition_number' => null,
                'requester_user_id' => $user->id,
                'requester_employee_id' => $user->employee_id,
                'department_id' => $user->employee?->department_id ?? $data['department_id'] ?? null,
                'category_id' => $data['category_id'],
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'],
                'purpose' => $data['purpose'] ?? null,
                'justification' => $data['justification'] ?? null,
                'currency' => $data['currency'],
                'requested_amount' => Money::normalize($data['requested_amount']),
                'priority' => $data['priority'],
                'required_date' => $data['required_date'] ?? null,
                'status' => RequisitionStatus::Draft,
                'supplier_name' => $data['supplier_name'] ?? null,
                'supplier_contact' => $data['supplier_contact'] ?? null,
                'destination' => $data['destination'] ?? null,
                'trip_start' => $data['trip_start'] ?? null,
                'trip_end' => $data['trip_end'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'budget_reference' => $data['budget_reference'] ?? null,
                'is_advance' => (bool) ($data['is_advance'] ?? false),
                'accountability_required' => (bool) ($data['is_advance'] ?? false) || (bool) ($data['accountability_required'] ?? false),
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] ?? [] as $index => $item) {
                RequisitionItem::create([
                    'requisition_id' => $requisition->id,
                    'description' => $item['description'],
                    'line_type' => $item['line_type'] ?? 'goods',
                    'quantity' => $item['quantity'] ?? 1,
                    'hours' => $item['hours'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'period' => $item['period'] ?? null,
                    'unit_cost' => Money::normalize($item['unit_cost']),
                    'total_cost' => RequisitionItem::computeLineTotal($item),
                    'sort_order' => $index,
                ]);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    try {
                        $document = DocumentService::store($file, 'requisition_attachment', $requisition);
                    } catch (\RuntimeException $e) {
                        // Attachments are optional — never block the requisition over one.
                        continue;
                    }
                    RequisitionAttachment::create([
                        'requisition_id' => $requisition->id,
                        'document_id' => $document->id,
                    ]);
                }
            }

            AuditLogger::log('requisition.created', Requisition::class, $requisition->id, "Created requisition draft {$requisition->title}");

            return $requisition;
        });

        if ($request->boolean('submit')) {
            $this->workflow->submit($requisition, $request->user());
        }

        return redirect()->route('requisitions.show', $requisition->id)
            ->with('success', 'Requisition created successfully.');
    }

    public function show(Request $request, Requisition $requisition)
    {
        $requisition->load([
            'requester:id,username,name',
            'requesterEmployee:id,first_name,last_name,employee_code,job_title',
            'category:id,name',
            'department:id,name',
            'project:id,name,project_code',
            'items',
            'attachments.document',
            'approvals.user:id,username',
            'payments.recordedBy:id,username',
            'payments.evidence',
            'receipts.document',
            'receipts.generatedBy:id,username',
            'accountabilitySubmissions.reviewer:id,username',
            'refunds',
        ]);

        $user = $request->user();

        return Inertia::render('Requisitions/Show', [
            'requisition' => $requisition,
            'can' => [
                'submit' => in_array($requisition->status, [RequisitionStatus::Draft, RequisitionStatus::ReturnedForCorrection], true),
                'edit' => in_array($requisition->status, [RequisitionStatus::Draft, RequisitionStatus::ReturnedForCorrection], true),
                'review_technical' => $requisition->status === RequisitionStatus::PendingTechnicalLead
                    && $user->hasPermission(\App\Support\Permissions::REQUISITIONS_REVIEW_TECHNICAL)
                    && $requisition->requester_user_id !== $user->id,
                'review_hr' => $requisition->status === RequisitionStatus::PendingHrReview
                    && $user->hasPermission(\App\Support\Permissions::REQUISITIONS_REVIEW_HR)
                    && $requisition->requester_user_id !== $user->id,
                'review_md' => $requisition->status === RequisitionStatus::PendingMdApproval
                    && $user->hasPermission(\App\Support\Permissions::REQUISITIONS_APPROVE_MD)
                    && $requisition->requester_user_id !== $user->id,
                'disburse' => in_array($requisition->status, [RequisitionStatus::ApprovedAwaitingDisbursement, RequisitionStatus::PartiallyDisbursed], true)
                    && $user->hasPermission(\App\Support\Permissions::FINANCE_DISBURSE),
                'accountability' => $user->id === $requisition->requester_user_id
                    && in_array($requisition->status, [RequisitionStatus::FullyDisbursed, RequisitionStatus::PartiallyDisbursed, RequisitionStatus::PendingAccountability], true)
                    && $requisition->accountability_required,
                'review_accountability' => $user->hasPermission(\App\Support\Permissions::FINANCE_ACCOUNTABILITY_REVIEW),
            ],
            'paymentMethods' => json_decode(\App\Support\Settings::get('finance.payment_methods', '["cash","bank_transfer","mobile_money","cheque","other"]') ?? '[]', true),
        ]);
    }

    public function update(Request $request, Requisition $requisition)
    {
        if (! in_array($requisition->status, [RequisitionStatus::Draft, RequisitionStatus::ReturnedForCorrection], true)) {
            abort(403, 'Only draft or returned requisitions can be edited.');
        }

        if ($request->user()->id !== $requisition->requester_user_id
            && ! $request->user()->hasPermission(\App\Support\Permissions::REQUISITIONS_VIEW_ALL)) {
            abort(403, 'You cannot modify a requisition belonging to another employee.');
        }

        $data = $this->validated($request, false);

        DB::transaction(function () use ($request, $data, $requisition) {
            $requisition->update([
                'category_id' => $data['category_id'],
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'],
                'purpose' => $data['purpose'] ?? null,
                'justification' => $data['justification'] ?? null,
                'currency' => $data['currency'],
                'requested_amount' => Money::normalize($data['requested_amount']),
                'priority' => $data['priority'],
                'required_date' => $data['required_date'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'supplier_contact' => $data['supplier_contact'] ?? null,
                'destination' => $data['destination'] ?? null,
                'trip_start' => $data['trip_start'] ?? null,
                'trip_end' => $data['trip_end'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'budget_reference' => $data['budget_reference'] ?? null,
                'is_advance' => (bool) ($data['is_advance'] ?? false),
                'accountability_required' => (bool) ($data['is_advance'] ?? false) || (bool) ($data['accountability_required'] ?? false),
            ]);

            $requisition->items()->delete();
            foreach ($data['items'] ?? [] as $index => $item) {
                RequisitionItem::create([
                    'requisition_id' => $requisition->id,
                    'description' => $item['description'],
                    'line_type' => $item['line_type'] ?? 'goods',
                    'quantity' => $item['quantity'] ?? 1,
                    'hours' => $item['hours'] ?? null,
                    'unit' => $item['unit'] ?? null,
                    'period' => $item['period'] ?? null,
                    'unit_cost' => Money::normalize($item['unit_cost']),
                    'total_cost' => RequisitionItem::computeLineTotal($item),
                    'sort_order' => $index,
                ]);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    try {
                        $document = DocumentService::store($file, 'requisition_attachment', $requisition);
                    } catch (\RuntimeException $e) {
                        // Attachments are optional — never block the requisition over one.
                        continue;
                    }
                    RequisitionAttachment::create([
                        'requisition_id' => $requisition->id,
                        'document_id' => $document->id,
                    ]);
                }
            }

            if ($requisition->status === RequisitionStatus::ReturnedForCorrection) {
                $requisition->update(['status' => RequisitionStatus::Draft]);
            }

            AuditLogger::log('requisition.updated', Requisition::class, $requisition->id, "Updated requisition {$requisition->requisition_number}");
        });

        return redirect()->route('requisitions.show', $requisition->id)->with('success', 'Requisition updated.');
    }

    public function submit(Request $request, Requisition $requisition)
    {
        $this->workflow->submit($requisition, $request->user());

        return redirect()->route('requisitions.show', $requisition->id)
            ->with('success', 'Requisition submitted. It is now pending Technical Lead review.');
    }

    public function approve(Request $request, Requisition $requisition)
    {
        $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflow->approve(
            $requisition,
            $request->user(),
            $request->input('approved_amount') !== null ? Money::normalize($request->input('approved_amount')) : null,
            $request->input('comments')
        );

        return back()->with('success', 'Requisition approved.');
    }

    public function reject(Request $request, Requisition $requisition)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->workflow->reject($requisition, $request->user(), $request->input('reason'));

        return back()->with('success', 'Requisition rejected.');
    }

    public function returnForCorrection(Request $request, Requisition $requisition)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->workflow->returnForCorrection($requisition, $request->user(), $request->input('reason'));

        return back()->with('success', 'Requisition returned to the requester for correction.');
    }

    public function destroy(Request $request, Requisition $requisition)
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            abort(403, 'Only draft requisitions can be deleted.');
        }
        if ($request->user()->id !== $requisition->requester_user_id
            && ! $request->user()->hasPermission(\App\Support\Permissions::REQUISITIONS_VIEW_ALL)) {
            abort(403, 'Not authorised.');
        }

        AuditLogger::log('requisition.deleted', Requisition::class, $requisition->id, "Deleted draft {$requisition->requisition_number}");
        $requisition->delete();

        return redirect()->route('requisitions.index')->with('success', 'Draft requisition deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $requireItems = true): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:requisition_categories,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'purpose' => ['nullable', 'string', 'max:4000'],
            'justification' => ['nullable', 'string', 'max:4000'],
            'currency' => ['required', 'string', 'max:10'],
            'requested_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'required_date' => ['nullable', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_contact' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'trip_start' => ['nullable', 'date'],
            'trip_end' => ['nullable', 'date', 'after_or_equal:trip_start'],
            'transport_mode' => ['nullable', 'string', 'max:60'],
            'budget_reference' => ['nullable', 'string', 'max:120'],
            'is_advance' => ['nullable', 'boolean'],
            'accountability_required' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.line_type' => ['nullable', Rule::in(['goods', 'service', 'activity'])],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'items.*.hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.period' => ['nullable', 'string', 'max:120'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,txt,zip,ppt,pptx,odt,rtf'],
        ]);
    }
}
