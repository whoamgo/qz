<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multiplayer quiz rooms. A host creates a room for one published quiz; players
 * join with the short room_code and play together once the host starts.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('quiz_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('room_code', 12)->unique();
            $table->enum('status', ['waiting', 'started', 'completed', 'cancelled', 'expired'])
                  ->default('waiting')->index();
            $table->unsignedSmallInteger('max_players')->default(10);
            $table->enum('room_type', ['public', 'private'])->default('private');
            $table->json('settings')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('quiz_rooms');
    }
};
