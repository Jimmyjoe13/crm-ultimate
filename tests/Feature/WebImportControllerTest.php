<?php

namespace Tests\Feature;

use App\Models\ImportJob;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function withAuth(User $user): static
    {
        $jwt = app(JwtService::class)->encode([
            'sub' => $user->id,
            'exp' => time() + 3600,
        ]);

        return $this->withCookies(['crm_jwt' => $jwt])
                    ->withSession(['_token' => 'test']);
    }

    private function createAdmin(): User
    {
        return User::createWithRole([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_create_page_loads_for_contact(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAuth($admin)->get('/imports/contact/create');

        $response->assertOk();
    }

    public function test_create_page_loads_for_company(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAuth($admin)->get('/imports/company/create');

        $response->assertOk();
    }

    public function test_create_page_404_for_invalid_entity(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAuth($admin)->get('/imports/invalid/create');

        $response->assertNotFound();
    }

    public function test_preview_parses_csv_and_returns_mapping(): void
    {
        $admin = $this->createAdmin();

        $csv = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "first_name,last_name,email\nJane,Doe,jane@test.com\nJohn,Smith,john@test.com"
        );

        $response = $this->withAuth($admin)->post('/imports/preview', [
            '_token' => 'test',
            'entity_type' => 'contact',
            'file' => $csv,
        ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertEquals(['first_name', 'last_name', 'email'], $json['headers']);
        $this->assertEquals('email', $json['auto_mapping']['email']);
        $this->assertEquals('first_name', $json['auto_mapping']['first_name']);
        $this->assertArrayHasKey('available_fields', $json);
        $this->assertArrayHasKey('sample_rows', $json);
        $this->assertArrayHasKey('required_fields', $json);
        $this->assertContains('email', $json['required_fields']);
    }

    public function test_store_creates_import_job_and_dispatches(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();

        // Simulate a preview token (file already uploaded)
        $path = 'imports/preview/test123.csv';
        Storage::disk('local')->put($path, "first_name,email\nJane,jane@test.com");

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/imports', [
                '_token' => 'test',
                'entity_type' => 'contact',
                'preview_token' => $path,
                'mapping' => [
                    'first_name' => 'first_name',
                    'email' => 'email',
                ],
                'duplicate_strategy' => 'skip',
            ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['id', 'status']);
        $this->assertDatabaseHas('import_jobs', [
            'user_id'            => $admin->id,
            'entity_type'        => 'contact',
            'status'             => 'pending',
            'duplicate_strategy' => 'skip',
        ]);

        // Cleanup
        Storage::disk('local')->delete('imports/' . basename($path));
    }

    public function test_store_rejects_invalid_preview_token(): void
    {
        $admin = $this->createAdmin();

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/imports', [
                '_token' => 'test',
                'entity_type' => 'contact',
                'preview_token' => 'malicious/path/file.csv',
                'mapping' => ['email' => 'email'],
            ]);

        $response->assertStatus(422);
    }

    public function test_status_returns_job_info(): void
    {
        $admin = $this->createAdmin();

        $job = ImportJob::create([
            'user_id' => $admin->id,
            'entity_type' => 'contact',
            'filename' => 'test.csv',
            'status' => 'completed',
            'total_rows' => 10,
            'processed_rows' => 9,
            'failed_rows' => 1,
            'duplicates_skipped' => 0,
            'mapping' => ['email' => 'email'],
        ]);

        $response = $this->withAuth($admin)->get('/imports/' . $job->id . '/status');

        $response->assertOk();
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('total_rows', 10);
        $response->assertJsonPath('processed_rows', 9);
        $response->assertJsonPath('failed_rows', 1);
    }
}
