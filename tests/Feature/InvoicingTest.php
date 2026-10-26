<?php

use App\Models\Invoice;
use App\Services\FinanceReportService;
use App\Services\InvoiceService;

function makeInvoice(array $overrides = [], array $items = [['description' => 'Web development', 'quantity' => 1, 'unit_price' => '5000.00']]): Invoice
{
    $hr = makeUser('hr');

    return app(InvoiceService::class)->create(array_merge([
        'client_name' => 'Acme Corporation',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency' => 'USD',
        'tax_rate' => '0',
    ], $overrides), $hr, $items);
}

it('creates an invoice with items and computes totals', function () {
    $invoice = makeInvoice(['tax_rate' => '16'], [
        ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => '1000.00'],
        ['description' => 'Licenses', 'quantity' => 1, 'unit_price' => '500.00'],
    ]);

    expect($invoice->invoice_number)->toStartWith('INV-');
    expect((string) $invoice->subtotal)->toBe('2500.00');
    expect((string) $invoice->tax_amount)->toBe('400.00');
    expect((string) $invoice->total_amount)->toBe('2900.00');
    expect((string) $invoice->amount_due)->toBe('2900.00');
    expect($invoice->status)->toBe('draft');
});

it('records client payments and clears the invoice when paid', function () {
    $invoice = makeInvoice();
    $hr = makeUser('hr');
    $service = app(InvoiceService::class);

    $service->recordPayment($invoice, $hr, '2000.00', now()->toDateString(), 'bank_transfer', 'TXN-1');
    $invoice->refresh();
    expect($invoice->status)->toBe('partially_paid');
    expect((string) $invoice->amount_due)->toBe('3000.00');

    // The clear action settles the remaining balance in one step.
    $service->clear($invoice->fresh(), $hr, 'cash', 'CLEARED-1');

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect((string) $invoice->amount_due)->toBe('0.00');
    expect($invoice->payments()->count())->toBe(2);
    expect($invoice->agingBucket())->toBe('paid');
});

it('refuses to overpay an invoice', function () {
    $invoice = makeInvoice();
    $hr = makeUser('hr');

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    app(InvoiceService::class)->recordPayment($invoice, $hr, '99999.00', now()->toDateString(), 'cash', 'TXN-X');
});

it('groups outstanding invoices into aging buckets', function () {
    $current = makeInvoice(['client_name' => 'Current Co', 'due_date' => now()->addDays(10)->toDateString()]);
    $late = makeInvoice(['client_name' => 'Late Co', 'issue_date' => now()->subDays(50)->toDateString(), 'due_date' => now()->subDays(20)->toDateString()]);
    makeInvoice(['client_name' => 'Paid Co'])->update(['status' => 'paid', 'amount_paid' => '5000.00', 'amount_due' => '0.00']);

    $aging = app(FinanceReportService::class)->aging();

    $rows = collect($aging['rows']);
    expect($rows->pluck('client_name')->all())->toContain('Current Co', 'Late Co');
    expect($rows->pluck('client_name')->all())->not->toContain('Paid Co');

    $currentRow = $rows->firstWhere('client_name', 'Current Co');
    expect($currentRow['bucket'])->toBe('current');

    $lateRow = $rows->firstWhere('client_name', 'Late Co');
    expect($lateRow['bucket'])->toBe('1_30');
    expect($lateRow['days_overdue'])->toBeGreaterThan(0);

    expect($aging['totals']['current'])->toBe('5000.00');
    expect($aging['grand_total'])->toBe('10000.00');
});

it('tracks every cash movement in the cashbook with a running balance', function () {
    $hr = makeUser('hr');
    $finance = makeUser('hr_finance');
    $staff = makeUser('technical_staff');

    $invoice = makeInvoice();
    app(InvoiceService::class)->recordPayment($invoice, $hr, '5000.00', now()->toDateString(), 'bank_transfer', 'IN-1');

    $req = makeRequisition($staff, ['requested_amount' => '800.00']);
    $workflow = app(\App\Services\RequisitionWorkflowService::class);
    $workflow->submit($req, $staff);
    $workflow->approve($req->fresh(), makeUser('technical_lead'));
    $workflow->approve($req->fresh(), makeUser('hr'));
    $workflow->approve($req->fresh(), makeUser('managing_director'), '800.00');
    app(\App\Services\PaymentService::class)->recordPayment($req->fresh(), $finance, '800.00', now()->toDateString(), 'cash', 'OUT-1', 'Payee');

    $cashbook = app(FinanceReportService::class)->cashbook(now()->startOfMonth()->toDateString(), now()->toDateString());

    expect(count($cashbook['entries']))->toBeGreaterThanOrEqual(2);
    expect($cashbook['total_in'])->toBe('5000.00');
    expect($cashbook['total_out'])->toBe('800.00');
    expect($cashbook['closing_balance'])->toBe('4200.00');

    // Categories are tracked individually.
    expect($cashbook['category_totals']['client_receipt']['money_in'])->toBe('5000.00');
    expect($cashbook['category_totals']['disbursement']['money_out'])->toBe('800.00');

    // Every entry carries a running balance.
    $last = end($cashbook['entries']);
    expect($last['balance'])->toBe('4200.00');
});

it('summarises finance activity by category', function () {
    $hr = makeUser('hr');

    $invoice = makeInvoice();
    app(InvoiceService::class)->recordPayment($invoice, $hr, '5000.00', now()->toDateString(), 'bank_transfer', 'IN-1');

    $summary = app(FinanceReportService::class)->summary(now()->startOfMonth()->toDateString(), now()->toDateString());

    expect($summary['invoiced'])->toBe('5000.00');
    expect($summary['collected'])->toBe('5000.00');
    expect($summary['categories']['Client receipts'])->toBe('5000.00');
});

it('restricts invoicing to authorised users', function () {
    $staff = makeUser('technical_staff');
    $invoice = makeInvoice();

    $this->actingAs($staff)->get('/invoices')->assertForbidden();
    $this->actingAs($staff)->post("/invoices/{$invoice->id}/payments", [
        'amount' => 1, 'payment_date' => now()->toDateString(), 'payment_method' => 'cash',
    ])->assertForbidden();
    $this->actingAs($staff)->get('/reports/finance')->assertForbidden();

    $hr = makeUser('hr');
    $this->actingAs($hr)->get('/invoices')->assertOk();
    $this->actingAs($hr)->get('/reports/finance')->assertOk();
});

it('tokenizes download urls with signed expiring links', function () {
    $hr = makeUser('hr');
    $invoice = makeInvoice();

    // The serialized invoice carries a signed, expiring PDF link.
    expect($invoice->pdf_url)->toContain('signature=');
    expect($invoice->pdf_url)->toContain('expires=');

    // Unsigned or tampered links are rejected.
    $this->actingAs($hr)->get("/invoices/{$invoice->id}/pdf")->assertStatus(403);

    // The signed link works for the authorised user.
    $this->actingAs($hr)->get($invoice->pdf_url)->assertOk();

    // An expired signature is rejected.
    $expired = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'invoices.pdf',
        now()->subMinutes(5),
        ['invoice' => $invoice->id]
    );
    $this->actingAs($hr)->get($expired)->assertStatus(403);
});
