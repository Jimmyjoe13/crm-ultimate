<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Deal;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SegmentQueryEngine
{
    private const ENTITY_MODELS = [
        'contact' => Contact::class,
        'company' => Company::class,
        'deal'    => Deal::class,
    ];

    /** Core columns allowed per entity (prevents injection via field name). */
    private const CORE_FIELDS = [
        'contact' => [
            'id', 'first_name', 'last_name', 'email', 'phone', 'job_title',
            'lifecycle_stage', 'lead_status', 'owner_id', 'created_at', 'updated_at',
        ],
        'company' => [
            'id', 'name', 'domain', 'industry', 'phone', 'website', 'city', 'country',
            'lifecycle_stage', 'lead_status', 'owner_id', 'created_at', 'updated_at',
        ],
        'deal' => [
            'id', 'name', 'amount', 'currency', 'close_date', 'status',
            'pipeline_id', 'pipeline_stage_id', 'owner_id', 'created_at', 'updated_at',
        ],
    ];

    /** Allowed relations per entity (name → related entity type for field validation). */
    private const RELATIONS = [
        'contact' => ['companies' => 'company', 'deals' => 'deal'],
        'company' => ['contacts' => 'contact', 'deals' => 'deal'],
        'deal'    => ['contacts' => 'contact', 'companies' => 'company'],
    ];

    private const VALID_OPERATORS = [
        'eq', 'neq', 'in', 'not_in', 'contains', 'not_contains', 'starts_with', 'ends_with',
        'gt', 'gte', 'lt', 'lte', 'between',
        'is_null', 'is_not_null',
        'days_ago_lt', 'days_ago_gt',
        'exists', 'not_exists', 'count_gte', 'count_lt',
    ];

    /** @var array<string, string[]> — custom field keys loaded per entity_type. */
    private array $customFieldKeys = [];

    public function buildQuery(Segment $segment): Builder
    {
        $modelClass = self::ENTITY_MODELS[$segment->entity_type]
            ?? throw new InvalidArgumentException("Unknown entity_type: {$segment->entity_type}");

        $query = $modelClass::query();

        if (! empty($segment->rules)) {
            $this->applyNode($query, $segment->rules, $segment->entity_type);
        }

        return $query;
    }

    /**
     * Validate that a rules tree is well-formed and uses only safe fields/operators.
     * Returns true or throws InvalidArgumentException with a descriptive message.
     */
    public function validateTree(array $tree, string $entityType): true
    {
        if (! array_key_exists($entityType, self::ENTITY_MODELS)) {
            throw new InvalidArgumentException("Unknown entity_type: $entityType");
        }

        $this->validateNode($tree, $entityType);

        return true;
    }

    /**
     * Return the list of fields available for a given entity_type.
     * Used by GET /segments/fields/{entityType}.
     *
     * @return array<array{key: string, label: string, type: string, source: string, operators: string[]}>
     */
    public function availableFields(string $entityType): array
    {
        if (! array_key_exists($entityType, self::ENTITY_MODELS)) {
            throw new InvalidArgumentException("Unknown entity_type: $entityType");
        }

        $fields = [];

        // Core fields
        $coreLabels = $this->coreFieldLabels($entityType);
        foreach (self::CORE_FIELDS[$entityType] as $col) {
            $type = $this->inferCoreType($entityType, $col);
            $fields[] = [
                'key'       => $col,
                'label'     => $coreLabels[$col] ?? $col,
                'type'      => $type,
                'source'    => 'core',
                'operators' => $this->operatorsForType($type),
            ];
        }

        // Custom fields from DB
        foreach ($this->loadCustomKeys($entityType, true) as $cf) {
            $fields[] = [
                'key'       => "custom.{$cf['key']}",
                'label'     => $cf['label'],
                'type'      => $cf['field_type'],
                'source'    => 'custom',
                'operators' => $this->operatorsForType($cf['field_type']),
            ];
        }

        // Relational fields
        foreach (self::RELATIONS[$entityType] as $relation => $relEntity) {
            foreach (self::CORE_FIELDS[$relEntity] as $col) {
                if (in_array($col, ['id', 'created_at', 'updated_at', 'owner_id'], true)) {
                    continue;
                }
                $type = $this->inferCoreType($relEntity, $col);
                $relLabels = $this->coreFieldLabels($relEntity);
                $fields[] = [
                    'key'       => "rel.{$relation}.{$col}",
                    'label'     => ucfirst($relation) . ' › ' . ($relLabels[$col] ?? $col),
                    'type'      => $type,
                    'source'    => 'rel',
                    'operators' => array_merge(
                        $this->operatorsForType($type),
                        ['exists', 'not_exists', 'count_gte', 'count_lt']
                    ),
                ];
            }
        }

        return $fields;
    }

    // ── Tree traversal ──────────────────────────────────────────────────────

    private function applyNode(Builder $query, array $node, string $entityType): void
    {
        if (isset($node['op'])) {
            // Group node: { op: 'AND'|'OR', rules: [...] }
            // OR group: children are OR'd together inside a WHERE () wrapper.
            // AND group: children are AND'd together inside a WHERE () wrapper.
            $isOrGroup = strtoupper($node['op'] ?? 'AND') === 'OR';
            $query->where(function (Builder $sub) use ($node, $entityType, $isOrGroup): void {
                $first = true;
                foreach ($node['rules'] ?? [] as $child) {
                    if ($isOrGroup && ! $first) {
                        // Subsequent children in an OR group are connected with OR
                        $sub->orWhere(function (Builder $inner) use ($child, $entityType): void {
                            $this->applyNode($inner, $child, $entityType);
                        });
                    } else {
                        $this->applyNode($sub, $child, $entityType);
                    }
                    $first = false;
                }
            });
        } else {
            // Leaf node: { field, operator, value, rel_filter? }
            $this->applyLeaf(
                $query,
                $node['field'] ?? '',
                $node['operator'] ?? 'eq',
                $node['value'] ?? null,
                $node['rel_filter'] ?? null,
                $entityType
            );
        }
    }

    private function applyLeaf(
        Builder $query,
        string $field,
        string $operator,
        mixed $value,
        ?array $relFilter,
        string $entityType
    ): void {
        if (str_starts_with($field, 'rel.')) {
            $this->applyRelationalLeaf($query, $field, $operator, $value, $relFilter, $entityType);
        } elseif (str_starts_with($field, 'custom.')) {
            $this->applyCustomLeaf($query, $field, $operator, $value, $entityType);
        } else {
            $this->applyCoreLeaf($query, $field, $operator, $value, $entityType);
        }
    }

    private function applyCoreLeaf(Builder $query, string $field, string $operator, mixed $value, string $entityType): void
    {
        if (! in_array($field, self::CORE_FIELDS[$entityType], true)) {
            throw new InvalidArgumentException("Unknown field '$field' for entity '$entityType'");
        }

        $this->applyScalarOperator($query, $field, $operator, $value);
    }

    private function applyCustomLeaf(Builder $query, string $field, string $operator, mixed $value, string $entityType): void
    {
        // field = "custom.some_key"
        $key = substr($field, 7);
        $allowedKeys = $this->loadCustomKeys($entityType);

        if (! in_array($key, $allowedKeys, true)) {
            throw new InvalidArgumentException("Unknown custom field '$key' for entity '$entityType'");
        }

        // Extract from JSONB: custom_values->>'key'
        $expr = DB::raw("(custom_values->>'$key')");

        $this->applyScalarOperator($query, $expr, $operator, $value);
    }

    private function applyRelationalLeaf(
        Builder $query,
        string $field,
        string $operator,
        mixed $value,
        ?array $relFilter,
        string $entityType
    ): void {
        // field = "rel.contacts.lifecycle_stage" → relation=contacts, relField=lifecycle_stage
        $parts = explode('.', $field, 3);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException("Invalid relational field format: $field");
        }

        [, $relation, $relField] = $parts;

        $allowedRelations = self::RELATIONS[$entityType] ?? [];
        if (! array_key_exists($relation, $allowedRelations)) {
            throw new InvalidArgumentException("Unknown relation '$relation' for entity '$entityType'");
        }

        $relEntityType = $allowedRelations[$relation];

        if (! in_array($relField, self::CORE_FIELDS[$relEntityType], true)) {
            throw new InvalidArgumentException("Unknown field '$relField' for relation '$relation'");
        }

        if ($operator === 'exists') {
            $query->whereHas($relation, function (Builder $sub) use ($relField, $relFilter, $relEntityType): void {
                if ($relFilter) {
                    $this->applyLeaf($sub, $relFilter['field'], $relFilter['operator'], $relFilter['value'] ?? null, null, $relEntityType);
                }
            });
        } elseif ($operator === 'not_exists') {
            $query->whereDoesntHave($relation, function (Builder $sub) use ($relFilter, $relEntityType): void {
                if ($relFilter) {
                    $this->applyLeaf($sub, $relFilter['field'], $relFilter['operator'], $relFilter['value'] ?? null, null, $relEntityType);
                }
            });
        } elseif ($operator === 'count_gte') {
            $query->whereHas($relation, null, '>=', (int) $value);
        } elseif ($operator === 'count_lt') {
            $query->whereHas($relation, null, '<', (int) $value);
        } else {
            // Filter on a specific column in the related table
            $query->whereHas($relation, function (Builder $sub) use ($relField, $operator, $value, $relFilter, $relEntityType): void {
                $this->applyCoreLeaf($sub, $relField, $operator, $value, $relEntityType);
                if ($relFilter) {
                    $this->applyLeaf($sub, $relFilter['field'], $relFilter['operator'], $relFilter['value'] ?? null, null, $relEntityType);
                }
            });
        }
    }

    /** Apply a scalar operator to a column or raw expression. */
    private function applyScalarOperator(Builder $query, mixed $column, string $operator, mixed $value): void
    {
        match ($operator) {
            'eq'           => $query->where($column, '=', $value),
            'neq'          => $query->where($column, '!=', $value),
            'in'           => $query->whereIn($column, (array) $value),
            'not_in'       => $query->whereNotIn($column, (array) $value),
            'contains'     => $query->where($column, 'ilike', "%$value%"),
            'not_contains' => $query->where($column, 'not ilike', "%$value%"),
            'starts_with'  => $query->where($column, 'ilike', "$value%"),
            'ends_with'    => $query->where($column, 'ilike', "%$value"),
            'gt'           => $query->where($column, '>', $value),
            'gte'          => $query->where($column, '>=', $value),
            'lt'           => $query->where($column, '<', $value),
            'lte'          => $query->where($column, '<=', $value),
            'between'      => $query->whereBetween($column, [$value[0], $value[1]]),
            'is_null'      => $query->whereNull($column),
            'is_not_null'  => $query->whereNotNull($column),
            'days_ago_lt'  => $query->where($column, '>=', now()->subDays((int) $value)->startOfDay()),
            'days_ago_gt'  => $query->where($column, '<', now()->subDays((int) $value)->startOfDay()),
            default        => throw new InvalidArgumentException("Unknown operator: $operator"),
        };
    }

    // ── Validation ──────────────────────────────────────────────────────────

    private function validateNode(array $node, string $entityType): void
    {
        if (isset($node['op'])) {
            if (! in_array(strtoupper($node['op']), ['AND', 'OR'], true)) {
                throw new InvalidArgumentException("Invalid group operator: {$node['op']}");
            }
            foreach ($node['rules'] ?? [] as $child) {
                $this->validateNode($child, $entityType);
            }
        } else {
            $field    = $node['field'] ?? null;
            $operator = $node['operator'] ?? null;

            if (! $field) {
                throw new InvalidArgumentException('Rule is missing field');
            }
            if (! $operator || ! in_array($operator, self::VALID_OPERATORS, true)) {
                throw new InvalidArgumentException("Invalid operator: $operator");
            }

            // Validate field accessibility
            if (str_starts_with($field, 'rel.')) {
                $parts = explode('.', $field, 3);
                if (count($parts) !== 3) {
                    throw new InvalidArgumentException("Invalid relational field: $field");
                }
                [, $relation, $relField] = $parts;
                $allowed = self::RELATIONS[$entityType] ?? [];
                if (! array_key_exists($relation, $allowed)) {
                    throw new InvalidArgumentException("Unknown relation: $relation");
                }
                $relEntityType = $allowed[$relation];
                if (! in_array($relField, self::CORE_FIELDS[$relEntityType], true)) {
                    throw new InvalidArgumentException("Unknown field '$relField' in relation '$relation'");
                }
            } elseif (str_starts_with($field, 'custom.')) {
                $key = substr($field, 7);
                if (! in_array($key, $this->loadCustomKeys($entityType), true)) {
                    throw new InvalidArgumentException("Unknown custom field: $key");
                }
            } else {
                if (! in_array($field, self::CORE_FIELDS[$entityType], true)) {
                    throw new InvalidArgumentException("Unknown field '$field' for entity '$entityType'");
                }
            }
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Load custom field keys for an entity type (cached per engine instance).
     *
     * @param bool $full  When true, return full CF objects instead of just keys.
     * @return string[]|array<array{key:string,label:string,field_type:string}>
     */
    private function loadCustomKeys(string $entityType, bool $full = false): array
    {
        if (! isset($this->customFieldKeys[$entityType])) {
            $this->customFieldKeys[$entityType] = CustomField::query()
                ->where('entity_type', $entityType)
                ->get(['key', 'label', 'field_type'])
                ->toArray();
        }

        if ($full) {
            return $this->customFieldKeys[$entityType];
        }

        return array_column($this->customFieldKeys[$entityType], 'key');
    }

    private function inferCoreType(string $entityType, string $col): string
    {
        $dateFields = ['created_at', 'updated_at', 'close_date'];
        $numberFields = ['id', 'amount', 'pipeline_id', 'pipeline_stage_id', 'owner_id'];

        if (in_array($col, $dateFields, true)) {
            return 'date';
        }
        if (in_array($col, $numberFields, true)) {
            return 'number';
        }
        // select-like fields with known enums
        if (in_array($col, ['lifecycle_stage', 'lead_status', 'status', 'currency'], true)) {
            return 'select';
        }

        return 'text';
    }

    private function operatorsForType(string $type): array
    {
        return match ($type) {
            'number'  => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_null', 'is_not_null'],
            'date'    => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'days_ago_lt', 'days_ago_gt', 'is_null', 'is_not_null'],
            'boolean' => ['eq', 'neq', 'is_null', 'is_not_null'],
            'select'  => ['eq', 'neq', 'in', 'not_in', 'is_null', 'is_not_null'],
            default   => ['eq', 'neq', 'contains', 'not_contains', 'starts_with', 'ends_with', 'in', 'not_in', 'is_null', 'is_not_null'],
        };
    }

    private function coreFieldLabels(string $entityType): array
    {
        return match ($entityType) {
            'contact' => [
                'id' => 'ID', 'first_name' => 'Prénom', 'last_name' => 'Nom',
                'email' => 'Email', 'phone' => 'Téléphone', 'job_title' => 'Poste',
                'lifecycle_stage' => 'Lifecycle stage', 'lead_status' => 'Lead status',
                'owner_id' => 'Responsable', 'created_at' => 'Créé le', 'updated_at' => 'Modifié le',
            ],
            'company' => [
                'id' => 'ID', 'name' => 'Nom', 'domain' => 'Domaine', 'industry' => 'Secteur',
                'phone' => 'Téléphone', 'website' => 'Site web', 'city' => 'Ville', 'country' => 'Pays',
                'lifecycle_stage' => 'Lifecycle stage', 'lead_status' => 'Lead status',
                'owner_id' => 'Responsable', 'created_at' => 'Créé le', 'updated_at' => 'Modifié le',
            ],
            'deal' => [
                'id' => 'ID', 'name' => 'Nom', 'amount' => 'Montant', 'currency' => 'Devise',
                'close_date' => 'Date de clôture', 'status' => 'Statut',
                'pipeline_id' => 'Pipeline', 'pipeline_stage_id' => 'Étape',
                'owner_id' => 'Responsable', 'created_at' => 'Créé le', 'updated_at' => 'Modifié le',
            ],
            default => [],
        };
    }
}
