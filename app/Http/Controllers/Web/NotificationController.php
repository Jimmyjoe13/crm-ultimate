<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markEmeliaRepliesSeen(Request $request)
    {
        $request->user()->update(['emelia_replies_last_seen' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
