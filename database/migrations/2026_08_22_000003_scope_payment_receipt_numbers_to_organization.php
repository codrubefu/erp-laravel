<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            try {
                $table->dropUnique(['receipt_number']);
            } catch (Throwable) {
            }

            $table->unique(['organization_id', 'receipt_number'], 'payments_organization_receipt_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_organization_receipt_number_unique');
            $table->unique('receipt_number');
        });
    }
};
