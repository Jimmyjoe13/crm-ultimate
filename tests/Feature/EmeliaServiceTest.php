<?php

namespace Tests\Feature;

use App\Services\EmeliaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EmeliaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.emelia.key'      => 'test-api-key',
            'services.emelia.base_url' => 'https://api.emelia.io',
            'services.emelia.timeout'  => 15,
        ]);
    }

    public function test_list_campaigns_returns_data(): void
    {
        Http::fake([
            'api.emelia.io/campaigns' => Http::response([
                ['id' => 'camp_1', 'name' => 'Campagne A'],
                ['id' => 'camp_2', 'name' => 'Campagne B'],
            ], 200),
        ]);

        $service = new EmeliaService();
        $result = $service->listCampaigns();

        $this->assertCount(2, $result);
        $this->assertEquals('Campagne A', $result[0]['name']);

        Http::assertSent(fn ($req) =>
            $req->url() === 'https://api.emelia.io/campaigns' &&
            $req->header('Authorization')[0] === 'Bearer test-api-key'
        );
    }

    public function test_add_contact_to_campaign_sends_correct_payload(): void
    {
        Http::fake([
            'api.emelia.io/campaigns/camp_1/contacts' => Http::response(['id' => 'emcontact_99'], 201),
        ]);

        $service = new EmeliaService();
        $result = $service->addContactToCampaign('camp_1', [
            'email'     => 'alice@test.com',
            'firstName' => 'Alice',
            'lastName'  => 'Dupont',
        ]);

        $this->assertEquals('emcontact_99', $result['id']);

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), '/campaigns/camp_1/contacts') &&
            $req->data()['email'] === 'alice@test.com'
        );
    }

    public function test_throws_on_api_error(): void
    {
        Http::fake([
            'api.emelia.io/campaigns' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/listCampaigns failed/');

        (new EmeliaService())->listCampaigns();
    }

    public function test_throws_when_api_key_missing(): void
    {
        config(['services.emelia.key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not configured/');

        new EmeliaService();
    }
}
