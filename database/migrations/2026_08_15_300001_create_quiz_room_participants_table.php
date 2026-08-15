<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room membership. One row per user per room. The unique (room_id, user_id)
 * constraint is the hard guarantee that a user cannot become a duplicate
 * participant even under concurrent join requests.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('quiz_room_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('quiz_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['host', 'player'])->default('player');
            $table->enum('status', ['joined', 'playing', 'finished', 'left'])->default('joined');
            $table->foreignId('quiz_attempt_id')->nullable()->constrained('quiz_attempts')->nullOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('wrong_answers')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'user_id']);
            $table->index(['room_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('quiz_room_participants');
    }
};
