<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suite_id')->constrained('suites')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('agent_sequence'); // [agent_id_1, agent_id_2, agent_id_3]
            $table->json('workflow_config')->nullable(); // Conditions, branching logic
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['suite_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_workflows');
    }
};

