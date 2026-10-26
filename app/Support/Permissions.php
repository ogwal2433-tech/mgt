<?php

namespace App\Support;

/**
 * Central registry of permission slugs. Grouped for the admin UI.
 */
final class Permissions
{
    // Dashboard
    public const DASHBOARD_MD = 'dashboard.md';
    public const DASHBOARD_HR = 'dashboard.hr';
    public const DASHBOARD_TECHNICAL = 'dashboard.technical';

    // Employees / HR
    public const EMPLOYEES_VIEW = 'employees.view';
    public const EMPLOYEES_MANAGE = 'employees.manage';
    public const HR_OPERATIONS = 'hr.operations';

    // Contracts
    public const CONTRACTS_VIEW = 'contracts.view';
    public const CONTRACTS_MANAGE = 'contracts.manage';
    public const CONTRACTS_VIEW_CONFIDENTIAL = 'contracts.view_confidential';

    // Requisitions
    public const REQUISITIONS_CREATE = 'requisitions.create';
    public const REQUISITIONS_VIEW_DEPARTMENT = 'requisitions.view_department';
    public const REQUISITIONS_VIEW_ALL = 'requisitions.view_all';
    public const REQUISITIONS_REVIEW_TECHNICAL = 'requisitions.review_technical';
    public const REQUISITIONS_REVIEW_HR = 'requisitions.review_hr';
    public const REQUISITIONS_APPROVE_MD = 'requisitions.approve_md';
    public const REQUISITIONS_CANCEL = 'requisitions.cancel';

    // External requisitions
    public const EXTERNAL_REQUISITIONS_MANAGE = 'external_requisitions.manage';
    public const EXTERNAL_REQUISITIONS_VIEW = 'external_requisitions.view';

    // Finance
    public const FINANCE_VIEW = 'finance.view';
    public const FINANCE_DISBURSE = 'finance.disburse';
    public const FINANCE_ACCOUNTABILITY_REVIEW = 'finance.accountability_review';
    public const FINANCE_REFUNDS = 'finance.refunds';
    public const FINANCE_BUDGETS = 'finance.budgets';

    // Technical department
    public const PROJECTS_MANAGE = 'projects.manage';
    public const PROJECTS_VIEW = 'projects.view';
    public const TASKS_UPDATE = 'tasks.update';
    public const SUPPORT_TICKETS = 'support_tickets.manage';

    // Procurement / inventory / assets
    public const PROCUREMENT_MANAGE = 'procurement.manage';
    public const INVENTORY_MANAGE = 'inventory.manage';
    public const ASSETS_MANAGE = 'assets.manage';

    // Reports
    public const REPORTS_EXECUTIVE = 'reports.executive';
    public const REPORTS_FINANCIAL = 'reports.financial';
    public const REPORTS_HR = 'reports.hr';
    public const REPORTS_TECHNICAL = 'reports.technical';

    // Payroll
    public const PAYROLL_VIEW = 'payroll.view';
    public const PAYROLL_RUN = 'payroll.run';
    public const PAYROLL_APPROVE = 'payroll.approve';
    public const PAYROLL_PAY = 'payroll.pay';

    // Client invoicing
    public const INVOICES_VIEW = 'invoices.view';
    public const INVOICES_MANAGE = 'invoices.manage';
    public const INVOICES_PAYMENTS = 'invoices.payments';

    // Platform
    public const AUDIT_VIEW = 'audit.view';
    public const SYSTEM_SETTINGS = 'system.settings';
    public const SYSTEM_USERS = 'system.users';
    public const SYSTEM_ROLES = 'system.roles';
    public const DOCUMENTS_DOWNLOAD_CONFIDENTIAL = 'documents.download_confidential';

    /** @return array<string, array<string, string>> slug => [label, description] */
    public static function catalogue(): array
    {
        return [
            self::DASHBOARD_MD => ['MD dashboard', 'View the company-wide executive dashboard'],
            self::DASHBOARD_HR => ['HR dashboard', 'View the HR and finance dashboard'],
            self::DASHBOARD_TECHNICAL => ['Technical dashboard', 'View the technical department dashboard'],

            self::EMPLOYEES_VIEW => ['View employees', 'Browse employee records'],
            self::EMPLOYEES_MANAGE => ['Manage employees', 'Create and update employee records'],
            self::HR_OPERATIONS => ['HR operations', 'Attendance, leave and performance management'],

            self::CONTRACTS_VIEW => ['View contracts', 'Access permitted employment contracts'],
            self::CONTRACTS_MANAGE => ['Manage contracts', 'Upload contracts, versions and reminders'],
            self::CONTRACTS_VIEW_CONFIDENTIAL => ['View confidential contracts', 'Open confidential contract documents'],

            self::REQUISITIONS_CREATE => ['Create requisitions', 'Submit internal requisitions'],
            self::REQUISITIONS_VIEW_DEPARTMENT => ['View department requisitions', 'View requisitions of own department'],
            self::REQUISITIONS_VIEW_ALL => ['View all requisitions', 'View every requisition'],
            self::REQUISITIONS_REVIEW_TECHNICAL => ['Technical Lead review', 'First-stage approval of technical requisitions'],
            self::REQUISITIONS_REVIEW_HR => ['HR review', 'Second-stage approval of requisitions'],
            self::REQUISITIONS_APPROVE_MD => ['MD approval', 'Final approval of requisitions'],
            self::REQUISITIONS_CANCEL => ['Cancel requisitions', 'Cancel or close requisitions'],

            self::EXTERNAL_REQUISITIONS_MANAGE => ['Manage external requisitions', 'Prepare and approve external requisition forms'],
            self::EXTERNAL_REQUISITIONS_VIEW => ['View external requisitions', 'View external requisition forms'],

            self::FINANCE_VIEW => ['View finance', 'Access payment and financial records'],
            self::FINANCE_DISBURSE => ['Disburse funds', 'Record payments against approved requisitions'],
            self::FINANCE_ACCOUNTABILITY_REVIEW => ['Review accountability', 'Review accountability submissions'],
            self::FINANCE_REFUNDS => ['Record refunds', 'Record refund transactions'],
            self::FINANCE_BUDGETS => ['Manage budgets', 'Maintain budgets and allocations'],

            self::PROJECTS_MANAGE => ['Manage projects', 'Create and manage technical projects'],
            self::PROJECTS_VIEW => ['View projects', 'View technical projects'],
            self::TASKS_UPDATE => ['Update tasks', 'Create and update task progress'],
            self::SUPPORT_TICKETS => ['Support tickets', 'Manage IT support tickets'],

            self::PROCUREMENT_MANAGE => ['Procurement', 'Suppliers, quotations and purchase orders'],
            self::INVENTORY_MANAGE => ['Inventory', 'Stock catalogue and movements'],
            self::ASSETS_MANAGE => ['Assets', 'Asset register and assignments'],

            self::REPORTS_EXECUTIVE => ['Executive reports', 'Company-wide reports'],
            self::REPORTS_FINANCIAL => ['Financial reports', 'Payments, receipts and expenditure reports'],
            self::REPORTS_HR => ['HR reports', 'Employee, contract and attendance reports'],
            self::REPORTS_TECHNICAL => ['Technical reports', 'Project, task and support reports'],

            self::PAYROLL_VIEW => ['View payroll', 'View payroll runs and payslips'],
            self::PAYROLL_RUN => ['Run payroll', 'Compute payroll for a pay period'],
            self::PAYROLL_APPROVE => ['Approve payroll', 'Approve computed payroll runs'],
            self::PAYROLL_PAY => ['Pay salaries', 'Record salary payments and issue payslips'],

            self::INVOICES_VIEW => ['View invoices', 'View client invoices and receipts'],
            self::INVOICES_MANAGE => ['Manage invoices', 'Create and manage client invoices'],
            self::INVOICES_PAYMENTS => ['Record invoice payments', 'Record client payments against invoices'],

            self::AUDIT_VIEW => ['Audit logs', 'Read the audit trail'],
            self::SYSTEM_SETTINGS => ['System settings', 'Application configuration'],
            self::SYSTEM_USERS => ['Manage users', 'Create, activate and suspend accounts'],
            self::SYSTEM_ROLES => ['Manage roles', 'Configure roles and permissions'],
            self::DOCUMENTS_DOWNLOAD_CONFIDENTIAL => ['Download confidential documents', 'Download confidential files'],
        ];
    }

    /** @return array<string, string> slug => label */
    public static function labels(): array
    {
        return array_map(fn ($entry) => $entry[0], self::catalogue());
    }
}
