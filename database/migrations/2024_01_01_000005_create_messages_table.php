<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->enum('role', ['user', 'assistant', 'system'])->default('user');
            $table->text('content');
            $table->json('metadata')->nullable(); // tokens_used, model_used, etc.
            $table->json('rag_context')->nullable(); // Retrieved context from RAG
            $table->json('external_data')->nullable(); // Web search results, API data
            $table->integer('order')->default(0); // Message order in chat
            $table->timestamps();

            $table->index(['chat_id', 'order']);
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

