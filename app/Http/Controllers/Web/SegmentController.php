<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Segment;
use App\Services\SegmentQueryEngine;
use Illuminate\Http\Request;

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
            ->with('success', "Segment « {$segment->name} » créé.");
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
            ->with('success', "Segment « {$segment->name} » mis à jour.");
    }

    public function destroy(Segment $segment)
    {
        $segment->delete();
        return redirect('/segments')->with('success', "Segment supprimé.");
    }
}
