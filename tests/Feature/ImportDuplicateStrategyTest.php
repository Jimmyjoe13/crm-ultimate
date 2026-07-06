<?php

namespace Tests\Feature;

use App\Jobs\ProcessCsvImport;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\ImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportDuplicateStrategyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::createWithRole([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function runImport(string $csv, array $mapping, string $strategy): ImportJob
    {
        Storage::fake('local');
        Cache::flush();
        $path = 'imports/dupe_test.csv';
        Storage::disk('local')->put($path, $csv);

        $job = ImportJob::query()->create([
            'user_id' => $this->user->id,
            'entity_type' => 'contact',
            'filename' => 'dupe_test.csv',
            'status' => 'pending',
            'mapping' => $mapping,
            'duplicate_strategy' => $strategy,
        ]);

        ProcessCsvImport::dispatchSync($job->id, $path);

        return $job->fresh();
    }

    public function test_skip_strategy_does_not_modify_existing(): void
    {
        Contact::query()->create([
            'email' => 'jane@test.com',
            'first_name' => 'Jane',
            'last_name' => 'Old',
            'job_title' => 'Original',
            'owner_id' => $this->user->id,
        ]);

        $csv = "email,first_name,last_name,job_title\njane@test.com,Jane,New,Updated";
        $job = $this->runImport($csv, [
            'email' => 'email',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'job_title' => 'job_title',
        ], 'skip');

        $this->assertSame('completed', $job->status);
        $this->assertSame(1, $job->duplicates_skipped);
        $this->assertSame(1, Contact::count());
        $this->assertSame('Original', Contact::first()->job_title);
    }

    public function test_update_strategy_updates_existing_record(): void
    {
        Contact::query()->create([
            'email' => 'jane@test.com',
            'first_name' => 'Jane',
            'job_title' => 'Old Title',
            'owner_id' => $this->user->id,
        ]);

        $csv = "email,job_title\njane@test.com,New Title";
        $job = $this->runImport($csv, [
            'email' => 'email',
            'job_title' => 'job_title',
        ], 'update');

        $this->assertSame('completed', $job->status);
        $this->assertSame(1, Contact::count());
        $this->assertSame('New Title', Contact::first()->job_title);
    }

    public function test_update_strategy_merges_custom_values(): void
    {
        Cache::flush();
        CustomField::create([
            'entity_type' => 'contact',
            'key' => 'linkedin_url',
            'label' => 'LinkedIn',
            'field_type' => 'text',
            'position' => 1,
        ]);
        CustomField::create([
            'entity_type' => 'contact',
            'key' => 'notes',
            'label' => 'Notes',
            'field_type' => 'text',
            'position' => 2,
        ]);

        Contact::query()->create([
            'email' => 'jane@test.com',
            'first_name' => 'Jane',
            'owner_id' => $this->user->id,
            'custom_values' => ['linkedin_url' => 'https://old-link.com'],
        ]);

        // Import adds 'notes' custom field — existing 'linkedin_url' must be preserved
        $csv = "email,notes\njane@test.com,Some note";
        $job = $this->runImport($csv, [
            'email' => 'email',
            'notes' => 'notes',
        ], 'update');

        $this->assertSame('completed', $job->status);
        $contact = Contact::first();
        $this->assertSame('https://old-link.com', $contact->custom_values['linkedin_url'] ?? null);
        $this->assertSame('Some note', $contact->custom_values['notes'] ?? null);
    }

    public function test_create_strategy_inserts_anyway(): void
    {
        Contact::query()->create([
            'first_name' => 'Jane',
            'email' => 'jane@test.com',
            'owner_id' => $this->user->id,
        ]);

        $csv = "first_name,email\nDuplicate,jane@test.com";
        $job = $this->runImport($csv, [
            'first_name' => 'first_name',
            'email' => 'email',
        ], 'create');

        $this->assertSame('completed', $job->status);
        $this->assertSame(2, Contact::count());
    }
}
