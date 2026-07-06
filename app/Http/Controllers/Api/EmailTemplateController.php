<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CrudActions;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    use CrudActions;

    protected string $modelClass = EmailTemplate::class;

    protected array $searchable = ['name', 'subject', 'category'];

    /**
     * Override du store de CrudActions : owner_id auto-assigné au créateur
     * (colonne non-nullable) et is_shared réservé à admin/manager.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules('store'));
        $data['owner_id'] = $data['owner_id'] ?? $request->user()->id;
        $data = $this->authorizeOwnerAssignment($request, $data);

        $canShare = $request->user()?->isAdmin() || $request->user()?->isManager();
        $data['is_shared'] = $canShare ? (bool) ($data['is_shared'] ?? false) : false;

        $record = EmailTemplate::create($data);

        return response()->json(['data' => $record->refresh()], 201);
    }

    /**
     * Rendu d'un modèle avec un jeu de variables fourni directement.
     * Pratique pour les intégrations (séquences, automatisations).
     */
    public function render(Request $request, int $id, TemplateRenderer $renderer): JsonResponse
    {
        $template = $this->scopedQuery($request)->findOrFail($id);

        $vars = $request->validate([
            'variables' => ['array'],
            'variables.*' => ['nullable'],
        ])['variables'] ?? [];

        return response()->json([
            'subject' => $renderer->render($template->subject ?? '', $vars),
            'body' => $renderer->render($template->body ?? '', $vars),
        ]);
    }

    protected function rules(string $operation): array
    {
        $required = $operation === 'store' ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_shared' => ['nullable', 'boolean'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }
}
