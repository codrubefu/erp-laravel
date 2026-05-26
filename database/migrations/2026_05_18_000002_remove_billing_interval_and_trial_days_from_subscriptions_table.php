<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['billing_interval']);
            $table->dropColumn(['billing_interval', 'trial_days']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('billing_interval', ['monthly', 'yearly'])->default('monthly')->after('currency');
            $table->integer('trial_days')->default(0)->after('duration_days');
            $table->index('billing_interval');
        });
    }
};
