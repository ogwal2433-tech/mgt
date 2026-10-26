<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_requisitions', function (Blueprint $table) {
            // Form fields (Requisition / Accountability form)
            $table->string('payee_name')->nullable()->after('external_phone');
            $table->string('requested_by_name')->nullable()->after('payee_name');
            $table->string('department_text', 120)->nullable()->after('requested_by_name');
            $table->string('mode_of_payment', 40)->nullable()->after('department_text');
            $table->date('date_of_activity')->nullable()->after('purpose');
            $table->decimal('activity_budget', 18, 2)->nullable()->after('date_of_activity');

            // External fill & sign flow (tokenized share link)
            $table->uuid('share_token')->nullable()->unique()->after('status');
            $table->timestamp('token_expires_at')->nullable()->after('share_token');
            $table->timestamp('sent_to_external_at')->nullable()->after('token_expires_at');
            $table->string('signed_by_name')->nullable()->after('sent_to_external_at');
            $table->string('signed_by_email')->nullable()->after('signed_by_name');
            $table->foreignId('signature_document_id')->nullable()->after('signed_by_email')
                ->constrained('documents')->nullOnDelete();
            $table->timestamp('signed_at')->nullable()->after('signature_document_id');

            // Accountability totals
            $table->decimal('actual_total', 18, 2)->default(0)->after('total_amount');
            $table->decimal('unspent_funds', 18, 2)->default(0)->after('actual_total');
        });

        Schema::table('external_requisition_items', function (Blueprint $table) {
            $table->decimal('actual_cost', 18, 2)->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('external_requisition_items', function (Blueprint $table) {
            $table->dropColumn('actual_cost');
        });

        Schema::table('external_requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signature_document_id');
            $table->dropColumn([
                'payee_name', 'requested_by_name', 'department_text', 'mode_of_payment',
                'date_of_activity', 'activity_budget', 'share_token', 'token_expires_at',
                'sent_to_external_at', 'signed_by_name', 'signed_by_email', 'signed_at',
                'actual_total', 'unspent_funds',
            ]);
        });
    }
};
