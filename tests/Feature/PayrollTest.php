<?php

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\PayPeriod;
use App\Models\SalaryComponent;
use Illuminate\Support\Facades\Mail;

function makeSalariedEmployee(array $overrides = []): Employee
{
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\CompanyDataSeeder())->run();
    (new \Database\Seeders\PayrollSeeder())->run();

    $department = \App\Models\Department::query()->first();

    return Employee::create(array_merge([
        'employee_code' => 'EMP-P' . strtoupper(substr(md5(uniqid()), 0, 6)),
        'first_name' => 'Pay',
        'last_name' => 'Roller',
        'email' => uniqid() . '@payroll.test',
        'department_id' => $department?->id,
        'employment_status' => 'active',
        'employment_type' => 'full_time',
        'hire_date' => now()->subYear()->toDateString(),
        'basic_salary' => '3000.00',
        'is_active' => true,
    ], $overrides));
}

it('computes gross pay from basic salary plus stipends', function () {
    $hr = makeUser('hr');
    $employee = makeSalariedEmployee();

    EmployeeCompensation::create([
        'employee_id' => $employee->id,
        'salary_component_id' => SalaryComponent::where('code', 'HOUSING')->first()->id,
        'amount' => '500.00',
        'effective_from' => now()->startOfMonth()->toDateString(),
    ]);

    $period = PayPeriod::create([
        'name' => now()->format('Y-m'),
        'year' => (int) now()->format('Y'),
        'month' => (int) now()->format('n'),
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'status' => 'open',
    ]);

    $run = app(\App\Services\PayrollService::class)->compute($period, $hr);

    $line = $run->lines()->where('employee_id', $employee->id)->first();
    expect((string) $line->basic_salary)->toBe('3000.00');
    expect((string) $line->gross_amount)->toBe('3500.00');
});

it('computes taxes from the configured brackets', function () {
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\CompanyDataSeeder())->run();
    (new \Database\Seeders\PayrollSeeder())->run();

    $service = app(\App\Services\PayrollService::class);

    // Brackets: 0–1000 @ 0%, 1000–2500 @ 10%, 2500–5000 @ 15%
    // Taxable 3500: 0 + (1500 × 10%) + (1000 × 15%) = 150 + 150 = 300.00
    expect($service->computeTax('3500.00'))->toBe('300.00');
    expect($service->computeTax('500.00'))->toBe('0.00');
});

it('recovers outstanding advances in payroll and caps the recovery', function () {
    $hr = makeUser('hr');
    $finance = makeUser('hr_finance');
    $employee = makeSalariedEmployee();
    $employee->user()->create([
        'username' => 'adv-' . uniqid(),
        'name' => $employee->full_name,
        'email' => $employee->email,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    // Give the employee an outstanding advance of 1000.
    $req = makeRequisition($employee->user()->first(), ['requested_amount' => '1000.00']);
    $service = app(\App\Services\RequisitionWorkflowService::class);
    $service->submit($req, $employee->user()->first());
    $lead = makeUser('technical_lead');
    $service->approve($req->fresh(), $lead);
    $service->approve($req->fresh(), makeUser('hr'));
    $service->approve($req->fresh(), makeUser('managing_director'), '1000.00');
    app(\App\Services\PaymentService::class)->recordPayment($req->fresh(), $finance, '1000.00', now()->toDateString(), 'cash', 'ADV-1', 'Employee');

    expect(app(\App\Services\PayrollService::class)->outstandingAdvanceFor($employee))->toBe('1000.00');

    // Recovery mode default = 50% of outstanding → 500.
    $period = PayPeriod::create([
        'name' => now()->format('Y-m'), 'year' => (int) now()->format('Y'), 'month' => (int) now()->format('n'),
        'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(), 'status' => 'open',
    ]);

    $run = app(\App\Services\PayrollService::class)->compute($period, $hr);
    $line = $run->lines()->where('employee_id', $employee->id)->first();

    expect((string) $line->advance_amount)->toBe('500.00');
});

it('runs the full payroll lifecycle and delivers payslips', function () {
    Mail::fake();

    $hr = makeUser('hr');
    $employee = makeSalariedEmployee();
    $employee->user()->create([
        'username' => 'pay-' . uniqid(),
        'name' => $employee->full_name,
        'email' => $employee->email,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $period = PayPeriod::create([
        'name' => now()->format('Y-m'), 'year' => (int) now()->format('Y'), 'month' => (int) now()->format('n'),
        'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(), 'status' => 'open',
    ]);

    $service = app(\App\Services\PayrollService::class);

    $run = $service->compute($period, $hr);
    expect($run->status)->toBe('computed');

    // Payments are blocked before approval.
    try {
        $service->markPaid($run, $hr, now()->toDateString(), 'bank_transfer', 'BATCH-1');
        $this->fail('Payment must be blocked before approval.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('status');
    }

    $service->approve($run, makeUser('managing_director'));
    $run = $service->markPaid($run->fresh(), $hr, now()->toDateString(), 'bank_transfer', 'BATCH-1');

    expect($run->status)->toBe('paid');
    expect($run->payments()->count())->toBe(1);

    $line = $run->lines()->first();
    expect($line->status)->toBe('paid');
    expect($line->payslip_number)->toStartWith('PAYSLIP-');
    expect($line->payslip_document_id)->not->toBeNull();
    expect(in_array($line->payslip_delivery_status, ['sent', 'failed']))->toBeTrue();

    // Double payment is blocked.
    try {
        $service->markPaid($run->fresh(), $hr, now()->toDateString(), 'cash', 'BATCH-2');
        $this->fail('A paid payroll must not be paid twice.');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('status');
    }

    // The employee can fetch the payslip from their account.
    $user = $employee->user()->first();
    $this->actingAs($user)->get('/my-payslips')->assertOk();
    $this->actingAs($user)->get(\App\Support\Links::payslipPdf($line))->assertOk();
});

it('blocks employees from other employees payslips', function () {
    $hr = makeUser('hr');
    $employee = makeSalariedEmployee();
    $other = makeUser('technical_staff');

    $period = PayPeriod::create([
        'name' => now()->format('Y-m'), 'year' => (int) now()->format('Y'), 'month' => (int) now()->format('n'),
        'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(), 'status' => 'open',
    ]);

    $service = app(\App\Services\PayrollService::class);
    $run = $service->compute($period, $hr);
    $service->approve($run, makeUser('managing_director'));
    $run = $service->markPaid($run->fresh(), $hr, now()->toDateString(), 'cash', 'BATCH-1');

    $line = $run->lines()->first();

    $this->actingAs($other)->get(\App\Support\Links::payslipPdf($line))->assertForbidden();
});

it('restricts payroll controls to hr roles', function () {
    $staff = makeUser('technical_staff');
    $period = PayPeriod::create([
        'name' => now()->format('Y-m'), 'year' => (int) now()->format('Y'), 'month' => (int) now()->format('n'),
        'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString(), 'status' => 'open',
    ]);

    $this->actingAs($staff)->get('/payroll')->assertForbidden();
    $this->actingAs($staff)->post("/payroll/periods/{$period->id}/compute")->assertForbidden();
});
