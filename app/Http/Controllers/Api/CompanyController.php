<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    use CrudActions;

    protected string $modelClass = Company::class;

    protected array $searchable = ['name', 'domain', 'industry', 'city', 'country'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'custom_values' => ['array'],
        ];
    }

    public function show(int $id): JsonResponse
    {
        $company = Company::query()
            ->with(['contacts', 'deals', 'owner'])
            ->findOrFail($id);

        return response()->json([
            'data' => $company,
            'activities' => Activity::query()
                ->where('subject_type', Company::class)
                ->where('subject_id', $company->id)
                ->latest()
                ->get(),
            'audit_logs' => AuditLog::query()
                ->where('auditable_type', Company::class)
                ->where('auditable_id', $company->id)
                ->latest('created_at')
                ->limit(25)
                ->get(),
        ]);
    }
}
