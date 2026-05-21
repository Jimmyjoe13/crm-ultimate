<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('emelia_campaign_id', 255)->nullable()->after('emelia_contact_id');
            $table->string('emelia_campaign_name', 255)->nullable()->after('emelia_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['emelia_campaign_id', 'emelia_campaign_name']);
        });
    }
};
