<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\RequisitionCategory;
use App\Support\Settings;
use Illuminate\Database\Seeder;

class CompanyDataSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'MD', 'name' => 'Managing Director', 'description' => 'Company oversight, final approvals and executive decisions'],
            ['code' => 'HR', 'name' => 'Human Resources', 'description' => 'Employee management, contracts, Finance & Accounts'],
            ['code' => 'TECH', 'name' => 'Technical Department', 'description' => 'Projects, development, IT operations and support'],
        ];

        foreach ($departments as $dept) {
            Department::query()->updateOrCreate(['code' => $dept['code']], $dept);
        }

        $positions = [
            ['code' => 'MD', 'title' => 'Managing Director'],
            ['code' => 'HR', 'title' => 'Human Resources Manager'],
            ['code' => 'HR', 'title' => 'Finance & Accounts Officer'],
            ['code' => 'HR', 'title' => 'HR Officer'],
            ['code' => 'TECH', 'title' => 'Technical Lead'],
            ['code' => 'TECH', 'title' => 'Software Engineer'],
            ['code' => 'TECH', 'title' => 'Systems Administrator'],
            ['code' => 'TECH', 'title' => 'Support Technician'],
        ];

        foreach ($positions as $position) {
            $department = Department::query()->where('code', $position['code'])->first();
            Position::query()->updateOrCreate(
                ['title' => $position['title'], 'department_id' => $department?->id],
                ['description' => null, 'is_active' => true]
            );
        }

        $categories = [
            ['code' => 'COMP', 'name' => 'Computers and accessories'],
            ['code' => 'NET', 'name' => 'Networking equipment'],
            ['code' => 'SRV', 'name' => 'Servers and infrastructure'],
            ['code' => 'SW', 'name' => 'Software licenses and cloud services'],
            ['code' => 'DEV', 'name' => 'Software development activities'],
            ['code' => 'SUPPORT', 'name' => 'Technical support and maintenance'],
            ['code' => 'PROJ', 'name' => 'Project materials'],
            ['code' => 'OFFICE', 'name' => 'Office supplies'],
            ['code' => 'REPAIR', 'name' => 'Repairs and maintenance'],
            ['code' => 'TRAIN', 'name' => 'Training and professional development'],
            ['code' => 'TRANSPORT', 'name' => 'Transport and fieldwork expenses'],
            ['code' => 'PETTY', 'name' => 'Operational funds and petty cash'],
            ['code' => 'OTHER', 'name' => 'Other authorised company expenses'],
        ];

        foreach ($categories as $category) {
            RequisitionCategory::query()->updateOrCreate(['code' => $category['code']], $category + ['is_active' => true]);
        }

        Settings::set('company.name', 'Biiteek', 'company', 'string', 'Company name shown on documents');
        Settings::set('company.email', 'info@biiteek.example', 'company', 'string');
        Settings::set('company.phone', '+000 000 0000', 'company', 'string');
        Settings::set('company.address', 'Head Office', 'company', 'string');
        Settings::set('company.tagline', 'Gain Digital Advantage', 'company', 'string', 'Company tagline shown on documents');
        Settings::set('finance.currency', 'UGX', 'finance', 'string', 'Default transaction currency');
        Settings::set('app.timezone', 'Africa/Nairobi', 'app', 'string', 'Company timezone');
        Settings::set('uploads.max_bytes', 20480, 'uploads', 'integer', 'Maximum upload size in kilobytes');
        Settings::set('contracts.reminder_days', '90,60,30', 'contracts', 'string', 'Days before contract expiry to remind');
        Settings::set('workflow.allow_return_for_correction', '1', 'workflow', 'boolean', 'Allow approvers to return requisitions for correction');
        Settings::set('finance.payment_methods', '["cash","bank_transfer","mobile_money","cheque","other"]', 'finance', 'json');
    }
}
