<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('emelia_campaign_id')
                ->nullable()
                ->after('source')
                ->constrained('emelia_campaigns')
                ->nullOnDelete();

            $table->index(['emelia_campaign_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['emelia_campaign_id']);
            $table->dropIndex(['emelia_campaign_id', 'occurred_at']);
            $table->dropColumn('emelia_campaign_id');
        });
    }
};
