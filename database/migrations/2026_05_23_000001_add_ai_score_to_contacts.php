<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->smallInteger('ai_score')->nullable()->after('lifecycle_stage');
            $table->timestamp('ai_score_updated_at')->nullable()->after('ai_score');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['ai_score', 'ai_score_updated_at']);
        });
    }
};
