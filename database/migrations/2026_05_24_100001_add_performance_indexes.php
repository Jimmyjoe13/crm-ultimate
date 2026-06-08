<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lot 1 — P1 : indexes manquants sur contacts, pipeline_stages, activities
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index('first_name');
            $table->index('last_name');
            $table->index('ai_score');
        });

        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->index('is_won');
            $table->index('is_lost');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id'], 'activities_subject_idx');
        });

        // Index pour les lookups « contact primaire d'un deal » via pivot
        Schema::table('deal_contact', function (Blueprint $table) {
            $table->index(['deal_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['first_name']);
            $table->dropIndex(['last_name']);
            $table->dropIndex(['ai_score']);
        });

        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->dropIndex(['is_won']);
            $table->dropIndex(['is_lost']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_subject_idx');
        });

        Schema::table('deal_contact', function (Blueprint $table) {
            $table->dropIndex(['deal_id', 'role']);
        });
    }
};
