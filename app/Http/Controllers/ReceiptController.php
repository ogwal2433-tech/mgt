<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceipt;
use App\Services\PaymentService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly PdfService $pdf,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = PaymentReceipt::with(['payment:id,payment_number,payment_method', 'requisition:id,requisition_number', 'requester:id,username']);

        if (! $user->hasPermission(\App\Support\Permissions::FINANCE_VIEW)) {
            $query->where('requester_user_id', $user->id);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('payee_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('delivery_status')->toString()) {
            $query->where('delivery_status', $status);
        }

        $receipts = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Finance/Receipts', [
            'receipts' => $receipts,
            'filters' => $request->only(['search', 'delivery_status']),
        ]);
    }

    public function show(Request $request, PaymentReceipt $receipt)
    {
        $user = $request->user();

        if (! $user->hasPermission(\App\Support\Permissions::FINANCE_VIEW) && $receipt->requester_user_id !== $user->id) {
            abort(403, 'You are not authorised to view this receipt.');
        }

        $receipt->load(['payment', 'requisition.requesterEmployee', 'requester', 'generatedBy', 'document']);

        return Inertia::render('Finance/ReceiptShow', ['receipt' => $receipt]);
    }

    public function pdf(PaymentReceipt $receipt)
    {
        $user = request()->user();
        if (! $user->hasPermission(\App\Support\Permissions::FINANCE_VIEW) && $receipt->requester_user_id !== $user->id) {
            abort(403, 'You are not authorised to download this receipt.');
        }

        return $this->pdf->receiptPdf($receipt)->stream("receipt-{$receipt->receipt_number}.pdf");
    }

    public function retryDelivery(Request $request, PaymentReceipt $receipt)
    {
        if (! $request->user()->hasPermission(\App\Support\Permissions::FINANCE_DISBURSE)) {
            abort(403);
        }

        $this->payments->retryReceiptDelivery($receipt);

        return back()->with('success', 'Receipt delivery retried.');
    }
}
