<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesOwnerAccess;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\EmailTemplate;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    use AuthorizesOwnerAccess;

    public function __construct(private readonly TemplateRenderer $renderer) {}

    public function index(Request $request)
    {
        $templates = EmailTemplate::with('owner:id,name')
            ->accessibleTo($request->user())
            ->orderByDesc('is_shared')
            ->orderBy('name')
            ->get();

        $variables = $this->renderer->availableVariables();

        return view('pages.email-templates.index', compact('templates', 'variables'));
    }

    /**
     * Liste légère (JSON) pour le sélecteur dans le modal de rédaction d'email.
     */
    public function options(Request $request): JsonResponse
    {
        $templates = EmailTemplate::accessibleTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'subject', 'body']);

        return response()->json($templates);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['owner_id'] = $request->user()->id;

        EmailTemplate::create($data);

        return back()->with('flash_toast', ['message' => 'Modèle créé.', 'type' => 'success']);
    }

    public function update(Request $request, EmailTemplate $template)
    {
        // Cloisonnement : seul le propriétaire (ou admin/manager dans son périmètre) peut éditer.
        $this->ensureVisible($template, $request->user());

        $template->update($this->validateData($request));

        return back()->with('flash_toast', ['message' => 'Modèle mis à jour.', 'type' => 'success']);
    }

    public function destroy(Request $request, EmailTemplate $template)
    {
        $this->ensureVisible($template, $request->user());
        $template->delete();

        return back()->with('flash_toast', ['message' => 'Modèle supprimé.', 'type' => 'success']);
    }

    /**
     * Rend un modèle pour un contact ou un deal donné (substitution des variables).
     * Le template doit être accessible (possédé ou partagé) ; l'entité est cloisonnée par owner.
     */
    public function render(Request $request, EmailTemplate $template): JsonResponse
    {
        // Le modèle doit être dans le périmètre accessible (possédé ou partagé).
        $accessible = EmailTemplate::accessibleTo($request->user())->whereKey($template->id)->exists();
        if (! $accessible) {
            abort(404);
        }

        $validated = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'deal_id' => ['nullable', 'integer'],
        ]);

        $vars = [];

        if (! empty($validated['contact_id'])) {
            $contact = Contact::with(['companies', 'owner'])
                ->visibleTo($request->user())
                ->findOrFail($validated['contact_id']);
            $vars = $this->renderer->contactVars($contact);
        } elseif (! empty($validated['deal_id'])) {
            $deal = Deal::with(['companies', 'contacts', 'stage', 'owner'])
                ->visibleTo($request->user())
                ->findOrFail($validated['deal_id']);
            $vars = $this->renderer->dealVars($deal);
        }

        return response()->json([
            'subject' => $this->renderer->render($template->subject ?? '', $vars),
            'body' => $this->renderer->render($template->body ?? '', $vars),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        // Seuls admin/manager peuvent publier un modèle partagé à toute l'équipe.
        $canShare = in_array($request->user()?->role, ['admin', 'manager'], true);
        $data['is_shared'] = $canShare && $request->boolean('is_shared');

        $data['subject'] = $data['subject'] ?? '';
        $data['body'] = $data['body'] ?? '';

        return $data;
    }
}
