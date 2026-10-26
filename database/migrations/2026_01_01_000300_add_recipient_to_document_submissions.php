<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->after('employee_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_id');
        });
    }
};
