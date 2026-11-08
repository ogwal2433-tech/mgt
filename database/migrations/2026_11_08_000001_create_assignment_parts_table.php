<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->string('title', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('assignment_deliverables', function (Blueprint $table) {
            $table->foreignId('assignment_part_id')->nullable()->after('assignment_recipient_id')
                ->constrained('assignment_parts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_deliverables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignment_part_id');
        });
        Schema::dropIfExists('assignment_parts');
    }
};
