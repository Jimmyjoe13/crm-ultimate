<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Segment;
use App\Services\SegmentQueryEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SegmentQueryEngineTest extends TestCase
{
    use RefreshDatabase;

    private SegmentQueryEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SegmentQueryEngine();
    }

    private function segment(string $entityType, array $rules): Segment
    {
        return new Segment(['entity_type' => $entityType, 'rules' => $rules]);
    }

    // ── Core scalar operators ─────────────────────────────────────────────

    public function test_eq_operator(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer']);
        Contact::factory()->create(['lifecycle_stage' => 'lead']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'lifecycle_stage', 'operator' => 'eq', 'value' => 'customer'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_neq_operator(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer']);
        Contact::factory()->create(['lifecycle_stage' => 'lead']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'lifecycle_stage', 'operator' => 'neq', 'value' => 'customer'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_in_operator(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer']);
        Contact::factory()->create(['lifecycle_stage' => 'mql']);
        Contact::factory()->create(['lifecycle_stage' => 'lead']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'lifecycle_stage', 'operator' => 'in', 'value' => ['customer', 'mql']],
            ],
        ]))->pluck('id');

        $this->assertCount(2, $ids);
    }

    public function test_not_in_operator(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer']);
        Contact::factory()->create(['lifecycle_stage' => 'lead']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'lifecycle_stage', 'operator' => 'not_in', 'value' => ['customer']],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_contains_operator(): void
    {
        Contact::factory()->create(['email' => 'alice@acme.com']);
        Contact::factory()->create(['email' => 'bob@other.com']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'email', 'operator' => 'contains', 'value' => 'acme'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_starts_with_operator(): void
    {
        Contact::factory()->create(['first_name' => 'Alice']);
        Contact::factory()->create(['first_name' => 'Bob']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'first_name', 'operator' => 'starts_with', 'value' => 'Al'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_gt_operator_on_amount(): void
    {
        $pipeline = Pipeline::query()->create(['name' => 'P', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'S', 'position' => 1, 'probability' => 50]);

        Deal::factory()->create(['amount' => 5000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);
        Deal::factory()->create(['amount' => 15000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);

        $ids = $this->engine->buildQuery($this->segment('deal', [
            'op' => 'AND', 'rules' => [
                ['field' => 'amount', 'operator' => 'gt', 'value' => 10000],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_between_operator(): void
    {
        $pipeline = Pipeline::query()->create(['name' => 'P', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'S', 'position' => 1, 'probability' => 50]);

        Deal::factory()->create(['amount' => 1000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);
        Deal::factory()->create(['amount' => 5000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);
        Deal::factory()->create(['amount' => 20000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);

        $ids = $this->engine->buildQuery($this->segment('deal', [
            'op' => 'AND', 'rules' => [
                ['field' => 'amount', 'operator' => 'between', 'value' => [3000, 10000]],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_is_null_operator(): void
    {
        Contact::factory()->create(['lead_status' => null]);
        Contact::factory()->create(['lead_status' => 'new']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'lead_status', 'operator' => 'is_null'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_is_not_null_operator(): void
    {
        Contact::factory()->create(['lead_status' => null]);
        Contact::factory()->create(['lead_status' => 'new']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'lead_status', 'operator' => 'is_not_null'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_days_ago_lt_operator(): void
    {
        $recent = Contact::factory()->create();
        $old = Contact::factory()->create();
        \Illuminate\Support\Facades\DB::table('contacts')
            ->where('id', $old->id)
            ->update(['created_at' => now()->subDays(30)]);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'created_at', 'operator' => 'days_ago_lt', 'value' => 7],
            ],
        ]))->pluck('id');

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    // ── Custom fields ─────────────────────────────────────────────────────

    public function test_custom_field_eq(): void
    {
        CustomField::query()->create(['entity_type' => 'contact', 'key' => 'segment', 'label' => 'Segment', 'field_type' => 'text']);

        Contact::factory()->create(['custom_values' => ['segment' => 'premium']]);
        Contact::factory()->create(['custom_values' => ['segment' => 'standard']]);

        $engine = new SegmentQueryEngine();
        $ids = $engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'custom.segment', 'operator' => 'eq', 'value' => 'premium'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_unknown_custom_field_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown custom field/');

        (new SegmentQueryEngine())->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'custom.nonexistent_key', 'operator' => 'eq', 'value' => 'x'],
            ],
        ]))->count();
    }

    // ── Relational operators ──────────────────────────────────────────────

    public function test_rel_deals_amount_gt_on_contact(): void
    {
        $pipeline = Pipeline::query()->create(['name' => 'P', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'S', 'position' => 1, 'probability' => 50]);

        $contact = Contact::factory()->create();
        $deal = Deal::factory()->create(['amount' => 50000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);
        $contact->deals()->attach($deal->id, ['role' => 'primary']);

        $other = Contact::factory()->create();

        $ids = (new SegmentQueryEngine())->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'rel.deals.amount', 'operator' => 'gt', 'value' => 10000],
            ],
        ]))->pluck('id');

        $this->assertContains($contact->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_rel_deals_with_rel_filter_on_contact(): void
    {
        $pipeline = Pipeline::query()->create(['name' => 'P', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'S', 'position' => 1, 'probability' => 50]);

        $contact = Contact::factory()->create();
        $wonDeal = Deal::factory()->create(['status' => 'won', 'amount' => 5000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);
        $contact->deals()->attach($wonDeal->id, ['role' => 'primary']);

        $contact2 = Contact::factory()->create();
        $openDeal = Deal::factory()->create(['status' => 'open', 'amount' => 5000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);
        $contact2->deals()->attach($openDeal->id, ['role' => 'primary']);

        $ids = (new SegmentQueryEngine())->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                [
                    'field' => 'rel.deals.amount',
                    'operator' => 'gt',
                    'value' => 0,
                    'rel_filter' => ['field' => 'status', 'operator' => 'eq', 'value' => 'won'],
                ],
            ],
        ]))->pluck('id');

        $this->assertContains($contact->id, $ids);
        $this->assertNotContains($contact2->id, $ids);
    }

    public function test_count_gte_on_company(): void
    {
        $company = Company::factory()->create();
        $c1 = Contact::factory()->create();
        $c2 = Contact::factory()->create();
        $company->contacts()->attach([$c1->id, $c2->id], ['role' => 'employee', 'is_primary' => false]);

        $company2 = Company::factory()->create();

        $ids = (new SegmentQueryEngine())->buildQuery($this->segment('company', [
            'op' => 'AND', 'rules' => [
                ['field' => 'rel.contacts.first_name', 'operator' => 'count_gte', 'value' => 2],
            ],
        ]))->pluck('id');

        $this->assertContains($company->id, $ids);
        $this->assertNotContains($company2->id, $ids);
    }

    public function test_exists_operator(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create();
        $company->contacts()->attach($contact->id, ['role' => 'employee', 'is_primary' => false]);

        $emptyCompany = Company::factory()->create();

        $ids = (new SegmentQueryEngine())->buildQuery($this->segment('company', [
            'op' => 'AND', 'rules' => [
                ['field' => 'rel.contacts.id', 'operator' => 'exists'],
            ],
        ]))->pluck('id');

        $this->assertContains($company->id, $ids);
        $this->assertNotContains($emptyCompany->id, $ids);
    }

    // ── AND/OR composition ────────────────────────────────────────────────

    public function test_and_or_composition(): void
    {
        Contact::factory()->create(['lifecycle_stage' => 'customer', 'lead_status' => 'new']);
        Contact::factory()->create(['lifecycle_stage' => 'customer', 'lead_status' => 'open']);
        Contact::factory()->create(['lifecycle_stage' => 'lead', 'lead_status' => 'new']);

        $ids = (new SegmentQueryEngine())->buildQuery($this->segment('contact', [
            'op' => 'AND',
            'rules' => [
                ['field' => 'lifecycle_stage', 'operator' => 'eq', 'value' => 'customer'],
                [
                    'op' => 'OR', 'rules' => [
                        ['field' => 'lead_status', 'operator' => 'eq', 'value' => 'new'],
                        ['field' => 'lead_status', 'operator' => 'eq', 'value' => 'open'],
                    ],
                ],
            ],
        ]))->pluck('id');

        $this->assertCount(2, $ids);
    }

    // ── Missing coverage: not_contains, lt, pure AND/OR, empty rules ────

    public function test_not_contains_operator(): void
    {
        Contact::factory()->create(['email' => 'alice@acme.com']);
        Contact::factory()->create(['email' => 'bob@other.com']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'email', 'operator' => 'not_contains', 'value' => 'acme'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_lt_operator_on_amount(): void
    {
        $pipeline = Pipeline::query()->create(['name' => 'P', 'is_default' => true]);
        $stage = $pipeline->stages()->create(['name' => 'S', 'position' => 1, 'probability' => 50]);

        Deal::factory()->create(['amount' => 5000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);
        Deal::factory()->create(['amount' => 15000, 'pipeline_id' => $pipeline->id, 'pipeline_stage_id' => $stage->id]);

        $ids = $this->engine->buildQuery($this->segment('deal', [
            'op' => 'AND', 'rules' => [
                ['field' => 'amount', 'operator' => 'lt', 'value' => 10000],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_pure_and_two_conditions(): void
    {
        Contact::factory()->create(['first_name' => 'Alice', 'lifecycle_stage' => 'customer']);
        Contact::factory()->create(['first_name' => 'Alice', 'lifecycle_stage' => 'lead']);
        Contact::factory()->create(['first_name' => 'Bob', 'lifecycle_stage' => 'customer']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'first_name', 'operator' => 'eq', 'value' => 'Alice'],
                ['field' => 'lifecycle_stage', 'operator' => 'eq', 'value' => 'customer'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    public function test_or_group_combines_with_previous_sibling(): void
    {
        // Engine OR semantic: OR connects the group to previous sibling conditions
        // AND root: condition1 OR (group2_child1 AND group2_child2)
        Contact::factory()->create(['first_name' => 'Alice', 'lifecycle_stage' => 'customer']);
        Contact::factory()->create(['first_name' => 'Bob', 'lifecycle_stage' => 'lead']);
        Contact::factory()->create(['first_name' => 'Charlie', 'lifecycle_stage' => 'customer']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'first_name', 'operator' => 'eq', 'value' => 'Alice'],
                [
                    'op' => 'OR', 'rules' => [
                        ['field' => 'first_name', 'operator' => 'eq', 'value' => 'Charlie'],
                    ],
                ],
            ],
        ]))->pluck('id');

        // Alice (matches first rule) OR Charlie (matches OR group) = 2
        $this->assertCount(2, $ids);
    }

    public function test_empty_rules_returns_all(): void
    {
        Contact::factory()->count(3)->create();

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [],
        ]))->pluck('id');

        $this->assertCount(3, $ids);
    }

    public function test_eq_on_first_name_string(): void
    {
        Contact::factory()->create(['first_name' => 'Sophie']);
        Contact::factory()->create(['first_name' => 'Marc']);

        $ids = $this->engine->buildQuery($this->segment('contact', [
            'op' => 'AND', 'rules' => [
                ['field' => 'first_name', 'operator' => 'eq', 'value' => 'Sophie'],
            ],
        ]))->pluck('id');

        $this->assertCount(1, $ids);
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_validate_unknown_operator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid operator/');

        (new SegmentQueryEngine())->validateTree([
            'op' => 'AND', 'rules' => [
                ['field' => 'email', 'operator' => 'LIKE_INJECTION', 'value' => 'x'],
            ],
        ], 'contact');
    }

    public function test_validate_unknown_field_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown field/');

        (new SegmentQueryEngine())->validateTree([
            'op' => 'AND', 'rules' => [
                ['field' => 'DROP TABLE contacts', 'operator' => 'eq', 'value' => 'x'],
            ],
        ], 'contact');
    }

    public function test_validate_unknown_relation_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown relation/');

        (new SegmentQueryEngine())->validateTree([
            'op' => 'AND', 'rules' => [
                ['field' => 'rel.hackers.id', 'operator' => 'eq', 'value' => 1],
            ],
        ], 'contact');
    }

    public function test_available_fields_returns_core_and_custom(): void
    {
        CustomField::query()->create(['entity_type' => 'contact', 'key' => 'tier', 'label' => 'Tier', 'field_type' => 'select']);

        $fields = (new SegmentQueryEngine())->availableFields('contact');

        $keys = array_column($fields, 'key');
        $this->assertContains('email', $keys);
        $this->assertContains('lifecycle_stage', $keys);
        $this->assertContains('custom.tier', $keys);
        $this->assertContains('rel.deals.amount', $keys);
    }
}
