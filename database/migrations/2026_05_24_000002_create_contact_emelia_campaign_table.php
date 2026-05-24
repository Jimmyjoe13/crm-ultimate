<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_emelia_campaign', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('emelia_campaign_id')->constrained('emelia_campaigns')->cascadeOnDelete();
            $table->string('emelia_contact_id', 64)->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('first_event_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(['contact_id', 'emelia_campaign_id']);
            $table->index(['emelia_campaign_id', 'last_event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_emelia_campaign');
    }
};
