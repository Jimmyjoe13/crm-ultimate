<?php

namespace Tests\Feature;

use App\Jobs\ProcessCsvImport;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ImportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_large_csv_processes_all_rows_and_skips_duplicates(): void
    {
        Storage::fake('local');

        $user = User::query()->create([
            'name' => 'Import Admin',
            'email' => 'import@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        // Build CSV with 1500 unique contacts + 10 duplicates (same email as rows 1-10)
        $lines = ['first_name,last_name,email,phone'];
        for ($i = 1; $i <= 1500; $i++) {
            $lines[] = "Prénom{$i},Nom{$i},contact{$i}@example.com,060000{$i}";
        }
        // 10 duplicate rows
        for ($i = 1; $i <= 10; $i++) {
            $lines[] = "Dupe{$i},Dupe{$i},contact{$i}@example.com,0600000000";
        }

        $csvContent = implode("\n", $lines);
        $path = 'imports/test_bulk.csv';
        Storage::disk('local')->put($path, $csvContent);

        $job = ImportJob::query()->create([
            'user_id' => $user->id,
            'entity_type' => 'contact',
            'filename' => 'test_bulk.csv',
            'status' => 'pending',
        ]);

        ProcessCsvImport::dispatchSync($job->id, $path);

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(1510, $job->total_rows);
        $this->assertSame(1500, $job->processed_rows);
        $this->assertSame(10, $job->duplicates_skipped);
        $this->assertSame(0, $job->failed_rows);
        $this->assertCount(1500, Contact::all());
    }

    public function test_import_applies_user_mapping(): void
    {
        Storage::fake('local');

        $user = User::query()->create([
            'name' => 'Mapping Admin',
            'email' => 'mapping@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $lines = ['Prénom,NomDeFamille,Courriel'];
        for ($i = 1; $i <= 5; $i++) {
            $lines[] = "Alice{$i},Dupont{$i},alice{$i}@example.com";
        }

        $path = 'imports/test_mapping.csv';
        Storage::disk('local')->put($path, implode("\n", $lines));

        $job = ImportJob::query()->create([
            'user_id' => $user->id,
            'entity_type' => 'contact',
            'filename' => 'test_mapping.csv',
            'status' => 'pending',
            'mapping' => ['Prénom' => 'first_name', 'NomDeFamille' => 'last_name', 'Courriel' => 'email'],
        ]);

        ProcessCsvImport::dispatchSync($job->id, $path);

        $job->refresh();

        $this->assertSame('completed', $job->status);
        $this->assertSame(5, $job->processed_rows);
        $this->assertSame('Alice1', Contact::first()->first_name);
        $this->assertSame('Dupont1', Contact::first()->last_name);
    }

    public function test_import_enriches_company_fields_from_contact_csv(): void
    {
        Storage::fake('local');

        $user = User::query()->create([
            'name' => 'Enrich Admin',
            'email' => 'enrich@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        // CSV contact with virtual company fields
        $lines = ['first_name,last_name,email,__company_name,__company_domain,__company_industry'];
        $lines[] = 'Marie,Durand,marie@acme.fr,ACME Corp,acme.fr,SaaS';
        $lines[] = 'Jean,Petit,jean@acme.fr,ACME Corp,acme.fr,SaaS'; // same company → should reuse

        $path = 'imports/test_enrich.csv';
        Storage::disk('local')->put($path, implode("\n", $lines));

        $job = ImportJob::query()->create([
            'user_id' => $user->id,
            'entity_type' => 'contact',
            'filename' => 'test_enrich.csv',
            'status' => 'pending',
        ]);

        ProcessCsvImport::dispatchSync($job->id, $path);

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame(2, $job->processed_rows);

        // Only one company should have been created (matched by domain)
        $this->assertCount(1, Company::all());
        $company = Company::first();
        $this->assertSame('ACME Corp', $company->name);
        $this->assertSame('acme.fr', $company->domain);
        $this->assertSame('SaaS', $company->industry);

        // Both contacts attached to that company via pivot
        $this->assertCount(2, $company->contacts);
        $this->assertSame(2, Contact::count());
    }

    public function test_import_reuses_existing_company_by_domain(): void
    {
        Storage::fake('local');

        $user = User::query()->create([
            'name' => 'Domain Admin',
            'email' => 'domain@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        // Pre-existing company with same domain
        $existing = Company::query()->create([
            'name' => 'Existing Corp',
            'domain' => 'existing.io',
        ]);

        $lines = ['first_name,email,__company_name,__company_domain'];
        $lines[] = 'Sophie,sophie@existing.io,Nouveau Nom,existing.io';

        $path = 'imports/test_domain.csv';
        Storage::disk('local')->put($path, implode("\n", $lines));

        $job = ImportJob::query()->create([
            'user_id' => $user->id,
            'entity_type' => 'contact',
            'filename' => 'test_domain.csv',
            'status' => 'pending',
        ]);

        ProcessCsvImport::dispatchSync($job->id, $path);

        // Should reuse existing company (matched by domain), not create a duplicate
        $this->assertCount(1, Company::all());
        $this->assertSame($existing->id, Contact::first()->companies()->first()->id);
    }

    public function test_import_preview_endpoint_returns_headers_and_sample(): void
    {
        Storage::fake('local');

        $user = User::query()->create([
            'name' => 'Preview Admin',
            'email' => 'preview@test.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'preview@test.com',
            'password' => 'password',
        ])->json('access_token');

        $csvContent = "Prénom,Nom,Email\nMarie,Durand,marie@acme.fr\nJean,Petit,jean@acme.fr";
        $file = File::fake()->createWithContent('contacts.csv', $csvContent);

        $this->withToken($token)
            ->postJson('/api/v1/imports/preview', [
                'entity_type' => 'contact',
                'file' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'preview_token',
                'headers',
                'auto_mapping',
                'available_fields',
                'sample_rows',
            ])
            ->assertJsonCount(3, 'headers')
            ->assertJsonCount(2, 'sample_rows');
    }
}
