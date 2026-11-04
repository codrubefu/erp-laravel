<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX users_organization_user_code_unique ON [users]');
        DB::statement('DROP INDEX users_organization_phone_unique ON [users]');

        DB::statement('CREATE UNIQUE INDEX users_organization_user_code_unique ON [users] ([organization_id], [user_code]) WHERE [user_code] IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX users_organization_phone_unique ON [users] ([organization_id], [phone]) WHERE [phone] IS NOT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX users_organization_user_code_unique ON [users]');
        DB::statement('DROP INDEX users_organization_phone_unique ON [users]');

        DB::statement('CREATE UNIQUE INDEX users_organization_user_code_unique ON [users] ([organization_id], [user_code])');
        DB::statement('CREATE UNIQUE INDEX users_organization_phone_unique ON [users] ([organization_id], [phone])');
    }
};
