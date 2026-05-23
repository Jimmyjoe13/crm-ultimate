<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $activities = Activity::with(['subject'])
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        return view('pages.activities.index', compact('activities'));
    }

    public function toggleDone(Request $request, Activity $activity): JsonResponse
    {
        $user = auth()->user();

        if ($activity->owner_id !== $user->id && !in_array($user->role, ['admin', 'manager'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($activity->status === 'completed') {
            $activity->update(['status' => 'open', 'completed_at' => null]);
        } else {
            $activity->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return response()->json([
            'status'       => $activity->status,
            'completed_at' => $activity->completed_at?->toIso8601String(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'type'         => ['required', 'in:note,task,call,email'],
            'title'        => ['required', 'string', 'max:255'],
            'body'         => ['nullable', 'string'],
            'subject_type' => ['required', 'in:contact,company,deal'],
            'subject_id'   => ['required', 'integer'],
        ]);

        $morphMap = [
            'contact' => \App\Models\Contact::class,
            'company' => \App\Models\Company::class,
            'deal'    => \App\Models\Deal::class,
        ];

        Activity::create([
            'type'         => $data['type'],
            'title'        => $data['title'],
            'body'         => $data['body'] ?? null,
            'status'       => 'open',
            'subject_type' => $morphMap[$data['subject_type']],
            'subject_id'   => $data['subject_id'],
            'owner_id'     => auth()->id(),
        ]);

        return back()->with('flash_toast', ['message' => 'Activité ajoutée.', 'type' => 'success']);
    }

    public function destroy(Activity $activity): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();

        if ($activity->owner_id !== $user->id && !in_array($user->role, ['admin', 'manager'])) {
            abort(403, 'Forbidden.');
        }

        $activity->delete();

        return back()->with('flash_toast', ['message' => 'Activité supprimée.', 'type' => 'success']);
    }
}

