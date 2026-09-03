<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services') || Schema::hasTable('subscriptions')) {
            return;
        }

        // SQL Server implements Laravel's enum() as a CHECK CONSTRAINT, which
        // dropColumn() doesn't drop on its own, so remove it explicitly first.
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("
                DECLARE @sql NVARCHAR(MAX) = '';
                SELECT @sql += 'ALTER TABLE [services] DROP CONSTRAINT [' + cc.name + '];'
                FROM sys.check_constraints cc
                JOIN sys.columns c
                    ON c.object_id = cc.parent_object_id
                    AND c.column_id = cc.parent_column_id
                WHERE cc.parent_object_id = OBJECT_ID(N'[services]')
                    AND c.name = 'billing_interval';
                EXEC(@sql);
            ");
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['billing_interval']);
            $table->dropColumn(['billing_interval', 'trial_days']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('services') || Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('services', function (Blueprint $table) {
            $table->enum('billing_interval', ['monthly', 'yearly'])->default('monthly')->after('currency');
            $table->integer('trial_days')->default(0)->after('duration_days');
            $table->index('billing_interval');
        });
    }
};
