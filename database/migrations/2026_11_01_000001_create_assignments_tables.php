<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();
            $table->string('title');
            $table->longText('body');
            $table->boolean('require_deliverables')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->string('status', 20)->default('sent'); // sent | closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('due_at');
        });

        Schema::create('assignment_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->string('kind', 10)->default('to'); // to | cc
            $table->string('email', 190);
            $table->string('name', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('signer_name', 150)->nullable();
            $table->string('signature_path', 255)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['assignment_id', 'kind']);
        });

        Schema::create('assignment_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_recipient_id')->constrained('assignment_recipients')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_deliverables');
        Schema::dropIfExists('assignment_recipients');
        Schema::dropIfExists('assignments');
    }
};
