<?php

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Services\RequisitionWorkflowService;

it('routes hr requisitions to md approval without the technical lead', function () {
    $hr = makeUser('hr');
    $md = makeUser('managing_director');

    $req = makeRequisition($hr);
    $service = app(RequisitionWorkflowService::class);

    // The HR requester holds HR review, so the chain is MD only.
    $service->submit($req, $hr);
    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingMdApproval);
    expect($req->fresh()->approval_chain)->toBe(['md']);

    $service->approve($req->fresh(), $md, '1000.00');

    $fresh = $req->fresh();
    expect($fresh->status)->toBe(RequisitionStatus::ApprovedAwaitingDisbursement);
    expect((string) $fresh->approved_amount)->toBe('1000.00');
});

it('routes hr officer requisitions through hr review and then md', function () {
    $finance = makeUser('hr_finance'); // HR role without the HR review permission
    $hr = makeUser('hr');
    $md = makeUser('managing_director');

    $req = makeRequisition($finance);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $finance);

    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingHrReview);
    expect($req->fresh()->approval_chain)->toBe(['hr', 'md']);

    $service->approve($req->fresh(), $hr);
    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingMdApproval);

    $service->approve($req->fresh(), $md);
    expect($req->fresh()->status)->toBe(RequisitionStatus::ApprovedAwaitingDisbursement);
});

it('requires hr approval for md requisitions', function () {
    $md = makeUser('managing_director');
    $hr = makeUser('hr');

    $req = makeRequisition($md);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $md);

    // The MD cannot approve their own request — HR must approve it.
    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingHrReview);
    expect($req->fresh()->approval_chain)->toBe(['hr']);

    try {
        $service->approve($req->fresh(), $md);
        $this->fail('The MD must not approve their own requisition.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect(array_keys($e->errors()))->not->toBeEmpty();
    }

    $service->approve($req->fresh(), $hr, '1000.00');

    $fresh = $req->fresh();
    expect($fresh->status)->toBe(RequisitionStatus::ApprovedAwaitingDisbursement);
    expect((string) $fresh->approved_amount)->toBe('1000.00');
});

it('skips the technical lead stage for the lead s own requisition', function () {
    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');
    $md = makeUser('managing_director');

    $req = makeRequisition($lead);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $lead);

    expect($req->fresh()->approval_chain)->toBe(['hr', 'md']);
    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingHrReview);

    $service->approve($req->fresh(), $hr);
    $service->approve($req->fresh(), $md);

    expect($req->fresh()->status)->toBe(RequisitionStatus::ApprovedAwaitingDisbursement);
});

it('pays a requisition after its computed chain is complete', function () {
    $mdRequester = makeUser('managing_director');
    $hr = makeUser('hr');
    $finance = makeUser('hr_finance');

    $req = makeRequisition($mdRequester, ['requested_amount' => '750.00']);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $mdRequester);
    $service->approve($req->fresh(), $hr, '750.00');

    $payment = app(\App\Services\PaymentService::class)->recordPayment(
        $req->fresh(), $finance, '750.00', now()->toDateString(), 'cash', 'PAY-MD-1', 'Managing Director'
    );

    expect($payment->requisition->status)->toBe(RequisitionStatus::FullyDisbursed);
    expect($payment->receipts()->count())->toBe(1);
});
