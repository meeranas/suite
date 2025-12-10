<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_api_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Crunchbase, Patents, Bing Search, etc.
            $table->string('provider'); // crunchbase, patents, bing, brave, serper
            $table->text('encrypted_api_key'); // AES-256 encrypted
            $table->text('encrypted_api_secret')->nullable(); // If needed
            $table->json('config')->nullable(); // Additional provider-specific config
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_api_configs');
    }
};

