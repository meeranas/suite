<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vector_embeddings', function (Blueprint $table) {
            $table->foreignId('chat_id')->nullable()->after('file_id')->constrained('chats')->nullOnDelete();
            $table->index(['chat_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vector_embeddings', function (Blueprint $table) {
            $table->dropForeign(['chat_id']);
            $table->dropIndex(['chat_id', 'user_id']);
            $table->dropColumn('chat_id');
        });
    }
};

