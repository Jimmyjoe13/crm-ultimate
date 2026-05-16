<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Tables pivots N-M ──────────────────────────────────────────────

        Schema::create('contact_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // employee | decision_maker | influencer | former
            $table->string('role')->default('employee');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['contact_id', 'company_id']);
            $table->index(['company_id', 'is_primary']);
        });

        Schema::create('deal_contact', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // primary | technical | billing | other
            $table->string('role')->default('primary');
            $table->timestamps();
            $table->unique(['deal_id', 'contact_id']);
        });

        Schema::create('deal_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // customer | partner | reseller
            $table->string('role')->default('customer');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['deal_id', 'company_id']);
            $table->index(['deal_id', 'is_primary']);
        });

        // ── 2. Lifecycle stages sur contacts et companies ─────────────────────

        Schema::table('contacts', function (Blueprint $table): void {
            // lead | mql | sql | opportunity | customer | evangelist | other
            $table->string('lifecycle_stage')->default('lead')->after('job_title')->index();
            // new | open | in_progress | connected | unqualified | bad_fit
            $table->string('lead_status')->nullable()->after('lifecycle_stage');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('lifecycle_stage')->default('lead')->after('country')->index();
            $table->string('lead_status')->nullable()->after('lifecycle_stage');
        });

        // ── 3. Backfill : FK scalaires → pivots ──────────────────────────────

        DB::statement("
            INSERT INTO contact_company (contact_id, company_id, role, is_primary, created_at, updated_at)
            SELECT id, company_id, 'employee', true, NOW(), NOW()
            FROM contacts
            WHERE company_id IS NOT NULL
            ON CONFLICT (contact_id, company_id) DO NOTHING
        ");

        DB::statement("
            INSERT INTO deal_company (deal_id, company_id, role, is_primary, created_at, updated_at)
            SELECT id, company_id, 'customer', true, NOW(), NOW()
            FROM deals
            WHERE company_id IS NOT NULL
            ON CONFLICT (deal_id, company_id) DO NOTHING
        ");

        DB::statement("
            INSERT INTO deal_contact (deal_id, contact_id, role, created_at, updated_at)
            SELECT id, contact_id, 'primary', NOW(), NOW()
            FROM deals
            WHERE contact_id IS NOT NULL
            ON CONFLICT (deal_id, contact_id) DO NOTHING
        ");

        // ── 4. Drop des colonnes FK scalaires ────────────────────────────────

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('deals', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['contact_id']);
            $table->dropColumn(['company_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        // Restore scalar FK columns (data loss — pivot rows not converted back)
        Schema::table('deals', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['lifecycle_stage', 'lead_status']);
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['lifecycle_stage', 'lead_status']);
        });

        Schema::dropIfExists('deal_company');
        Schema::dropIfExists('deal_contact');
        Schema::dropIfExists('contact_company');
    }
};
