<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('suite_id')->nullable()->constrained('suites')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained('chats')->nullOnDelete();
            $table->enum('action', ['chat', 'rag_query', 'web_search', 'external_api', 'report_generation'])->default('chat');
            $table->string('model_provider')->nullable(); // openai, gemini, etc.
            $table->string('model_name')->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0); // Token cost
            $table->json('metadata')->nullable(); // Additional usage data
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['suite_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_logs');
    }
};

