<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('external_api_configs', function (Blueprint $table) {
            $table->string('base_url')->nullable()->after('provider');
            $table->string('api_type')->default('rest')->after('base_url'); // rest, graphql, etc.
        });
    }

    public function down(): void
    {
        Schema::table('external_api_configs', function (Blueprint $table) {
            $table->dropColumn(['base_url', 'api_type']);
        });
    }
};




