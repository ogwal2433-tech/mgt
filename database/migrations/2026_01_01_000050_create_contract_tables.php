<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 40)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('contract_type', 40)->default('permanent');
            $table->string('job_title', 150)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->decimal('salary', 18, 2)->nullable();
            $table->string('currency', 10)->default('UGX');
            $table->string('working_hours', 60)->nullable();
            $table->string('signature_status', 40)->default('pending');
            $table->unsignedInteger('current_version')->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_confidential')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('employee_contracts')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('change_summary')->nullable();
            $table->string('file_label')->nullable();
            $table->foreignId('uploaded_by')->nullable();
            $table->timestamps();
            $table->unique(['contract_id', 'version_number']);
        });

        Schema::create('contract_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('employee_contracts')->cascadeOnDelete();
            $table->date('remind_on');
            $table->unsignedInteger('days_before')->default(30);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_reminders');
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('employee_contracts');
    }
};
