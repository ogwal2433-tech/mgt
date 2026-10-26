<?php

use App\Models\ContractVersion;
use App\Models\EmployeeContract;
use App\Models\ContractReminder;

it('lets hr upload a contract and keeps version history', function () {
    $hr = makeUser('hr');
    $staff = makeUser('technical_staff');

    $pdf = \Illuminate\Http\UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf');

    $this->actingAs($hr)->post('/contracts', [
        'employee_id' => $staff->employee_id,
        'contract_type' => 'permanent',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'status' => 'active',
        'contract_file' => $pdf,
    ])->assertRedirect();

    $contract = EmployeeContract::query()->first();
    expect($contract)->not->toBeNull();
    expect($contract->contract_number)->toStartWith('CON-');
    expect($contract->is_confidential)->toBeTrue();

    // A second version preserves the first one.
    $this->actingAs($hr)->post("/contracts/{$contract->id}/versions", [
        'change_summary' => 'Salary revision',
        'contract_file' => \Illuminate\Http\UploadedFile::fake()->create('contract-v2.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    expect(ContractVersion::where('contract_id', $contract->id)->count())->toBe(2);
    expect($contract->fresh()->current_version)->toBe(2);
});

it('generates expiry reminders according to configured rules', function () {
    $hr = makeUser('hr');
    $staff = makeUser('technical_staff');

    $this->actingAs($hr)->post('/contracts', [
        'employee_id' => $staff->employee_id,
        'contract_type' => 'fixed_term',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(80)->toDateString(),
        'status' => 'active',
    ])->assertRedirect();

    $contract = EmployeeContract::query()->first();
    $reminders = ContractReminder::where('contract_id', $contract->id)->get();

    expect($reminders->count())->toBeGreaterThan(0);
    // 60-day reminder falls inside the 80-day window; 90-day reminder is in the past.
    expect($reminders->pluck('days_before')->all())->toContain(60);
});

it('only allows employees to access their own contracts', function () {
    $hr = makeUser('hr');
    $owner = makeUser('technical_staff');
    $other = makeUser('technical_staff');

    $this->actingAs($hr)->post('/contracts', [
        'employee_id' => $owner->employee_id,
        'contract_type' => 'permanent',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ])->assertRedirect();

    $contract = EmployeeContract::query()->first();

    $this->actingAs($owner)->get("/contracts/{$contract->id}")->assertOk();
    $this->actingAs($other)->get("/contracts/{$contract->id}")->assertForbidden();
});

it('blocks technical staff from uploading contracts', function () {
    $staff = makeUser('technical_staff');

    $this->actingAs($staff)->post('/contracts', [
        'employee_id' => $staff->employee_id,
        'contract_type' => 'permanent',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ])->assertForbidden();
});
