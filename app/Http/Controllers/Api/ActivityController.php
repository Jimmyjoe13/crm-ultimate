<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    use CrudActions;

    protected string $modelClass = Activity::class;

    protected array $searchable = ['title', 'body', 'type', 'status'];

    public function due(Request $request): JsonResponse
    {
        $query = Activity::query()
            ->where('type', 'task')
            ->where('status', 'open')
            ->where('owner_id', Auth::id())
            ->orderBy('due_at');

        if ($request->boolean('overdue_only')) {
            $query->where('due_at', '<', now())->whereNotNull('due_at');
        }

        return response()->json(['data' => $query->get()]);
    }

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'type' => [$required, 'in:note,task,call,email'],
            'title' => [$required, 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'status' => ['in:open,done,cancelled'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'subject_type' => ['nullable', 'string', 'in:App\\Models\\Company,App\\Models\\Contact,App\\Models\\Deal'],
            'subject_id' => ['nullable', 'integer'],
            'owner_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
