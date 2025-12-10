<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('external_user_id')->nullable()->unique()->after('id'); // ID from main platform
            $table->string('jwt_issuer')->nullable()->after('external_user_id'); // JWT issuer for verification
            $table->json('subscription_tier')->nullable()->after('jwt_issuer'); // ['basic', 'pro', 'enterprise']
            $table->timestamp('last_jwt_verified_at')->nullable()->after('subscription_tier');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['external_user_id', 'jwt_issuer', 'subscription_tier', 'last_jwt_verified_at']);
        });
    }
};

