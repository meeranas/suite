<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_id')->constrained('suites')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug');
            $table->enum('model_provider', ['openai', 'gemini', 'mistral', 'claude', 'anthropic'])->default('openai');
            $table->string('model_name')->default('gpt-4'); // gpt-4, gemini-pro, mistral-large, claude-3-opus
            $table->text('system_prompt')->nullable();
            $table->json('prompt_template')->nullable(); // Template with variables
            $table->json('model_config')->nullable(); // temperature, max_tokens, etc.
            $table->json('external_api_configs')->nullable(); // References to external_api_configs
            $table->boolean('enable_rag')->default(false);
            $table->boolean('enable_web_search')->default(false);
            $table->integer('order')->default(0); // For workflow ordering
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['suite_id', 'order']);
            $table->index('is_active');
            $table->unique(['suite_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};

