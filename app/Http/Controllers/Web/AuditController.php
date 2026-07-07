<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->with('user')->orderBy('id', 'desc');

        // Filtrage par agent
        if ($request->filled('user_id')) {
            if ($request->user_id === 'system') {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', $request->user_id);
            }
        }

        // Filtrage par type d'action
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        // Filtrage par entité (Contact, Company, Deal)
        if ($request->filled('entity_type')) {
            $type = match ($request->entity_type) {
                'contact' => \App\Models\Contact::class,
                'company' => \App\Models\Company::class,
                'deal' => \App\Models\Deal::class,
                default => null,
            };
            if ($type) {
                $query->where('auditable_type', $type);
            }
        }

        $auditLogs = $query->paginate(40)->withQueryString();
        $users = User::orderBy('name')->get();

        return view('pages.audit.index', compact('auditLogs', 'users'));
    }
}
