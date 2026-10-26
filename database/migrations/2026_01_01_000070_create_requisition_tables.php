<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            // Assigned when the requisition is submitted for approval.
            $table->string('requisition_number', 40)->nullable()->unique();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requester_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('requisition_categories')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->text('justification')->nullable();
            $table->string('currency', 10)->default('UGX');
            $table->decimal('requested_amount', 18, 2)->default(0);
            $table->decimal('approved_amount', 18, 2)->nullable();
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('amount_accounted', 18, 2)->default(0);
            $table->decimal('amount_refunded', 18, 2)->default(0);
            $table->string('priority', 20)->default('normal');
            $table->date('required_date')->nullable();
            $table->string('status', 50)->default('DRAFT')->index();
            $table->string('current_stage', 40)->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_contact')->nullable();
            $table->string('budget_reference')->nullable();
            $table->boolean('is_advance')->default(false);
            $table->boolean('accountability_required')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'department_id']);
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 2)->default(1);
            $table->string('unit', 40)->nullable();
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('requisition_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['requisition_id', 'document_id']);
        });

        Schema::create('requisition_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->string('stage', 40)->index();
            $table->string('action', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('decision_amount', 18, 2)->nullable();
            $table->text('reason')->nullable();
            $table->text('comments')->nullable();
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['requisition_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_approvals');
        Schema::dropIfExists('requisition_attachments');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
        Schema::dropIfExists('requisition_categories');
    }
};
