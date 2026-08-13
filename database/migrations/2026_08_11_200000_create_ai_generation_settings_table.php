<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('ai_generation_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);

            $table->string('provider', 30)->default('gemini');
            $table->string('model', 100)->default('gemini-flash-latest');

            // Encrypted at rest via the model's `encrypted` casts. These are
            // never sent to the browser - the settings form renders a masked
            // placeholder and only writes when a new value is submitted.
            $table->text('gemini_api_key')->nullable();
            $table->text('openai_api_key')->nullable();
            $table->text('anthropic_api_key')->nullable();

            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('max_tokens')->default(8192);
            $table->unsignedInteger('request_timeout')->default(120);

            $table->string('default_language', 20)->default('english');
            $table->string('default_difficulty', 20)->default('medium');
            $table->string('default_question_type', 20)->default('mcq');
            $table->unsignedInteger('default_quantity')->default(10);
            $table->unsignedInteger('max_quantity')->default(100);

            $table->text('system_prompt')->nullable();
            $table->text('default_user_prompt')->nullable();

            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('ai_generation_settings');
    }
};
