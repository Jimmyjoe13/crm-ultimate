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

class ImportCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::createWithRole([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_ADMIN,
        ]);
    }

    private function runJob(User $user, string $csv, array $mapping, string $entityType = 'contact'): ImportJob
    {
        Storage::fake('local');
        Cache::flush();
        $path = 'imports/test_cf.csv';
        Storage::disk('local')->put($path, $csv);

        $job = ImportJob::query()->create([
            'user_id'     => $user->id,
            'entity_type' => $entityType,
            'filename'    => 'test_cf.csv',
            'status'      => 'pending',
            'mapping'     => $mapping,
        ]);

        ProcessCsvImport::dispatchSync($job->id, $path);

        return $job->fresh();
    }

    public function test_import_writes_mapped_custom_fields_to_custom_values(): void
    {
        $user = $this->makeUser();
        CustomField::create([
            'entity_type' => 'contact',
            'key'         => 'linkedin_url',
            'label'       => 'LinkedIn',
            'field_type'  => 'text',
            'position'    => 1,
        ]);

        $csv = "first_name,email,LinkedIn\nJane,jane@test.com,https://linkedin.com/in/jane";
        $job = $this->runJob($user, $csv, [
            'first_name' => 'first_name',
            'email'      => 'email',
            'LinkedIn'   => 'linkedin_url',
        ]);

        $this->assertSame('completed', $job->status);
        $contact = Contact::first();
        $this->assertNotNull($contact);
        $this->assertSame('https://linkedin.com/in/jane', $contact->custom_values['linkedin_url']);
    }

    public function test_import_casts_number_custom_field(): void
    {
        $user = $this->makeUser();
        CustomField::create([
            'entity_type' => 'contact',
            'key'         => 'budget',
            'label'       => 'Budget',
            'field_type'  => 'number',
            'position'    => 1,
        ]);

        $csv = "first_name,email,Budget\nClient,client@test.com,42.5";
        $job = $this->runJob($user, $csv, [
            'first_name' => 'first_name',
            'email'      => 'email',
            'Budget'     => 'budget',
        ]);

        $contact = Contact::first();
        $this->assertNotNull($contact);
        $this->assertEquals(42.5, $contact->custom_values['budget']);
    }

    public function test_import_casts_date_custom_field(): void
    {
        $user = $this->makeUser();
        CustomField::create([
            'entity_type' => 'contact',
            'key'         => 'renewal_date',
            'label'       => 'Date renouvellement',
            'field_type'  => 'date',
            'position'    => 1,
        ]);

        $csv = "first_name,email,Date renouvellement\nClient,client@test.com,2026-05-15";
        $job = $this->runJob($user, $csv, [
            'first_name'        => 'first_name',
            'email'             => 'email',
            'Date renouvellement' => 'renewal_date',
        ]);

        $contact = Contact::first();
        $this->assertNotNull($contact);
        $this->assertSame('2026-05-15', $contact->custom_values['renewal_date']);
    }

    public function test_import_ignores_unknown_custom_keys(): void
    {
        $user = $this->makeUser();

        $csv = "first_name,email,UnknownField\nClient,client@test.com,somevalue";
        $job = $this->runJob($user, $csv, [
            'first_name'   => 'first_name',
            'email'        => 'email',
            'UnknownField' => 'unknown_key_xyz',
        ]);

        $this->assertSame('completed', $job->status);
        $contact = Contact::first();
        $this->assertNotNull($contact);
        // unknown key should not appear in custom_values
        $this->assertArrayNotHasKey('unknown_key_xyz', $contact->custom_values ?? []);
    }
}
