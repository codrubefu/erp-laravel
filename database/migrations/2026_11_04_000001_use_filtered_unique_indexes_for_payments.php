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

        $this->dropIndexes();

        DB::statement('CREATE UNIQUE INDEX payments_external_reference_unique ON [payments] ([external_reference]) WHERE [external_reference] IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX payments_organization_receipt_number_unique ON [payments] ([organization_id], [receipt_number]) WHERE [organization_id] IS NOT NULL AND [receipt_number] IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX payments_provider_provider_transaction_id_unique ON [payments] ([provider], [provider_transaction_id]) WHERE [provider] IS NOT NULL AND [provider_transaction_id] IS NOT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlsrv') {
            return;
        }

        $this->dropIndexes();

        DB::statement('CREATE UNIQUE INDEX payments_external_reference_unique ON [payments] ([external_reference])');
        DB::statement('CREATE UNIQUE INDEX payments_organization_receipt_number_unique ON [payments] ([organization_id], [receipt_number])');
        DB::statement('CREATE UNIQUE INDEX payments_provider_provider_transaction_id_unique ON [payments] ([provider], [provider_transaction_id])');
    }

    private function dropIndexes(): void
    {
        DB::statement('DROP INDEX payments_external_reference_unique ON [payments]');
        DB::statement('DROP INDEX payments_organization_receipt_number_unique ON [payments]');
        DB::statement('DROP INDEX payments_provider_provider_transaction_id_unique ON [payments]');
    }
};
