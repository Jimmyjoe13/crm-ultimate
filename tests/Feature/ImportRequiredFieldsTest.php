<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportRequiredFieldsTest extends TestCase
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

    public function test_preview_response_exposes_required_fields_for_contact(): void
    {
        $admin = $this->createAdmin();

        $csv = UploadedFile::fake()->createWithContent(
            'contacts.csv',
            "first_name,last_name,email\nJane,Doe,jane@test.com"
        );

        $response = $this->withAuth($admin)->post('/imports/preview', [
            '_token' => 'test',
            'entity_type' => 'contact',
            'file' => $csv,
        ]);

        $response->assertOk();
        $this->assertContains('email', $response->json('required_fields'));
    }

    public function test_preview_response_exposes_required_fields_for_company(): void
    {
        $admin = $this->createAdmin();

        $csv = UploadedFile::fake()->createWithContent(
            'companies.csv',
            "name,domain\nAcme,acme.fr"
        );

        $response = $this->withAuth($admin)->post('/imports/preview', [
            '_token' => 'test',
            'entity_type' => 'company',
            'file' => $csv,
        ]);

        $response->assertOk();
        $this->assertContains('name', $response->json('required_fields'));
    }

    public function test_store_rejects_when_required_field_unmapped_for_contact(): void
    {
        $admin = $this->createAdmin();

        $path = 'imports/preview/req_test.csv';
        Storage::disk('local')->put($path, "first_name,last_name\nJane,Doe");

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/imports', [
                '_token' => 'test',
                'entity_type' => 'contact',
                'preview_token' => $path,
                'mapping' => [
                    'first_name' => 'first_name',
                    'last_name' => 'last_name',
                    // email intentionally not mapped
                ],
            ]);

        $response->assertStatus(422);
        $this->assertContains('email', $response->json('missing'));

        Storage::disk('local')->delete($path);
    }

    public function test_store_rejects_when_name_unmapped_for_company(): void
    {
        $admin = $this->createAdmin();

        $path = 'imports/preview/req_co_test.csv';
        Storage::disk('local')->put($path, "domain\nacme.fr");

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/imports', [
                '_token' => 'test',
                'entity_type' => 'company',
                'preview_token' => $path,
                'mapping' => ['domain' => 'domain'],
            ]);

        $response->assertStatus(422);
        $this->assertContains('name', $response->json('missing'));

        Storage::disk('local')->delete($path);
    }

    public function test_store_accepts_when_all_required_fields_are_mapped(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();

        $path = 'imports/preview/req_ok.csv';
        Storage::disk('local')->put($path, "email,first_name\njane@test.com,Jane");

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/imports', [
                '_token' => 'test',
                'entity_type' => 'contact',
                'preview_token' => $path,
                'mapping' => [
                    'email' => 'email',
                    'first_name' => 'first_name',
                ],
            ]);

        $response->assertStatus(202);

        Storage::disk('local')->delete('imports/'.basename($path));
    }

    public function test_store_validates_duplicate_strategy_enum(): void
    {
        $admin = $this->createAdmin();

        $path = 'imports/preview/enum_test.csv';
        Storage::disk('local')->put($path, "email\njane@test.com");

        $response = $this->withAuth($admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/imports', [
                '_token' => 'test',
                'entity_type' => 'contact',
                'preview_token' => $path,
                'mapping' => ['email' => 'email'],
                'duplicate_strategy' => 'invalid_value',
            ]);

        $response->assertStatus(422);

        Storage::disk('local')->delete($path);
    }
}
