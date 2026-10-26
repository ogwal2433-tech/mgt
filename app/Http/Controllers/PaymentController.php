<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Models\Payment;
use App\Models\Requisition;
use App\Services\PaymentService;
use App\Support\AuditLogger;
use App\Support\DocumentService;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Payment::with(['requisition:id,requisition_number,title,currency', 'recordedBy:id,username']);

        if (! $user->hasPermission(\App\Support\Permissions::FINANCE_VIEW)) {
            $query->whereHas('requisition', fn ($q) => $q->where('requester_user_id', $user->id));
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('payee_name', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $payments = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Finance/Payments', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request, Requisition $requisition)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'payee_name' => ['required', 'string', 'max:255'],
            'payee_details' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg'],
        ]);

        $evidenceDocumentId = null;
        if ($request->hasFile('evidence')) {
            $document = DocumentService::store($request->file('evidence'), 'payment_evidence', $requisition);
            $evidenceDocumentId = $document->id;
        }

        $this->payments->recordPayment(
            $requisition,
            $request->user(),
            Money::normalize($data['amount']),
            $data['payment_date'],
            $data['payment_method'],
            $data['transaction_reference'] ?? null,
            $data['payee_name'],
            $data['payee_details'] ?? null,
            $data['notes'] ?? null,
            $evidenceDocumentId,
        );

        return back()->with('success', 'Payment recorded and receipt generated.');
    }

    public function reverse(Request $request, Payment $payment)
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $this->payments->reverse($payment, $request->user(), $request->input('reason'));

        return back()->with('success', 'Payment reversed. The original record is preserved for audit.');
    }

    public function disbursementQueue(Request $request)
    {
        $user = $request->user();

        $query = Requisition::with(['requesterEmployee:id,first_name,last_name,employee_code', 'department:id,name', 'category:id,name'])
            ->whereIn('status', [RequisitionStatus::ApprovedAwaitingDisbursement, RequisitionStatus::PartiallyDisbursed]);

        if (! $user->hasPermission(\App\Support\Permissions::FINANCE_VIEW)) {
            $query->where('requester_user_id', $user->id);
        }

        $requisitions = $query->orderBy('id')->paginate(15)->withQueryString();

        return Inertia::render('Finance/Disbursements', [
            'requisitions' => $requisitions->through(function ($r) {
                return [
                    'id' => $r->id,
                    'requisition_number' => $r->requisition_number,
                    'title' => $r->title,
                    'status' => $r->status->value,
                    'status_label' => $r->status->label(),
                    'requester' => $r->requesterEmployee?->full_name,
                    'department' => $r->department?->name,
                    'category' => $r->category?->name,
                    'requested_amount' => (string) $r->requested_amount,
                    'approved_amount' => (string) $r->approved_amount,
                    'amount_paid' => (string) $r->amount_paid,
                    'remaining' => $r->remainingApprovedAmount(),
                    'currency' => $r->currency,
                ];
            }),
            'canDisburse' => $user->hasPermission(\App\Support\Permissions::FINANCE_DISBURSE),
        ]);
    }
}
