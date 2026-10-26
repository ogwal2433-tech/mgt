<?php

use App\Enums\RequisitionStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\PaymentService;
use App\Services\RequisitionWorkflowService;

function approveFully($req): void
{
    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');
    $md = makeUser('managing_director');

    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $req->requester);
    $service->approve($req->fresh(), $lead);
    $service->approve($req->fresh(), $hr);
    $service->approve($req->fresh(), $md, $req->requested_amount);
}

it('refuses to disburse before md approval', function () {
    $finance = makeUser('hr_finance');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff);

    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(PaymentService::class)->recordPayment($req->fresh(), $finance, '500.00', now()->toDateString(), 'cash', 'REF-1', 'Payee');
});

it('refuses to disburse more than the remaining approved amount', function () {
    $finance = makeUser('hr_finance');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff, ['requested_amount' => '1000.00']);
    approveFully($req);

    $service = app(PaymentService::class);

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $service->recordPayment($req->fresh(), $finance, '1500.00', now()->toDateString(), 'cash', 'REF-1', 'Payee');
});

it('supports partial payments with separate receipts and correct balances', function () {
    $finance = makeUser('hr_finance');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff, ['requested_amount' => '1000.00']);
    approveFully($req);

    $service = app(PaymentService::class);
    $service->recordPayment($req->fresh(), $finance, '400.00', now()->toDateString(), 'bank_transfer', 'TXN-1', 'Payee One');
    $req->refresh();
    expect($req->status)->toBe(RequisitionStatus::PartiallyDisbursed);
    expect((string) $req->amount_paid)->toBe('400.00');

    $service->recordPayment($req->fresh(), $finance, '600.00', now()->toDateString(), 'bank_transfer', 'TXN-2', 'Payee Two');
    $req->refresh();
    expect($req->status)->toBe(RequisitionStatus::FullyDisbursed);
    expect((string) $req->amount_paid)->toBe('1000.00');

    $receipts = PaymentReceipt::where('requisition_id', $req->id)->get();
    expect($receipts->count())->toBe(2);

    $first = $receipts->firstWhere('amount', '400.00');
    expect((string) $first->remaining_balance)->toBe('600.00');

    $second = $receipts->firstWhere('amount', '600.00');
    expect((string) $second->remaining_balance)->toBe('0.00');
});

it('prevents duplicate payments for the same transaction reference', function () {
    $finance = makeUser('hr_finance');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff, ['requested_amount' => '1000.00']);
    approveFully($req);

    $service = app(PaymentService::class);
    $service->recordPayment($req->fresh(), $finance, '100.00', now()->toDateString(), 'cash', 'DUP-REF', 'Payee');

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $service->recordPayment($req->fresh(), $finance, '100.00', now()->toDateString(), 'cash', 'DUP-REF', 'Payee');
});

it('generates a unique receipt and notifies the requester for every completed payment', function () {
    $finance = makeUser('hr_finance');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff, ['requested_amount' => '1000.00']);
    approveFully($req);

    app(PaymentService::class)->recordPayment($req->fresh(), $finance, '1000.00', now()->toDateString(), 'cash', 'TXN-9', 'Payee');

    $receipt = PaymentReceipt::where('requisition_id', $req->id)->first();
    expect($receipt)->not->toBeNull();
    expect($receipt->receipt_number)->toStartWith('RCT-');
    expect($receipt->requester_user_id)->toBe($staff->id);
    expect(\App\Models\Notification::where('user_id', $staff->id)->where('type', 'payment.completed')->count())->toBeGreaterThan(0);
});

it('refuses to pay a rejected requisition', function () {
    $finance = makeUser('hr_finance');
    $lead = makeUser('technical_lead');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff);

    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);
    $service->reject($req->fresh(), $lead, 'No budget');

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(PaymentService::class)->recordPayment($req->fresh(), $finance, '100.00', now()->toDateString(), 'cash', 'TXN-X', 'Payee');
});

it('preserves payment history when a payment is reversed', function () {
    $finance = makeUser('hr_finance');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff, ['requested_amount' => '1000.00']);
    approveFully($req);

    $service = app(PaymentService::class);
    $payment = $service->recordPayment($req->fresh(), $finance, '1000.00', now()->toDateString(), 'cash', 'TXN-R', 'Payee');

    $service->reverse($payment, $finance, 'Incorrect amount');

    $req->refresh();
    expect((string) $req->amount_paid)->toBe('0.00');
    expect($req->status)->toBe(RequisitionStatus::ApprovedAwaitingDisbursement);

    // Original payment row is preserved and marked reversed.
    expect(Payment::find($payment->id)->status)->toBe('reversed');
    expect(Payment::where('is_reversal', true)->count())->toBe(1);

    $actions = AuditLog::where('action', 'payment.reversed')->count();
    expect($actions)->toBe(1);
});

it('requires the finance disburse permission', function () {
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff, ['requested_amount' => '1000.00']);
    approveFully($req);

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(PaymentService::class)->recordPayment($req->fresh(), $staff, '100.00', now()->toDateString(), 'cash', 'TXN-P', 'Payee');
});
