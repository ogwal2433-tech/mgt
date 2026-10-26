<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('title');
            $table->string('category', 80)->default('general');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->timestamps();
        });

        Schema::create('document_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_submission_id')->constrained('document_submissions')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['document_submission_id', 'document_id'], 'doc_sub_file_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_submission_files');
        Schema::dropIfExists('document_submissions');
    }
};
