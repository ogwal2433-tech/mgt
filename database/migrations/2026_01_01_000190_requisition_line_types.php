<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            // goods  = quantity x unit cost
            // service = hours x rate (or flat amount)
            // activity = flat amount (e.g. software development milestone)
            $table->string('line_type', 20)->default('goods')->after('description');
            $table->decimal('hours', 8, 2)->nullable()->after('quantity');
            $table->string('period', 120)->nullable()->after('unit'); // e.g. "Oct 2026", "Milestone 2"
        });
    }

    public function down(): void
    {
        Schema::table('requisition_items', function (Blueprint $table) {
            $table->dropColumn(['line_type', 'hours', 'period']);
        });
    }
};
