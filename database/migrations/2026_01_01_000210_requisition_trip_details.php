<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            // Trip / field details for transport & fieldwork requisitions.
            $table->string('destination')->nullable()->after('supplier_contact');
            $table->date('trip_start')->nullable()->after('destination');
            $table->date('trip_end')->nullable()->after('trip_start');
            $table->string('transport_mode', 60)->nullable()->after('trip_end');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn(['destination', 'trip_start', 'trip_end', 'transport_mode']);
        });
    }
};
