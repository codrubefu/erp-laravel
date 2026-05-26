<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        foreach (['users', 'groups', 'rights', 'locations', 'articles', 'subscriptions', 'events', 'event_occurrences', 'personal_access_tokens'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
                $table->index('organization_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'groups', 'rights', 'locations', 'articles', 'subscriptions', 'events', 'event_occurrences', 'personal_access_tokens'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }

        Schema::dropIfExists('organizations');
    }
};
