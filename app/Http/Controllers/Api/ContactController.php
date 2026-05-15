<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    use CrudActions;

    protected string $modelClass = Contact::class;

    protected array $searchable = ['first_name', 'last_name', 'email', 'phone', 'job_title'];

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'first_name' => [$required, 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'custom_values' => ['array'],
        ];
    }

    public function show(int $id): JsonResponse
    {
        $contact = Contact::query()
            ->with(['company', 'owner'])
            ->findOrFail($id);

        return response()->json([
            'data' => $contact,
            'activities' => Activity::query()
                ->where('subject_type', Contact::class)
                ->where('subject_id', $contact->id)
                ->latest()
                ->get(),
            'audit_logs' => AuditLog::query()
                ->where('auditable_type', Contact::class)
                ->where('auditable_id', $contact->id)
                ->latest('created_at')
                ->limit(25)
                ->get(),
        ]);
    }
}
