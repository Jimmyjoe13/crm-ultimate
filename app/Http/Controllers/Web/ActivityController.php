<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
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
}
