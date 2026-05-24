<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Lot 5 — P3 : index GIN trigram pour recherche ILIKE ultra-rapide sur contacts
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement("
            CREATE INDEX contacts_search_trgm ON contacts USING gin (
                (coalesce(first_name, '') || ' ' || coalesce(last_name, '') || ' ' || coalesce(email, '')) gin_trgm_ops
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contacts_search_trgm');
    }
};
