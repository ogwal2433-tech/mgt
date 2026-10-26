<?php

use App\Enums\RequisitionStatus;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\RequisitionApproval;
use App\Services\PaymentService;
use App\Services\RequisitionWorkflowService;

it('requires a mandatory rejection reason', function () {
    $lead = makeUser('technical_lead');
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff);

    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);

    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingTechnicalLead);

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $service->reject($req->fresh(), $lead, '   ');
});

it('enforces the sequential approval order technical lead -> hr -> md', function () {
    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');
    $md = makeUser('managing_director');
    $staff = makeUser('technical_staff');

    $req = makeRequisition($staff);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);

    $rejects = function ($user, $message) use ($service, $req) {
        try {
            $service->approve($req->fresh(), $user);
            $this->fail($message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            expect(array_keys($e->errors()))->not->toBeEmpty();
        }
    };

    // HR cannot act before Technical Lead approval.
    $rejects($hr, 'HR must not review before Technical Lead approval.');

    // MD cannot act before HR approval.
    $rejects($md, 'MD must not approve before HR approval.');

    $service->approve($req->fresh(), $lead);
    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingHrReview);

    // MD still cannot act before HR approval.
    $rejects($md, 'MD must not approve before HR approval.');

    $service->approve($req->fresh(), $hr);
    expect($req->fresh()->status)->toBe(RequisitionStatus::PendingMdApproval);

    $service->approve($req->fresh(), $md, '1000.00');
    $fresh = $req->fresh();

    expect($fresh->status)->toBe(RequisitionStatus::ApprovedAwaitingDisbursement);
    expect((string) $fresh->approved_amount)->toBe('1000.00');

    // The approval history contains every approval stage in order.
    $stages = RequisitionApproval::where('requisition_id', $req->id)
        ->where('action', 'approved')
        ->orderBy('id')
        ->pluck('stage')
        ->all();
    expect($stages)->toBe(['technical_lead', 'hr', 'md']);
});

it('prevents users from approving their own requisition', function () {
    $lead = makeUser('technical_lead');
    $req = makeRequisition($lead);

    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $lead);

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $service->approve($req->fresh(), $lead);
});

it('stops a rejected requisition from moving forward', function () {
    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');
    $staff = makeUser('technical_staff');

    $req = makeRequisition($staff);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);
    $service->reject($req->fresh(), $lead, 'Not justified');

    expect($req->fresh()->status)->toBe(RequisitionStatus::RejectedByTechnicalLead);

    try {
        $service->approve($req->fresh(), $hr);
        $this->fail('A rejected requisition must not proceed to HR.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('status');
    }
});

it('allows the md to approve a lower amount', function () {
    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');
    $md = makeUser('managing_director');
    $staff = makeUser('technical_staff');

    $req = makeRequisition($staff);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);
    $service->approve($req->fresh(), $lead);
    $service->approve($req->fresh(), $hr);
    $service->approve($req->fresh(), $md, '400.00');

    $fresh = $req->fresh();
    expect((string) $fresh->requested_amount)->toBe('1000.00');
    expect((string) $fresh->approved_amount)->toBe('400.00');
});

it('records a complete audit trail for every decision', function () {
    $lead = makeUser('technical_lead');
    $staff = makeUser('technical_staff');

    $req = makeRequisition($staff);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);
    $service->approve($req->fresh(), $lead);

    $actions = AuditLog::where('entity_id', $req->id)->pluck('action')->all();
    expect($actions)->toContain('requisition.submitted');
    expect($actions)->toContain('requisition.approved');
});

it('notifies the requester and the next approver on decisions', function () {
    $lead = makeUser('technical_lead');
    $hr = makeUser('hr');
    $staff = makeUser('technical_staff');

    $req = makeRequisition($staff);
    $service = app(RequisitionWorkflowService::class);
    $service->submit($req, $staff);

    expect(Notification::where('user_id', $lead->id)->count())->toBeGreaterThan(0);

    $service->approve($req->fresh(), $lead);

    expect(Notification::where('user_id', $staff->id)->count())->toBeGreaterThan(0);
    expect(Notification::where('user_id', $hr->id)->count())->toBeGreaterThan(0);
});

it('supports service and activity lines for development work', function () {
    $staff = makeUser('technical_staff');

    // A requisition paying for software development: an activity milestone
    // plus a service line (hours x rate) � no goods quantities involved.
    $requisition = \App\Models\Requisition::create([
        'requisition_number' => null,
        'requester_user_id' => $staff->id,
        'requester_employee_id' => $staff->employee_id,
        'department_id' => $staff->employee?->department_id,
        'category_id' => \App\Models\RequisitionCategory::first()->id,
        'title' => 'Payment for software development � milestone 2',
        'purpose' => 'Backend API development and integration',
        'currency' => 'UGX',
        'requested_amount' => '0.00',
        'priority' => 'normal',
        'status' => \App\Enums\RequisitionStatus::Draft,
    ]);

    \App\Models\RequisitionItem::create([
        'requisition_id' => $requisition->id,
        'description' => 'Backend API development � milestone 2',
        'line_type' => 'activity',
        'quantity' => 1,
        'period' => 'Milestone 2',
        'unit_cost' => '2500000.00',
        'total_cost' => \App\Models\RequisitionItem::computeLineTotal([
            'line_type' => 'activity', 'quantity' => 1, 'unit_cost' => '2500000.00',
        ]),
    ]);

    \App\Models\RequisitionItem::create([
        'requisition_id' => $requisition->id,
        'description' => 'Technical support retainer',
        'line_type' => 'service',
        'hours' => 20,
        'unit_cost' => '50000.00',
        'period' => 'Oct 2026',
        'total_cost' => \App\Models\RequisitionItem::computeLineTotal([
            'line_type' => 'service', 'hours' => 20, 'unit_cost' => '50000.00',
        ]),
    ]);

    $requisition->update([
        'requested_amount' => \App\Support\Money::sum(
            $requisition->items()->pluck('total_cost')->map(fn ($v) => (string) $v)->all()
        ),
    ]);

    expect((string) $requisition->fresh()->items()->first()->total_cost)->toBe('2500000.00');
    expect((string) $requisition->fresh()->items()->orderBy('id')->get()->get(1)->total_cost)->toBe('1000000.00');
    expect((string) $requisition->fresh()->requested_amount)->toBe('3500000.00');

    // The workflow handles activity requisitions exactly like goods ones.
    $service = app(\App\Services\RequisitionWorkflowService::class);
    $service->submit($requisition->fresh(), $staff);
    expect($requisition->fresh()->status)->toBe(\App\Enums\RequisitionStatus::PendingTechnicalLead);
});
