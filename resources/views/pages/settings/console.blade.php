<x-app-shell active="settings" breadcrumb="Console Admin">

<div class="px-7 pt-6 pb-10 max-w-4xl mx-auto" x-data="consoleAdmin()">

    <div class="mb-6">
        <h1>Console Admin</h1>
        <p class="text-sm text-secondary mt-0.5">Exécuter des commandes système depuis l'interface — admin uniquement.</p>
    </div>

    {{-- Liste des commandes --}}
    <div class="grid gap-3 mb-8">
        @foreach($commands as $key => $cmd)
        <div class="card p-4 flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="font-medium text-sm">{{ $cmd['label'] }}</div>
                <div class="text-xs text-secondary mt-0.5">{{ $cmd['desc'] }}</div>
            </div>
            <button
                @click="runCommand('{{ $key }}', '{{ addslashes($cmd['label']) }}')"
                :disabled="running"
                class="btn btn-sm btn-secondary shrink-0"
                :class="{ 'opacity-50 cursor-not-allowed': running }"
            >
                <span x-show="currentKey !== '{{ $key }}'">Exécuter</span>
                <span x-show="currentKey === '{{ $key }}'" class="flex items-center gap-1.5">
                    <svg class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10" stroke-width="2" stroke-linecap="round"/></svg>
                    En cours…
                </span>
            </button>
        </div>
        @endforeach
    </div>

    {{-- Output terminal --}}
    <div class="card mb-8" x-show="output !== null" x-cloak>
        <div class="flex items-center justify-between px-4 py-2 border-b border-default bg-surface-2 rounded-t-lg">
            <span class="text-xs font-mono text-secondary" x-text="currentLabel"></span>
            <div class="flex items-center gap-2">
                <span class="text-xs" :class="statusClass" x-text="statusLabel"></span>
                <span class="text-xs text-tertiary" x-show="durationMs" x-text="durationMs + ' ms'"></span>
            </div>
        </div>
        <pre class="p-4 text-xs font-mono text-primary overflow-x-auto whitespace-pre-wrap max-h-80 overflow-y-auto leading-relaxed" x-text="output || '…'"></pre>
    </div>

    {{-- Historique des runs --}}
    @if($recentRuns->isNotEmpty())
    <div>
        <h2 class="text-sm font-semibold mb-3">Historique récent</h2>
        <div class="card divide-y divide-default">
            @foreach($recentRuns as $run)
            <div class="px-4 py-3 flex items-center justify-between text-sm gap-3">
                <div class="flex-1 min-w-0">
                    <span class="font-medium">{{ $run->command_label }}</span>
                    <span class="text-tertiary text-xs ml-2">par {{ $run->user?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    @if($run->status === 'done')
                        <span class="chip ok text-xs">succès</span>
                    @elseif($run->status === 'failed')
                        <span class="chip warn text-xs">échec</span>
                    @elseif(in_array($run->status, ['pending', 'running']))
                        <span class="chip accent text-xs">{{ $run->status }}</span>
                    @endif
                    <span class="text-tertiary text-xs num-mono">{{ $run->created_at->diffForHumans() }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>

<script>
function consoleAdmin() {
    return {
        running:      false,
        currentKey:   null,
        currentLabel: null,
        output:       null,
        exitCode:     null,
        durationMs:   null,
        runId:        null,
        pollTimer:    null,

        get statusClass() {
            if (this.running)       return 'text-accent';
            if (this.exitCode === 0) return 'text-ok';
            if (this.exitCode !== null) return 'text-warn';
            return 'text-secondary';
        },

        get statusLabel() {
            if (this.running)       return 'En cours…';
            if (this.exitCode === 0) return '✓ Terminé';
            if (this.exitCode !== null) return '✗ Échec';
            return '';
        },

        async runCommand(key, label) {
            if (this.running) return;

            this.running      = true;
            this.currentKey   = key;
            this.currentLabel = label;
            this.output       = null;
            this.exitCode     = null;
            this.durationMs   = null;
            this.runId        = null;

            try {
                const res = await fetch('/settings/console/run', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}' },
                    body:    JSON.stringify({ command: key }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.output   = data.message || 'Erreur serveur.';
                    this.exitCode = 1;
                    this.running  = false;
                    this.currentKey = null;
                    return;
                }

                if (!data.async) {
                    // Commande synchrone — réponse immédiate
                    this.output     = data.output;
                    this.exitCode   = data.exit_code;
                    this.durationMs = data.duration_ms;
                    this.running    = false;
                    this.currentKey = null;
                    return;
                }

                // Commande asynchrone — polling
                this.runId  = data.run_id;
                this.output = 'Commande envoyée en arrière-plan… (mise à jour toutes les 2s)';
                this.poll();

            } catch (e) {
                this.output   = 'Erreur réseau : ' + e.message;
                this.exitCode = 1;
                this.running  = false;
                this.currentKey = null;
            }
        },

        poll() {
            this.pollTimer = setInterval(async () => {
                try {
                    const res  = await fetch('/settings/console/run/' + this.runId);
                    const data = await res.json();

                    if (data.output)     this.output     = data.output;
                    if (data.duration_ms) this.durationMs = data.duration_ms;

                    if (['done', 'failed'].includes(data.status)) {
                        this.exitCode   = data.exit_code;
                        this.running    = false;
                        this.currentKey = null;
                        clearInterval(this.pollTimer);
                    }
                } catch (e) {
                    // on retry silencieusement
                }
            }, 2000);
        },
    };
}
</script>

</x-app-shell>
