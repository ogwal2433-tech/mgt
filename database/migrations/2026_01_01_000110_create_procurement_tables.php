<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->string('category', 60)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->string('quote_number', 60)->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('UGX');
            $table->date('valid_until')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 30)->default('received');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('UGX');
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 2)->default(1);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->decimal('total_cost', 18, 2)->default(0);
            $table->decimal('received_quantity', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number', 40)->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->date('received_date');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('condition_notes')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('status', 30)->default('received');
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 40)->unique();
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('unit');
            $table->decimal('quantity_on_hand', 18, 2)->default(0);
            $table->decimal('min_stock_level', 18, 2)->default(0);
            $table->decimal('reorder_level', 18, 2)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->string('location')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('movement_type', 30)->index();
            $table->decimal('quantity', 18, 2);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('moved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('moved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 40)->unique();
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 18, 2)->nullable();
            $table->date('warranty_end')->nullable();
            $table->string('condition', 30)->default('good');
            $table->string('location')->nullable();
            $table->string('status', 30)->default('in_stock')->index();
            $table->foreignId('assigned_to_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->text('maintenance_history')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->text('disposal_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('condition_at_issue', 30)->nullable();
            $table->string('condition_at_return', 30)->nullable();
            $table->string('return_status', 30)->default('outstanding');
            $table->foreignId('issued_by_user_id')->nullable();
            $table->foreignId('received_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_quotations');
        Schema::dropIfExists('suppliers');
    }
};
