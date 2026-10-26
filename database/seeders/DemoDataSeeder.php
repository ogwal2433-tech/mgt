<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development-only demo accounts. Never run with real credentials in production.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder skipped: production environment.');

            return;
        }

        $password = env('DEMO_PASSWORD', 'Password123!');

        $people = [
            [
                'code' => 'EMP-0001', 'first' => 'Alice', 'last' => 'Morgan', 'email' => 'md@biiteek.test',
                'username' => 'md', 'role' => 'managing_director', 'department' => 'MD', 'title' => 'Managing Director',
            ],
            [
                'code' => 'EMP-0002', 'first' => 'Brian', 'last' => 'Otieno', 'email' => 'hr@biiteek.test',
                'username' => 'hr', 'role' => 'hr', 'department' => 'HR', 'title' => 'Human Resources Manager',
            ],
            [
                'code' => 'EMP-0003', 'first' => 'Cynthia', 'last' => 'Kamau', 'email' => 'finance@biiteek.test',
                'username' => 'finance', 'role' => 'hr_finance', 'department' => 'HR', 'title' => 'Finance & Accounts Officer',
            ],
            [
                'code' => 'EMP-0004', 'first' => 'David', 'last' => 'Njoroge', 'email' => 'lead@biiteek.test',
                'username' => 'lead', 'role' => 'technical_lead', 'department' => 'TECH', 'title' => 'Technical Lead',
            ],
            [
                'code' => 'EMP-0005', 'first' => 'Esther', 'last' => 'Wanjiku', 'email' => 'dev@biiteek.test',
                'username' => 'dev', 'role' => 'technical_staff', 'department' => 'TECH', 'title' => 'Software Engineer',
            ],
            [
                'code' => 'EMP-0006', 'first' => 'Frank', 'last' => 'Omondi', 'email' => 'admin@biiteek.test',
                'username' => 'sysadmin', 'role' => 'system_admin', 'department' => 'TECH', 'title' => 'Systems Administrator',
            ],
        ];

        foreach ($people as $person) {
            $department = Department::query()->where('code', $person['department'])->first();

            $employee = Employee::query()->updateOrCreate(
                ['employee_code' => $person['code']],
                [
                    'first_name' => $person['first'],
                    'last_name' => $person['last'],
                    'email' => $person['email'],
                    'phone' => '+254 700 000 000',
                    'department_id' => $department?->id,
                    'employment_status' => 'active',
                    'employment_type' => 'full_time',
                    'hire_date' => now()->subYear()->toDateString(),
                    'job_title' => $person['title'],
                    'is_active' => true,
                ]
            );

            $user = User::query()->updateOrCreate(
                ['username' => $person['username']],
                [
                    'name' => $employee->full_name,
                    'email' => $person['email'],
                    'password' => Hash::make($password),
                    'employee_id' => $employee->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $role = Role::query()->where('slug', $person['role'])->first();
            if ($role && ! $user->roles->contains($role->id)) {
                $user->roles()->attach($role->id);
            }
        }

        $this->command?->info("Demo users created. Password for all demo accounts: {$password}");
    }
}
