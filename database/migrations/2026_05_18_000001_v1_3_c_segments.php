<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('entity_type')->index(); // contact | company | deal
            $table->jsonb('rules')->default('{}');  // arbre AND/OR
            $table->unsignedInteger('last_count')->nullable();
            $table->timestamp('last_computed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entity_type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
