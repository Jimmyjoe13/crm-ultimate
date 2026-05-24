<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emelia_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('emelia_id', 64)->unique();
            $table->string('name');
            $table->string('status', 32)->nullable();
            $table->string('client_name')->nullable();
            $table->text('objective')->nullable();
            $table->jsonb('tags')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emelia_campaigns');
    }
};
