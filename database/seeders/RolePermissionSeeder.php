<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions as P;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (P::catalogue() as $slug => [$name, $description]) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'group' => $this->groupFor($slug)]
            );
        }

        $roles = [
            'managing_director' => [
                'name' => 'Managing Director',
                'permissions' => [
                    P::DASHBOARD_MD, P::EMPLOYEES_VIEW, P::CONTRACTS_VIEW, P::CONTRACTS_VIEW_CONFIDENTIAL,
                    P::REQUISITIONS_CREATE, P::REQUISITIONS_VIEW_ALL, P::REQUISITIONS_APPROVE_MD,
                    P::EXTERNAL_REQUISITIONS_VIEW, P::EXTERNAL_REQUISITIONS_MANAGE, P::FINANCE_VIEW, P::PROJECTS_VIEW,
                    P::PAYROLL_VIEW, P::PAYROLL_APPROVE, P::INVOICES_VIEW,
                    P::REPORTS_EXECUTIVE, P::REPORTS_FINANCIAL, P::REPORTS_HR, P::REPORTS_TECHNICAL,
                    P::DOCS_SUBMIT,
                    P::AUDIT_VIEW, P::DOCUMENTS_DOWNLOAD_CONFIDENTIAL,
                ],
            ],
            'hr' => [
                'name' => 'Human Resources',
                'permissions' => [
                    P::DASHBOARD_HR, P::EMPLOYEES_VIEW, P::EMPLOYEES_MANAGE, P::HR_OPERATIONS,
                    P::CONTRACTS_VIEW, P::CONTRACTS_MANAGE, P::CONTRACTS_VIEW_CONFIDENTIAL,
                    P::REQUISITIONS_CREATE, P::REQUISITIONS_VIEW_ALL, P::REQUISITIONS_REVIEW_HR,
                    P::REQUISITIONS_CANCEL, P::EXTERNAL_REQUISITIONS_VIEW, P::EXTERNAL_REQUISITIONS_MANAGE,
                    P::FINANCE_VIEW, P::FINANCE_DISBURSE, P::FINANCE_ACCOUNTABILITY_REVIEW,
                    P::FINANCE_REFUNDS, P::FINANCE_BUDGETS,
                    P::PAYROLL_VIEW, P::PAYROLL_RUN, P::PAYROLL_APPROVE, P::PAYROLL_PAY,
                    P::INVOICES_VIEW, P::INVOICES_MANAGE, P::INVOICES_PAYMENTS,
                    P::PROJECTS_VIEW, P::PROCUREMENT_MANAGE, P::INVENTORY_MANAGE, P::ASSETS_MANAGE,
                    P::REPORTS_EXECUTIVE, P::REPORTS_FINANCIAL, P::REPORTS_HR,
                    P::DOCS_SUBMIT, P::DOCS_REVIEW,
                    P::DOCUMENTS_DOWNLOAD_CONFIDENTIAL,
                ],
            ],
            'hr_finance' => [
                'name' => 'HR Finance & Accounts',
                'permissions' => [
                    P::DASHBOARD_HR, P::REQUISITIONS_VIEW_ALL, P::FINANCE_VIEW, P::FINANCE_DISBURSE,
                    P::FINANCE_ACCOUNTABILITY_REVIEW, P::FINANCE_REFUNDS, P::FINANCE_BUDGETS,
                    P::PAYROLL_VIEW, P::PAYROLL_RUN, P::PAYROLL_PAY,
                    P::INVOICES_VIEW, P::INVOICES_PAYMENTS,
        P::DOCS_SUBMIT, P::DOCS_REVIEW,
        P::REPORTS_FINANCIAL, P::DOCUMENTS_DOWNLOAD_CONFIDENTIAL,
                ],
            ],
            'technical_lead' => [
                'name' => 'Technical Lead',
                'permissions' => [
                    P::DASHBOARD_TECHNICAL, P::EMPLOYEES_VIEW, P::CONTRACTS_VIEW,
                    P::REQUISITIONS_CREATE, P::REQUISITIONS_VIEW_DEPARTMENT, P::REQUISITIONS_REVIEW_TECHNICAL,
                    P::EXTERNAL_REQUISITIONS_MANAGE, P::EXTERNAL_REQUISITIONS_VIEW,
                    P::PROJECTS_MANAGE, P::PROJECTS_VIEW, P::TASKS_UPDATE, P::SUPPORT_TICKETS,
                    P::REPORTS_TECHNICAL,
                    P::DOCS_SUBMIT,
                ],
            ],
            'technical_staff' => [
                'name' => 'Technical Staff',
                'permissions' => [
                    P::PROJECTS_VIEW, P::TASKS_UPDATE, P::SUPPORT_TICKETS,
                    P::REQUISITIONS_CREATE,
                    P::DOCS_SUBMIT,
                ],
            ],
            'system_admin' => [
                'name' => 'System Administrator',
                'description' => 'System configuration only. No financial or HR approval authority is granted by default.',
                'permissions' => [
        P::AUDIT_VIEW, P::SYSTEM_SETTINGS, P::SYSTEM_USERS, P::SYSTEM_ROLES,
        P::DOCS_SUBMIT, P::DOCS_REVIEW,
                ],
            ],
        ];

        foreach ($roles as $slug => $config) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $config['name'], 'description' => $config['description'] ?? null, 'is_system' => true]
            );

            $ids = Permission::query()->whereIn('slug', $config['permissions'])->pluck('id')->all();
            $role->permissions()->sync($ids);
        }
    }

    private function groupFor(string $slug): string
    {
        return explode('.', $slug)[0];
    }
}
