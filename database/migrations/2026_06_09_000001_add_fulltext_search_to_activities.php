<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Index GIN full-text search sur les activités (titre + body)
        // Permet au RAG léger de chercher contextuellement dans les notes
        DB::statement("
            CREATE INDEX IF NOT EXISTS activities_fulltext_idx
            ON activities USING gin(
                to_tsvector('french',
                    coalesce(title, '') || ' ' || coalesce(body, '')
                )
            )
        ");

        // Index GIN trigram sur le titre pour la recherche partielle (ILIKE '%mot%')
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement("
            CREATE INDEX IF NOT EXISTS activities_title_trgm_idx
            ON activities USING gin(title gin_trgm_ops)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS activities_fulltext_idx');
        DB::statement('DROP INDEX IF EXISTS activities_title_trgm_idx');
    }
};
