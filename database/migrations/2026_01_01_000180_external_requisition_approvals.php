<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_requisition_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_requisition_id')->constrained('external_requisitions')->cascadeOnDelete();
            $table->string('stage', 40)->index();
            $table->string('action', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comments')->nullable();
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['external_requisition_id', 'stage'], 'ext_req_appr_stage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_requisition_approvals');
    }
};
