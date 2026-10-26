<?php

namespace App\Services;

use App\Enums\RequisitionStatus;
use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\PaymentReceipt;
use App\Models\Requisition;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\NotificationService;
use App\Support\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly PdfService $pdf) {}

    /**
     * Record a disbursement against an MD-approved requisition.
     * Partial payments are supported; each completed payment produces its own receipt.
     */
    public function recordPayment(
        Requisition $requisition,
        User $actor,
        string $amount,
        string $paymentDate,
        string $method,
        ?string $transactionReference,
        string $payeeName,
        ?string $payeeDetails = null,
        ?string $notes = null,
        ?int $evidenceDocumentId = null,
    ): Payment {
        return DB::transaction(function () use ($requisition, $actor, $amount, $paymentDate, $method, $transactionReference, $payeeName, $payeeDetails, $notes, $evidenceDocumentId) {
            /** @var Requisition $req */
            $req = Requisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();

            if (! $actor->hasPermission(\App\Support\Permissions::FINANCE_DISBURSE)) {
                throw ValidationException::withMessages(['actor' => 'You are not authorised to disburse funds.']);
            }

            $previousStatus = $req->status;

            if ($req->status->isRejected() || $req->status === RequisitionStatus::Cancelled || $req->status === RequisitionStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => 'A rejected, cancelled or draft requisition cannot be paid.',
                ]);
            }

            if (! in_array($req->status, [RequisitionStatus::ApprovedAwaitingDisbursement, RequisitionStatus::PartiallyDisbursed], true)) {
                throw ValidationException::withMessages([
                    'status' => "Requisition is not eligible for payment (status: {$req->status->label()}).",
                ]);
            }

            // Workflow integrity: every approval stage in the requisition's chain
            // must have a recorded decision before money can move.
            $chain = is_array($req->approval_chain) && $req->approval_chain
                ? $req->approval_chain
                : ['technical_lead', 'hr', 'md'];
            $stages = $req->approvals()->pluck('stage')->all();
            foreach ($chain as $requiredStage) {
                if (! in_array($requiredStage, $stages, true)) {
                    throw ValidationException::withMessages([
                        'workflow' => "Missing {$requiredStage} approval. Payments are not permitted until every stage is approved.",
                    ]);
                }
            }

            $normalized = Money::normalize($amount);
            if (Money::compare($normalized, '0.00') <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            $remaining = Money::sub((string) ($req->approved_amount ?? '0.00'), (string) $req->amount_paid);
            if (Money::gt($normalized, $remaining)) {
                throw ValidationException::withMessages([
                    'amount' => "Payment exceeds the remaining approved amount of {$remaining}.",
                ]);
            }

            if ($transactionReference) {
                $duplicate = Payment::query()
                    ->where('requisition_id', $req->id)
                    ->where('transaction_reference', $transactionReference)
                    ->where('status', '!=', 'reversed')
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'transaction_reference' => 'A payment with this transaction reference already exists for this requisition.',
                    ]);
                }
            }

            $payment = Payment::create([
                'payment_number' => Sequence::next('payment', 'PAY'),
                'requisition_id' => $req->id,
                'amount' => $normalized,
                'currency' => $req->currency,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'transaction_reference' => $transactionReference,
                'payee_name' => $payeeName,
                'payee_details' => $payeeDetails,
                'recorded_by_user_id' => $actor->id,
                'status' => 'completed',
                'evidence_document_id' => $evidenceDocumentId,
                'notes' => $notes,
            ]);

            $req->amount_paid = Money::add((string) $req->amount_paid, $normalized);
            $req->status = Money::gte($req->amount_paid, (string) ($req->approved_amount ?? '0.00'))
                ? RequisitionStatus::FullyDisbursed
                : RequisitionStatus::PartiallyDisbursed;
            if ($req->accountability_required || $req->is_advance) {
                $req->current_stage = 'accountability';
            }
            $req->save();

            AuditLogger::log('payment.recorded', Payment::class, $payment->id,
                "Recorded {$normalized} {$req->currency} against {$req->requisition_number}",
                ['amount_paid' => $req->getOriginal('amount_paid')],
                ['amount_paid' => $req->amount_paid, 'payment_id' => $payment->payment_number]);

            // Stage 6: receipt generation is automatic for completed payments.
            $receipt = $this->generateReceipt($payment, $actor);

            NotificationService::notify(
                $req->requester,
                'payment.completed',
                'Payment completed – receipt available',
                "Payment {$payment->payment_number} of {$normalized} {$req->currency} for requisition {$req->requisition_number} has been completed. Receipt {$receipt->receipt_number} is available.",
                "/receipts/{$receipt->id}",
                ['payment_id' => $payment->id, 'receipt_id' => $receipt->id]
            );

            PaymentNotification::create([
                'payment_id' => $payment->id,
                'receipt_id' => $receipt->id,
                'user_id' => $req->requester_user_id,
                'channel' => 'in_app',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $payment->refresh();
        });
    }

    public function generateReceipt(Payment $payment, User $actor): PaymentReceipt
    {
        $requisition = $payment->requisition;
        $remaining = Money::sub((string) ($requisition->approved_amount ?? '0.00'), (string) $requisition->amount_paid);

        $receipt = PaymentReceipt::create([
            'receipt_number' => Sequence::next('receipt', 'RCT'),
            'payment_id' => $payment->id,
            'requisition_id' => $requisition->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'remaining_balance' => $remaining,
            'requester_user_id' => $requisition->requester_user_id,
            'payee_name' => $payment->payee_name,
            'purpose' => $requisition->title,
            'payment_method' => $payment->payment_method,
            'payment_reference' => $payment->transaction_reference,
            'payment_date' => $payment->payment_date,
            'generated_by_user_id' => $actor->id,
            'generated_at' => now(),
            'delivery_status' => 'pending',
        ]);

        $document = $this->pdf->storeReceiptPdf($receipt);
        $receipt->update(['document_id' => $document->id]);

        $delivery = NotificationService::email(
            $requisition->requester,
            "Payment receipt {$receipt->receipt_number}",
            "Your payment receipt {$receipt->receipt_number} for requisition {$requisition->requisition_number} is ready. Amount: {$receipt->amount} {$receipt->currency}.",
            'receipt',
            ['receipt_number' => $receipt->receipt_number],
            PaymentReceipt::class,
            $receipt->id
        );

        $receipt->update([
            'delivery_status' => $delivery->status === 'sent' ? 'sent' : 'failed',
            'delivered_at' => $delivery->sent_at,
            'delivery_error' => $delivery->error,
        ]);

        AuditLogger::log('receipt.generated', PaymentReceipt::class, $receipt->id,
            "Generated receipt {$receipt->receipt_number}", null, ['amount' => $receipt->amount]);

        return $receipt;
    }

    public function retryReceiptDelivery(PaymentReceipt $receipt): PaymentReceipt
    {
        $delivery = NotificationService::email(
            $receipt->requester,
            "Payment receipt {$receipt->receipt_number}",
            "Your payment receipt {$receipt->receipt_number} for requisition {$receipt->requisition->requisition_number} is ready. Amount: {$receipt->amount} {$receipt->currency}.",
            'receipt_retry',
            ['receipt_number' => $receipt->receipt_number],
            PaymentReceipt::class,
            $receipt->id
        );

        return $receipt->update([
            'delivery_status' => $delivery->status === 'sent' ? 'sent' : 'failed',
            'delivered_at' => $delivery->sent_at,
            'delivery_error' => $delivery->error,
            'retry_count' => $receipt->retry_count + 1,
        ]) ? $receipt->refresh() : $receipt;
    }

    /**
     * Financial corrections are reversals: the original payment row is preserved.
     */
    public function reverse(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            /** @var Payment $payment */
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'reversed' || $payment->is_reversal) {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            }

            $requisition = $payment->requisition;

            $reversal = Payment::create([
                'payment_number' => Sequence::next('payment', 'PAY'),
                'requisition_id' => $requisition->id,
                'amount' => Money::mul($payment->amount, '-1'),
                'currency' => $payment->currency,
                'payment_date' => now()->toDateString(),
                'payment_method' => $payment->payment_method,
                'transaction_reference' => 'REVERSAL-' . $payment->payment_number,
                'payee_name' => $payment->payee_name,
                'recorded_by_user_id' => $actor->id,
                'status' => 'completed',
                'notes' => "Reversal of {$payment->payment_number}: {$reason}",
                'is_reversal' => true,
                'reversal_of_payment_id' => $payment->id,
            ]);

            $payment->update(['status' => 'reversed']);

            $requisition->amount_paid = Money::add((string) $requisition->amount_paid, Money::mul($payment->amount, '-1'));
            $requisition->status = Money::compare((string) $requisition->amount_paid, '0.00') <= 0
                ? RequisitionStatus::ApprovedAwaitingDisbursement
                : RequisitionStatus::PartiallyDisbursed;
            $requisition->save();

            AuditLogger::log('payment.reversed', Payment::class, $payment->id,
                "Reversed payment {$payment->payment_number}", ['status' => 'completed'], ['status' => 'reversed', 'reason' => $reason]);

            return $reversal;
        });
    }
}
