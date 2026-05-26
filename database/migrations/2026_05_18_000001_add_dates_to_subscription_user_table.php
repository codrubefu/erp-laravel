<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_user', function (Blueprint $table) {
            $table->index(['subscription_id', 'user_id']);
            $table->dropUnique(['subscription_id', 'user_id']);
            $table->date('start_date')->nullable()->after('user_id');
            $table->date('expires_at')->nullable()->after('start_date');
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'expires_at']);
            $table->dropIndex(['subscription_id', 'user_id']);
            $table->dropColumn(['start_date', 'expires_at']);
            $table->unique(['subscription_id', 'user_id']);
        });
    }
};
