<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vector_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->nullable()->constrained('files')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content'); // Chunked text content
            $table->string('content_hash'); // Hash for deduplication
            $table->integer('chunk_index')->default(0); // Order within file
            $table->json('embedding')->nullable(); // Store embedding vector (or reference to vector DB)
            $table->string('vector_id')->nullable()->unique(); // ID in vector DB (Chroma/FAISS)
            $table->json('metadata')->nullable(); // Source, page number, etc.
            $table->timestamps();

            $table->index(['file_id', 'chunk_index']);
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vector_embeddings');
    }
};

