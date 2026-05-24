<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RunConsoleCommandJob;
use App\Models\ConsoleRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ConsoleController extends Controller
{
    // Whitelist stricte — aucune commande libre n'est possible
    private const COMMANDS = [
        'emelia-sync'    => [
            'cmd'   => 'emelia:sync-all-campaigns',
            'args'  => ['--only-linked' => true],
            'label' => 'Sync Emelia — campagnes liées',
            'desc'  => 'Synchronise tous les contacts CRM vers leurs campagnes Emelia liées.',
            'async' => true,
            'icon'  => 'mail',
        ],
        'ai-score'       => [
            'cmd'   => 'ai:score-contacts',
            'args'  => ['--limit' => '50'],
            'label' => 'Score IA contacts (50)',
            'desc'  => 'Calcule le score IA des 50 prochains contacts.',
            'async' => true,
            'icon'  => 'star',
        ],
        'ai-precompute'  => [
            'cmd'   => 'ai:precompute',
            'args'  => ['--limit' => '50'],
            'label' => 'Préchauffer cache IA (50 deals)',
            'desc'  => 'Pré-calcule les insights IA des 50 deals actifs les plus importants.',
            'async' => true,
            'icon'  => 'zap',
        ],
        'queue-restart'  => [
            'cmd'   => 'queue:restart',
            'args'  => [],
            'label' => 'Redémarrer les workers',
            'desc'  => 'Envoie le signal de redémarrage aux workers de queue.',
            'async' => false,
            'icon'  => 'refresh',
        ],
        'cache-clear'    => [
            'cmd'   => 'cache:clear',
            'args'  => [],
            'label' => 'Vider le cache',
            'desc'  => 'Efface le cache Redis (config, views, données).',
            'async' => false,
            'icon'  => 'trash',
        ],
    ];

    public function index()
    {
        $commands   = self::COMMANDS;
        $recentRuns = ConsoleRun::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('pages.settings.console', compact('commands', 'recentRuns'));
    }

    public function run(Request $request): JsonResponse
    {
        $key = $request->input('command');

        if (!array_key_exists($key, self::COMMANDS)) {
            return response()->json(['message' => 'Commande non autorisée.'], 422);
        }

        $def = self::COMMANDS[$key];

        $consoleRun = ConsoleRun::create([
            'user_id'       => auth()->id(),
            'command_key'   => $key,
            'command_label' => $def['label'],
            'status'        => $def['async'] ? 'pending' : 'running',
            'started_at'    => $def['async'] ? null : now(),
        ]);

        if ($def['async']) {
            RunConsoleCommandJob::dispatch($consoleRun, $def['cmd'], $def['args']);

            return response()->json([
                'run_id' => $consoleRun->id,
                'status' => 'pending',
                'async'  => true,
            ]);
        }

        // Commandes synchrones — rapides (queue:restart, cache:clear)
        try {
            $exitCode = Artisan::call($def['cmd'], $def['args']);
            $output   = Artisan::output() ?: '(commande exécutée)';
        } catch (\Throwable $e) {
            $consoleRun->update([
                'status'      => 'failed',
                'output'      => $e->getMessage(),
                'exit_code'   => 1,
                'finished_at' => now(),
            ]);

            return response()->json([
                'run_id'    => $consoleRun->id,
                'status'    => 'failed',
                'output'    => $e->getMessage(),
                'exit_code' => 1,
                'async'     => false,
            ], 500);
        }

        $consoleRun->update([
            'status'      => $exitCode === 0 ? 'done' : 'failed',
            'output'      => $output,
            'exit_code'   => $exitCode,
            'finished_at' => now(),
        ]);

        return response()->json([
            'run_id'       => $consoleRun->id,
            'status'       => $consoleRun->status,
            'output'       => $output,
            'exit_code'    => $exitCode,
            'duration_ms'  => $consoleRun->durationMs(),
            'async'        => false,
        ]);
    }

    public function status(ConsoleRun $run): JsonResponse
    {
        return response()->json([
            'run_id'       => $run->id,
            'status'       => $run->status,
            'output'       => $run->output,
            'exit_code'    => $run->exit_code,
            'duration_ms'  => $run->durationMs(),
            'command_label'=> $run->command_label,
            'started_at'   => $run->started_at?->toIso8601String(),
            'finished_at'  => $run->finished_at?->toIso8601String(),
        ]);
    }
}
