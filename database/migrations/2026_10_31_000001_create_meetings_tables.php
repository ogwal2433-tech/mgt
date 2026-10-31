<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->string('title');
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('access_mode', 20)->default('guest'); // guest | staff_only
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('link_expires_at');
            $table->string('status', 20)->default('scheduled'); // scheduled | live | ended | expired
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['host_user_id', 'status']);
            $table->index('scheduled_at');
        });

        Schema::create('meeting_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email', 190)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->unique(['meeting_id', 'email']);
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('identity', 120);
            $table->string('display_name', 150);
            $table->string('role', 20)->default('participant'); // host | moderator | participant
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['meeting_id', 'identity']);
        });

        Schema::create('meeting_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name', 150);
            $table->text('body');
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_chat_messages');
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meeting_invitations');
        Schema::dropIfExists('meetings');
    }
};
