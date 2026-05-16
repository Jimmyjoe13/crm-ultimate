<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $sort   = $request->get('sort', 'close_date');
        $search = $request->get('search');

        $base = Deal::with('stage', 'companies', 'contacts', 'owner')->where('status', 'open');

        $allCount = (clone $base)->count();
        $hotCount = 0;
        $total    = (clone $base)->sum('amount');

        $query = clone $base;

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $query = match($sort) {
            'amount' => $query->orderByDesc('amount'),
            default  => $query->orderBy('close_date'),
        };

        $deals = $query->paginate(20)->withQueryString();

        $stages = PipelineStage::orderBy('position')->get();

        return view('pages.deals.index', compact('deals', 'filter', 'sort', 'search', 'allCount', 'total', 'stages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'close_date'        => ['nullable', 'date'],
        ]);

        $deal = Deal::create([
            'name'              => $data['name'],
            'amount'            => $data['amount'],
            'pipeline_stage_id' => $data['pipeline_stage_id'],
            'close_date'        => $data['close_date'] ?? null,
            'status'            => 'open',
            'owner_id'          => auth()->id(),
        ]);

        return redirect()->route('deals.index')->with('success', "Deal « {$deal->name} » créé.");
    }

    public function show(Deal $deal)
    {
        $deal->load('stage', 'companies', 'contacts', 'owner');

        $stages = PipelineStage::orderBy('position')
            ->where('is_won', false)
            ->where('is_lost', false)
            ->get();

        $activities = Activity::where('subject_type', Deal::class)
            ->where('subject_id', $deal->id)
            ->with('owner')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Background deals list (dimmed behind drawer)
        $bgDeals = Deal::with('stage', 'companies', 'owner')
            ->where('status', 'open')
            ->orderBy('close_date')
            ->limit(10)
            ->get();

        return view('pages.deals.show', compact('deal', 'stages', 'activities', 'bgDeals'));
    }

    public function markWon(Deal $deal)
    {
        $wonStage = PipelineStage::where('is_won', true)->first();
        $deal->update([
            'status'            => 'won',
            'pipeline_stage_id' => $wonStage?->id ?? $deal->pipeline_stage_id,
        ]);

        return redirect()->route('deals.index')->with('success', "Deal « {$deal->name} » marqué gagné ✓");
    }

    public function markLost(Deal $deal)
    {
        $lostStage = PipelineStage::where('is_lost', true)->first();
        $deal->update([
            'status'            => 'lost',
            'pipeline_stage_id' => $lostStage?->id ?? $deal->pipeline_stage_id,
        ]);

        return redirect()->route('deals.index')->with('success', "Deal « {$deal->name} » marqué perdu.");
    }
}
