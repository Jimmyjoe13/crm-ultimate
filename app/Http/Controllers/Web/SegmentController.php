<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Services\SegmentQueryEngine;
use App\Support\CustomFieldRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SegmentController extends Controller
{
    public function __construct(private readonly SegmentQueryEngine $engine) {}

    public function index()
    {
        $segments = Segment::with('creator')->orderByDesc('updated_at')->get();
        return view('pages.segments.index', compact('segments'));
    }

    public function create()
    {
        $fieldsByEntity = $this->loadAllFields();
        return view('pages.segments.create', ['segment' => null, 'fieldsByEntity' => $fieldsByEntity]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_type' => ['required', 'in:contact,company,deal'],
            'rules'       => ['required', 'json'],
        ]);

        $rules = json_decode($data['rules'], true);

        try {
            $this->engine->validateTree($rules, $data['entity_type']);
        } catch (\Throwable $e) {
            return back()->withErrors(['rules' => $e->getMessage()])->withInput();
        }

        $segment = Segment::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'entity_type' => $data['entity_type'],
            'rules'       => $rules,
            'created_by'  => auth()->id(),
        ]);

        // Compute initial count
        try {
            $count = $this->engine->buildQuery($segment)->count();
            $segment->update(['last_count' => $count, 'last_computed_at' => now()]);
        } catch (\Throwable) {
        }

        return redirect('/segments/' . $segment->id)
            ->with('flash_toast', ['message' => "Segment « {$segment->name} » créé.", 'type' => 'success']);
    }

    public function show(Segment $segment, Request $request)
    {
        $page = (int) $request->get('page', 1);
        $perPage = 25;

        try {
            $membersQuery = $this->engine->buildQuery($segment);
            $total = $membersQuery->count();
            $withRelations = match($segment->entity_type) {
                'deal'    => ['stage'],
                'contact' => ['companies'],
                'company' => ['contacts'],
                default   => [],
            };
            $members = $membersQuery->with($withRelations)->forPage($page, $perPage)->get();

            // Refresh count if stale or first load
            if (! $segment->last_computed_at || $segment->last_computed_at->lt(now()->subMinutes(10))) {
                $segment->update(['last_count' => $total, 'last_computed_at' => now()]);
            }
        } catch (\Throwable $e) {
            $members = collect();
            $total   = 0;
        }

        $lastPage = (int) ceil($total / $perPage);

        return view('pages.segments.show', compact('segment', 'members', 'total', 'page', 'perPage', 'lastPage'));
    }

    public function edit(Segment $segment)
    {
        $fieldsByEntity = $this->loadAllFields();
        return view('pages.segments.create', compact('segment', 'fieldsByEntity'));
    }

    public function preview(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:contact,company,deal'],
            'rules'       => ['required', 'array'],
        ]);

        try {
            $this->engine->validateTree($data['rules'], $data['entity_type']);
            $segment = new \App\Models\Segment(['entity_type' => $data['entity_type'], 'rules' => $data['rules']]);
            $query   = $this->engine->buildQuery($segment);
            $count   = $query->count();
            $sample  = (clone $query)->limit(20)->get();
            return response()->json(['count' => $count, 'sample' => $sample]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function loadAllFields(): array
    {
        $result = [];
        foreach (['contact', 'company', 'deal'] as $et) {
            try {
                $result[$et] = $this->engine->availableFields($et);
            } catch (\Throwable) {
                $result[$et] = [];
            }
        }
        return $result;
    }

    public function update(Request $request, Segment $segment)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_type' => ['required', 'in:contact,company,deal'],
            'rules'       => ['required', 'json'],
        ]);

        $rules = json_decode($data['rules'], true);

        try {
            $this->engine->validateTree($rules, $data['entity_type']);
        } catch (\Throwable $e) {
            return back()->withErrors(['rules' => $e->getMessage()])->withInput();
        }

        $segment->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'entity_type' => $data['entity_type'],
            'rules'       => $rules,
        ]);

        return redirect('/segments/' . $segment->id)
            ->with('flash_toast', ['message' => "Segment « {$segment->name} » mis à jour.", 'type' => 'success']);
    }

    public function export(Segment $segment): StreamedResponse
    {
        $slug         = Str::slug($segment->name);
        $date         = now()->format('Y-m-d');
        $filename     = "segment-{$slug}-{$date}.csv";
        $entityType   = $segment->entity_type;
        $customFields = CustomFieldRenderer::forEntity($entityType);

        $relations = match($entityType) {
            'contact' => ['companies:id,name', 'owner:id,name'],
            'company' => ['owner:id,name'],
            'deal'    => ['stage:id,name', 'companies:id,name', 'contacts:id,first_name,last_name', 'owner:id,name'],
            default   => [],
        };

        $standardHeaders = match($entityType) {
            'contact' => ['id', 'prénom', 'nom', 'email', 'téléphone', 'poste', 'étape_lifecycle', 'statut_lead', 'propriétaire', 'entreprises', 'créé_le'],
            'company' => ['id', 'nom', 'domaine', 'secteur', 'téléphone', 'site_web', 'ville', 'pays', 'étape_lifecycle', 'statut_lead', 'propriétaire', 'créé_le'],
            'deal'    => ['id', 'nom', 'montant', 'devise', 'statut', 'étape', 'date_clôture', 'propriétaire', 'entreprises', 'contacts', 'créé_le'],
            default   => ['id', 'créé_le'],
        };

        $headers = array_merge($standardHeaders, $customFields->pluck('label')->toArray());

        return response()->streamDownload(function () use ($segment, $entityType, $relations, $headers, $customFields) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            $this->engine->buildQuery($segment)
                ->with($relations)
                ->chunk(200, function ($members) use ($out, $entityType, $customFields) {
                    foreach ($members as $m) {
                        $row = match($entityType) {
                            'contact' => [
                                $m->id,
                                $m->first_name,
                                $m->last_name,
                                $m->email,
                                $m->phone,
                                $m->job_title,
                                $m->lifecycle_stage,
                                $m->lead_status,
                                $m->owner?->name,
                                $m->companies->pluck('name')->implode(', '),
                                $m->created_at?->format('d/m/Y'),
                            ],
                            'company' => [
                                $m->id,
                                $m->name,
                                $m->domain,
                                $m->industry,
                                $m->phone,
                                $m->website,
                                $m->city,
                                $m->country,
                                $m->lifecycle_stage,
                                $m->lead_status,
                                $m->owner?->name,
                                $m->created_at?->format('d/m/Y'),
                            ],
                            'deal' => [
                                $m->id,
                                $m->name,
                                $m->amount,
                                $m->currency,
                                $m->status,
                                $m->stage?->name,
                                $m->close_date?->format('d/m/Y'),
                                $m->owner?->name,
                                $m->companies->pluck('name')->implode(', '),
                                $m->contacts->map(fn($c) => $c->first_name . ' ' . $c->last_name)->implode(', '),
                                $m->created_at?->format('d/m/Y'),
                            ],
                            default => [$m->id, $m->created_at?->format('d/m/Y')],
                        };

                        foreach ($customFields as $field) {
                            $raw   = $m->custom_values[$field->key] ?? null;
                            $display = CustomFieldRenderer::displayValue($field, $raw);
                            $row[] = $display === '—' ? '' : $display;
                        }

                        fputcsv($out, $row);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function destroy(Segment $segment)
    {
        $segment->delete();
        return redirect('/segments')->with('success', "Segment supprimé.");
    }
}
