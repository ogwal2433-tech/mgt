<?php

/**
 * Smoke tests: every major page renders for the role that is supposed to use it.
 * These catch template/runtime errors that unit tests would miss.
 */
it('renders every module page for the managing director', function () {
    $md = makeUser('managing_director');

    foreach ([
        '/dashboard', '/employees', '/contracts', '/requisitions', '/approvals',
        '/finance', '/disbursements', '/receipts', '/accountability',
        '/external-requisitions', '/projects', '/technical', '/reports', '/notifications',
    ] as $uri) {
        $this->actingAs($md)->get($uri)->assertOk();
    }
});

it('renders every module page for hr', function () {
    $hr = makeUser('hr');

    foreach ([
        '/dashboard', '/employees', '/hr', '/contracts', '/requisitions', '/approvals',
        '/finance', '/disbursements', '/receipts', '/accountability',
        '/external-requisitions', '/procurement', '/inventory', '/attendance',
        '/reports', '/notifications',
    ] as $uri) {
        $this->actingAs($hr)->get($uri)->assertOk();
    }
});

it('renders every module page for the technical lead', function () {
    $lead = makeUser('technical_lead');

    foreach ([
        '/dashboard', '/technical', '/projects', '/requisitions', '/approvals',
        '/external-requisitions', '/receipts', '/notifications',
    ] as $uri) {
        $this->actingAs($lead)->get($uri)->assertOk();
    }
});

it('renders the staff dashboard, requisitions and receipts', function () {
    $staff = makeUser('technical_staff');

    foreach (['/dashboard', '/requisitions', '/receipts', '/accountability', '/notifications', '/projects'] as $uri) {
        $this->actingAs($staff)->get($uri)->assertOk();
    }
});

it('renders settings and audit for the system administrator', function () {
    $admin = makeUser('system_admin');

    foreach (['/dashboard', '/users', '/settings', '/audit', '/notifications'] as $uri) {
        $this->actingAs($admin)->get($uri)->assertOk();
    }
});

it('renders the requisition form and detail pages', function () {
    $staff = makeUser('technical_staff');
    $req = makeRequisition($staff);

    $this->actingAs($staff)->get('/requisitions/create')->assertOk();
    $this->actingAs($staff)->get("/requisitions/{$req->id}")->assertOk();
});

it('generates the receipt and external requisition pdfs', function () {
    $finance = makeUser('hr_finance');
    $lead = makeUser('technical_lead');
    $staff = makeUser('technical_staff');

    $req = makeRequisition($staff, ['requested_amount' => '500.00']);
    $service = app(\App\Services\RequisitionWorkflowService::class);
    $service->submit($req, $staff);

    $tl = makeUser('technical_lead');
    $hr = makeUser('hr');
    $md = makeUser('managing_director');
    $service->approve($req->fresh(), $tl);
    $service->approve($req->fresh(), $hr);
    $service->approve($req->fresh(), $md, '500.00');

    $payment = app(\App\Services\PaymentService::class)
        ->recordPayment($req->fresh(), $finance, '500.00', now()->toDateString(), 'cash', 'SMOKE-1', 'Payee');

    $receipt = $payment->receipts()->first();
    $this->actingAs($staff)->get(\App\Support\Links::receiptPdf($receipt))->assertOk();

    $external = \App\Models\ExternalRequisition::create([
        'requisition_number' => \App\Support\Sequence::next('external_requisition', 'EXT'),
        'date' => now()->toDateString(),
        'external_party_name' => 'ACME Supplies',
        'external_contact' => 'Jane',
        'prepared_by_user_id' => $lead->id,
        'purpose' => 'Equipment purchase',
        'currency' => 'USD',
        'total_amount' => '250.00',
        'status' => 'draft',
    ]);

    \App\Models\ExternalRequisitionItem::create([
        'external_requisition_id' => $external->id,
        'description' => 'Router',
        'quantity' => 1,
        'unit_price' => '250.00',
        'total_price' => '250.00',
    ]);

    $this->actingAs($lead)->get(\App\Support\Links::externalPdf($external))->assertOk();
    $this->actingAs($lead)->get("/external-requisitions/{$external->id}")->assertOk();
});
