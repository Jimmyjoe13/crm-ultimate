<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index pour les agrégats de tracking d'ouverture Juliette (FleetController::collectJulietteTracking) :
     * filtre systématique (source, type) + tri/borne temporelle (occurred_at) sur la table activities.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->index(['source', 'type', 'occurred_at'], 'activities_source_type_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex('activities_source_type_occurred_idx');
        });
    }
};
