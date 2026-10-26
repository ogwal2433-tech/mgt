<?php

namespace App\Http\Controllers;

use App\Models\ExternalParty;
use App\Models\ExternalRequisition;
use App\Models\ExternalRequisitionItem;
use App\Models\Project;
use App\Services\PdfService;
use App\Support\AuditLogger;
use App\Support\DocumentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExternalRequisitionController extends Controller
{
    public function __construct(private readonly PdfService $pdf) {}

    public function index(Request $request)
    {
        $query = ExternalRequisition::with(['party:id,name', 'preparedBy:id,username', 'project:id,name']);

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', "%{$search}%")
                    ->orWhere('external_party_name', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return Inertia::render('External/Index', [
            'requisitions' => $query->orderByDesc('id')->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
            'canManage' => $request->user()->hasPermission(\App\Support\Permissions::EXTERNAL_REQUISITIONS_MANAGE),
        ]);
    }

    public function create()
    {
        return Inertia::render('External/Create', [
            'parties' => ExternalParty::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'project_code']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $requisition = DB::transaction(function () use ($request, $data) {
            $requisition = ExternalRequisition::create([
                'requisition_number' => \App\Support\Sequence::next('external_requisition', 'EXT'),
                'date' => $data['date'],
                'external_party_id' => $data['external_party_id'] ?? null,
                'external_party_name' => $data['external_party_name'],
                'external_contact' => $data['external_contact'] ?? null,
                'external_email' => $data['external_email'] ?? null,
                'external_phone' => $data['external_phone'] ?? null,
                'prepared_by_user_id' => $request->user()->id,
                'project_id' => $data['project_id'] ?? null,
                'client_reference' => $data['client_reference'] ?? null,
                'purpose' => $data['purpose'],
                'currency' => $data['currency'],
                'total_amount' => Money::normalize($data['total_amount']),
                'required_delivery_date' => $data['required_delivery_date'] ?? null,
                'payee_name' => $data['payee_name'] ?? null,
                'requested_by_name' => $data['requested_by_name'] ?? null,
                'department_text' => $data['department_text'] ?? null,
                'mode_of_payment' => $data['mode_of_payment'] ?? null,
                'date_of_activity' => $data['date_of_activity'] ?? null,
                'activity_budget' => $data['activity_budget'] ?? null,
                'status' => 'draft',
                'terms' => $data['terms'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $index => $item) {
                ExternalRequisitionItem::create([
                    'external_requisition_id' => $requisition->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => Money::normalize($item['unit_price']),
                    'total_price' => ExternalRequisitionItem::query()
                        ->exists() ? Money::mulQty($item['unit_price'], $item['quantity']) : Money::mulQty($item['unit_price'], $item['quantity']),
                    'sort_order' => $index,
                ]);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $document = DocumentService::store($file, 'external_requisition', $requisition);
                    $requisition->attachments()->create(['document_id' => $document->id]);
                }
            }

            AuditLogger::log('external_requisition.created', ExternalRequisition::class, $requisition->id,
                "Created external requisition {$requisition->requisition_number}");

            return $requisition;
        });

        return redirect()->route('external-requisitions.show', $requisition->id)->with('success', 'External requisition created.');
    }

    public function show(ExternalRequisition $external_requisition)
    {
        $external_requisition->load(['party', 'preparedBy:id,username', 'project:id,name', 'items', 'attachments.document', 'approvedBy:id,username']);

        return Inertia::render('External/Show', [
            'requisition' => $external_requisition,
            'canManage' => request()->user()->hasPermission(\App\Support\Permissions::EXTERNAL_REQUISITIONS_MANAGE),
        ]);
    }

    /**
     * Email the form to the external requester with a tokenized link
     * so they can fill it in, sign it digitally and submit it back.
     */
    public function sendToExternal(Request $request, ExternalRequisition $external_requisition)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::EXTERNAL_REQUISITIONS_MANAGE)) {
            abort(403);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'contact_name' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $external_requisition->items()->exists()) {
            return back()->withErrors(['email' => 'Add at least one item before sending the form.']);
        }

        $external_requisition->update([
            'external_email' => $data['email'],
            'external_contact' => $data['contact_name'] ?? $external_requisition->external_contact,
            'share_token' => (string) \Illuminate\Support\Str::uuid(),
            'token_expires_at' => now()->addDays(7),
            'sent_to_external_at' => now(),
            'status' => 'awaiting_external',
        ]);

        $link = url('/share/external-requisitions/' . $external_requisition->share_token);

        \Illuminate\Support\Facades\Mail::to($data['email'])->send(
            new \App\Mail\ExternalShareMail($external_requisition->fresh(['items', 'preparedBy.employee']), $link)
        );

        \App\Support\AuditLogger::log('external_requisition.sent', ExternalRequisition::class, $external_requisition->id,
            "Sent {$external_requisition->requisition_number} to {$data['email']} for filling and signing");

        return back()->with('success', "Form sent to {$data['email']}. The link is valid for 7 days.");
    }

    /**
     * Internal decision at the caller's stage:
     * Technical Lead (confirm all) → HR → MD.
     * Feedback is emailed to the external party only when the chain completes.
     */
    public function approve(Request $request, ExternalRequisition $external_requisition)
    {
        $request->validate(['comments' => ['nullable', 'string', 'max:4000']]);

        app(\App\Services\ExternalApprovalService::class)
            ->decide($external_requisition, $request->user(), 'approved', $request->input('comments'));

        return back()->with('success', 'Decision recorded.');
    }

    public function reject(Request $request, ExternalRequisition $external_requisition)
    {
        $request->validate(['comments' => ['required', 'string', 'max:4000']]);

        app(\App\Services\ExternalApprovalService::class)
            ->decide($external_requisition, $request->user(), 'rejected', $request->input('comments'));

        return back()->with('success', 'Rejection recorded. The external party has been notified.');
    }

    /**
     * Issue (or refresh) the tokenized share link and return it as JSON,
     * so the user can copy it and deliver it through any channel.
     */
    public function shareLink(Request $request, ExternalRequisition $external_requisition)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::EXTERNAL_REQUISITIONS_MANAGE)) {
            abort(403);
        }

        if (! $external_requisition->items()->exists()) {
            return response()->json(['message' => 'Add at least one item before sharing the form.'], 422);
        }

        if (! $external_requisition->isTokenValid()) {
            $external_requisition->update([
                'share_token' => (string) \Illuminate\Support\Str::uuid(),
                'token_expires_at' => now()->addDays(7),
                'status' => $external_requisition->status === 'draft' ? 'awaiting_external' : $external_requisition->status,
            ]);

            \App\Support\AuditLogger::log('external_requisition.link_issued', ExternalRequisition::class, $external_requisition->id,
                "Issued share link for {$external_requisition->requisition_number}");
        }

        return response()->json([
            'url' => url('/share/external-requisitions/' . $external_requisition->share_token),
            'expires_at' => $external_requisition->token_expires_at?->toIso8601String(),
        ]);
    }

    public function pdf(ExternalRequisition $external_requisition)
    {
        if (! request()->user()->hasPermission(\App\Support\Permissions::EXTERNAL_REQUISITIONS_VIEW)) {
            abort(403);
        }

        AuditLogger::log('external_requisition.pdf', ExternalRequisition::class, $external_requisition->id,
            "Generated PDF for {$external_requisition->requisition_number}");

        return $this->pdf->externalRequisitionPdf($external_requisition)
            ->stream("external-requisition-{$external_requisition->requisition_number}.pdf");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'external_party_id' => ['nullable', 'exists:external_parties,id'],
            'external_party_name' => ['required', 'string', 'max:255'],
            'external_contact' => ['nullable', 'string', 'max:255'],
            'external_email' => ['nullable', 'email', 'max:190'],
            'external_phone' => ['nullable', 'string', 'max:40'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'client_reference' => ['nullable', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'max:4000'],
            'currency' => ['required', 'string', 'max:10'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'required_delivery_date' => ['nullable', 'date'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'requested_by_name' => ['nullable', 'string', 'max:255'],
            'department_text' => ['nullable', 'string', 'max:120'],
            'mode_of_payment' => ['nullable', \Illuminate\Validation\Rule::in(['bank_transfer', 'mobile_money', 'cash', 'cheque', 'other'])],
            'date_of_activity' => ['nullable', 'date'],
            'activity_budget' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,txt,zip'],
        ], [
            'external_party_name.required' => 'The external organisation name is required.',
        ]);
    }
}
