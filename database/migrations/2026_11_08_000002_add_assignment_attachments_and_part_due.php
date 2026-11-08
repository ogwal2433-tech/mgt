<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        Schema::table('assignment_parts', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_parts', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
        Schema::dropIfExists('assignment_attachments');
    }
};
