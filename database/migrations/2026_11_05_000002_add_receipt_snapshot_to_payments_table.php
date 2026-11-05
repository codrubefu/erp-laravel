<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'receipt_snapshot')) {
                $table->json('receipt_snapshot')->nullable();
            }

            if (! Schema::hasColumn('payments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = collect(['receipt_snapshot', 'cancellation_reason'])
                ->filter(fn (string $column) => Schema::hasColumn('payments', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
