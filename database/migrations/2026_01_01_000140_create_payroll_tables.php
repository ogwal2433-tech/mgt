<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20); // e.g. 2026-10
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('type', 20)->index(); // earning | deduction
            $table->string('calculation_type', 30)->default('fixed'); // fixed | percent_basic | percent_gross
            $table->decimal('default_amount', 18, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('employee_compensation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'salary_component_id', 'effective_from'], 'emp_comp_unique');
        });

        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->decimal('monthly_lower', 18, 2)->default(0);
            $table->decimal('monthly_upper', 18, 2)->nullable(); // null = no upper limit
            $table->decimal('rate_percent', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number', 40)->unique();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('total_basic', 18, 2)->default(0);
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->decimal('total_paid', 18, 2)->default(0);
            $table->foreignId('computed_by')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['pay_period_id'], 'payroll_runs_period_unique');
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('employee_code', 30)->nullable();
            $table->string('employee_name')->nullable();
            $table->decimal('basic_salary', 18, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('advance_amount', 18, 2)->default(0);
            $table->decimal('statutory_amount', 18, 2)->default(0);
            $table->decimal('other_deduction_amount', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->string('status', 30)->default('unpaid')->index();
            $table->string('payslip_number', 40)->nullable()->unique();
            $table->foreignId('payslip_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('payslip_generated_at')->nullable();
            $table->string('payslip_delivery_status', 30)->default('pending')->index();
            $table->timestamp('payslip_emailed_at')->nullable();
            $table->text('payslip_email_error')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payroll_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_line_id')->constrained('payroll_lines')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->string('component_code', 30);
            $table->string('component_name', 120);
            $table->string('type', 20); // earning | deduction
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->index(['payroll_line_id', 'type']);
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('payroll_line_id')->constrained('payroll_lines')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('UGX');
            $table->date('payment_date');
            $table->string('payment_method', 40)->default('bank_transfer');
            $table->string('transaction_reference')->nullable();
            $table->foreignId('recorded_by')->nullable();
            $table->string('status', 30)->default('completed')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            // One salary payment per payroll line prevents duplicate salary payments.
            $table->unique(['payroll_line_id'], 'payroll_payments_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_line_items');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('tax_brackets');
        Schema::dropIfExists('employee_compensation');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('pay_periods');
    }
};
