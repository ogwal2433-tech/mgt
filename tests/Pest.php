<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Create a user with the given role slug, backed by an employee record.
 */
function makeUser(string $roleSlug, array $overrides = []): User
{
    (new \Database\Seeders\RolePermissionSeeder())->run();
    (new \Database\Seeders\CompanyDataSeeder())->run();

    $department = Department::query()->first();
    $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

    $suffix = $roleSlug . '-' . uniqid();

    $employee = Employee::create([
        'employee_code' => 'EMP-' . strtoupper(substr(md5($suffix), 0, 8)),
        'first_name' => ucfirst($roleSlug),
        'last_name' => 'Tester',
        'email' => "{$suffix}@test.local",
        'department_id' => $department?->id,
        'employment_status' => 'active',
        'employment_type' => 'full_time',
        'hire_date' => now()->subYear()->toDateString(),
        'job_title' => 'Tester',
        'is_active' => true,
    ]);

    $user = User::create(array_merge([
        'username' => 'user-' . substr(md5($suffix), 0, 8),
        'name' => $employee->full_name,
        'email' => "{$suffix}@test.local",
        'password' => Hash::make('password'),
        'employee_id' => $employee->id,
        'is_active' => true,
        'email_verified_at' => now(),
    ], $overrides));

    $user->roles()->attach($role->id);

    return $user->fresh(['roles.permissions', 'employee']);
}

function makeRequisition(User $requester, array $overrides = []): \App\Models\Requisition
{
    $category = \App\Models\RequisitionCategory::query()->first();

    $requisition = \App\Models\Requisition::create(array_merge([
        'requisition_number' => null,
        'requester_user_id' => $requester->id,
        'requester_employee_id' => $requester->employee_id,
        'department_id' => $requester->employee?->department_id,
        'category_id' => $category?->id,
        'title' => 'Test requisition',
        'purpose' => 'Testing purposes',
        'currency' => 'USD',
        'requested_amount' => '1000.00',
        'priority' => 'normal',
        'status' => \App\Enums\RequisitionStatus::Draft,
        'is_advance' => true,
        'accountability_required' => true,
    ], $overrides));

    \App\Models\RequisitionItem::create([
        'requisition_id' => $requisition->id,
        'description' => 'Laptop',
        'quantity' => 1,
        'unit_cost' => '1000.00',
        'total_cost' => '1000.00',
    ]);

    return $requisition->fresh();
}
