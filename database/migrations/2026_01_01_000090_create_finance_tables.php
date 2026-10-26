<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('UGX');
            $table->date('payment_date');
            $table->string('payment_method', 40)->default('bank_transfer');
            $table->string('transaction_reference')->nullable();
            $table->string('payee_name');
            $table->string('payee_details')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('completed')->index();
            $table->foreignId('evidence_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->foreignId('reversal_of_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();

            $table->index(['requisition_id', 'status']);
            // Prevent recording the same external transaction twice for one requisition.
            $table->unique(['requisition_id', 'transaction_reference'], 'payments_req_txn_unique');
        });

        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('UGX');
            $table->decimal('remaining_balance', 18, 2)->default(0);
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payee_name');
            $table->string('purpose')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference')->nullable();
            $table->date('payment_date')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('delivery_status', 30)->default('pending')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_error')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['requisition_id', 'payment_id']);
        });

        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('payment_receipts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20)->default('in_app');
            $table->string('status', 30)->default('sent')->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('retries')->default(0);
            $table->timestamps();
        });

        Schema::create('accountability_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('advance_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->decimal('unspent_balance', 18, 2)->default(0);
            $table->decimal('refund_amount', 18, 2)->default(0);
            $table->text('explanation')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('accountability_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accountability_submission_id')->constrained('accountability_submissions')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('expense_date')->nullable();
            $table->foreignId('receipt_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('accountability_submission_id')->nullable()->constrained('accountability_submissions')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('UGX');
            $table->date('refund_date');
            $table->string('method', 40)->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('spent_amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('UGX');
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamp('allocated_at')->nullable();
            $table->foreignId('allocated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('accountability_items');
        Schema::dropIfExists('accountability_submissions');
        Schema::dropIfExists('payment_notifications');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payments');
    }
};
