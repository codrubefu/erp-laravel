<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_organization_id_email_unique');
            $table->dropUnique('users_organization_phone_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->index(['organization_id', 'email'], 'users_organization_email_index');
            $table->index(['organization_id', 'phone'], 'users_organization_phone_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_organization_email_index');
            $table->dropIndex('users_organization_phone_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->unique(['organization_id', 'email'], 'users_organization_id_email_unique');
            $table->unique(['organization_id', 'phone'], 'users_organization_phone_unique');
        });
    }
};
