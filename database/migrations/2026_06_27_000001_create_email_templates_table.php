<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('subject')->default('');
            $table->text('body')->default('');
            $table->string('category')->nullable();
            // Partagé = visible/utilisable par toute l'équipe (sinon cloisonné par owner).
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
            $table->index('is_shared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
