<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->string('source', 32)->default('manual')->after('type');
            $table->string('external_id', 255)->nullable()->after('source');
            $table->jsonb('metadata')->nullable()->after('body');
            $table->unique(['source', 'external_id'], 'activities_source_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropUnique('activities_source_external_unique');
            $table->dropColumn(['source', 'external_id', 'metadata']);
        });
    }
};
