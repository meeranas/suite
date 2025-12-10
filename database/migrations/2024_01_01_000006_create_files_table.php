<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained('chats')->nullOnDelete();
            $table->string('original_name');
            $table->string('stored_name'); // Encrypted/hashed filename
            $table->string('path'); // Storage path
            $table->string('mime_type');
            $table->bigInteger('size'); // Bytes
            $table->enum('type', ['pdf', 'docx', 'xlsx', 'txt', 'csv', 'other'])->default('other');
            $table->boolean('is_processed')->default(false); // RAG processing status
            $table->boolean('is_embedded')->default(false); // Vector embedding status
            $table->json('metadata')->nullable(); // Extracted text, page count, etc.
            $table->string('signed_url_token')->nullable()->unique(); // For secure access
            $table->timestamp('signed_url_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_processed']);
            $table->index('is_embedded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

