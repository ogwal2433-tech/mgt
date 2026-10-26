<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use App\Models\TaxBracket;
use App\Support\Settings;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        // Configurable progressive monthly tax brackets (generic example —
        // adjust to local law before production use).
        $brackets = [
            ['name' => 'Band 1', 'monthly_lower' => 0, 'monthly_upper' => 1000, 'rate_percent' => 0, 'sort_order' => 1],
            ['name' => 'Band 2', 'monthly_lower' => 1000, 'monthly_upper' => 2500, 'rate_percent' => 10, 'sort_order' => 2],
            ['name' => 'Band 3', 'monthly_lower' => 2500, 'monthly_upper' => 5000, 'rate_percent' => 15, 'sort_order' => 3],
            ['name' => 'Band 4', 'monthly_lower' => 5000, 'monthly_upper' => 10000, 'rate_percent' => 20, 'sort_order' => 4],
            ['name' => 'Band 5', 'monthly_lower' => 10000, 'monthly_upper' => null, 'rate_percent' => 25, 'sort_order' => 5],
        ];

        foreach ($brackets as $bracket) {
            TaxBracket::query()->updateOrCreate(['name' => $bracket['name']], $bracket + ['is_active' => true]);
        }

        $components = [
            ['code' => 'BASIC', 'name' => 'Basic salary', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0, 'is_taxable' => true, 'is_statutory' => false, 'sort_order' => 1],
            ['code' => 'HOUSING', 'name' => 'Housing stipend', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0, 'is_taxable' => true, 'is_statutory' => false, 'sort_order' => 2],
            ['code' => 'TRANSPORT', 'name' => 'Transport stipend', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0, 'is_taxable' => true, 'is_statutory' => false, 'sort_order' => 3],
            ['code' => 'ALLOWANCE', 'name' => 'Additional stipend', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0, 'is_taxable' => true, 'is_statutory' => false, 'sort_order' => 4],
            ['code' => 'TAX', 'name' => 'Income tax (PAYE)', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_amount' => 0, 'is_taxable' => false, 'is_statutory' => true, 'sort_order' => 10],
            ['code' => 'PENSION', 'name' => 'Pension / statutory', 'type' => 'deduction', 'calculation_type' => 'percent_basic', 'default_amount' => 0, 'is_taxable' => false, 'is_statutory' => true, 'sort_order' => 11],
            ['code' => 'ADVANCE', 'name' => 'Advance recovery', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_amount' => 0, 'is_taxable' => false, 'is_statutory' => false, 'sort_order' => 12],
        ];

        foreach ($components as $component) {
            SalaryComponent::query()->updateOrCreate(['code' => $component['code']], $component + ['is_active' => true]);
        }

        Settings::set('payroll.tax_enabled', '1', 'payroll', 'boolean', 'Apply income tax brackets in payroll');
        Settings::set('payroll.pension_percent', '0', 'payroll', 'string', 'Statutory pension percentage of basic salary');
        Settings::set('payroll.advance_recovery', 'percent', 'payroll', 'string', 'Advance recovery mode: percent or full');
        Settings::set('payroll.advance_recovery_percent', '50', 'payroll', 'string', 'Percentage of outstanding advance recovered per run');
    }
}
