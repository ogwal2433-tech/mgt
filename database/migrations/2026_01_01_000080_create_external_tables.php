<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_parties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('supplier');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('external_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number', 40)->unique();
            $table->date('date');
            $table->foreignId('external_party_id')->nullable()->constrained('external_parties')->nullOnDelete();
            $table->string('external_party_name');
            $table->string('external_contact')->nullable();
            $table->string('external_email')->nullable();
            $table->string('external_phone', 40)->nullable();
            $table->foreignId('prepared_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('client_reference')->nullable();
            $table->text('purpose');
            $table->string('currency', 10)->default('UGX');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->date('required_delivery_date')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('external_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_requisition_id')->constrained('external_requisitions')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 2)->default(1);
            $table->string('unit', 40)->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_price', 18, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('external_requisition_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_requisition_id')->constrained('external_requisitions')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->unique(['external_requisition_id', 'document_id'], 'ext_req_doc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_requisition_attachments');
        Schema::dropIfExists('external_requisition_items');
        Schema::dropIfExists('external_requisitions');
        Schema::dropIfExists('external_parties');
    }
};
