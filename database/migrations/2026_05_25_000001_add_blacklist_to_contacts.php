<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('blacklisted_at')->nullable()->index()->after('lead_status');
            $table->string('blacklist_reason', 255)->nullable()->after('blacklisted_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['blacklisted_at']);
            $table->dropColumn(['blacklisted_at', 'blacklist_reason']);
        });
    }
};
