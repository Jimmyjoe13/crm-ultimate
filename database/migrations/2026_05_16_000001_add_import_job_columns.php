<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_jobs', function (Blueprint $table): void {
            $table->unsignedInteger('duplicates_skipped')->default(0)->after('failed_rows');
            $table->jsonb('mapping')->nullable()->after('duplicates_skipped');
        });
    }

    public function down(): void
    {
        Schema::table('import_jobs', function (Blueprint $table): void {
            $table->dropColumn(['duplicates_skipped', 'mapping']);
        });
    }
};
