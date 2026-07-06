@props(['active' => ''])

<x-layouts.app :title="$title ?? 'CRM Ultimate'">
<div class="flex h-screen overflow-hidden" style="background: var(--bg);">

    {{-- ===== ICON RAIL ===== --}}
    <aside class="flex flex-col items-center py-3 gap-1 border-r flex-shrink-0"
           style="width:56px; background: var(--surface); border-color: var(--border);">

        {{-- Brand mark --}}
        <div class="rail-ic" style="background: var(--accent); color: white; font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 14px; cursor: default;">C</div>

        <div class="my-2 h-px w-6" style="background: var(--border);"></div>

        <x-rail-icon route="dashboard" :active="$active === 'dashboard'" tooltip="Dashboard">
            <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
        </x-rail-icon>
        <x-rail-icon href="/deals" :active="$active === 'deals'" tooltip="Deals">
            <svg class="ic" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h12"/></svg>
        </x-rail-icon>
        <x-rail-icon route="pipeline.index" :active="$active === 'pipeline'" tooltip="Pipeline">
            <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="3" width="5" height="18"/><rect x="10" y="3" width="5" height="12"/><rect x="17" y="3" width="4" height="8"/></svg>
        </x-rail-icon>
        @php
            $emeliaPending = \App\Models\Activity::where('type', \App\Models\Activity::TYPE_EMAIL_REPLIED)
                ->where('created_at', '>', auth()->user()?->emelia_replies_last_seen ?? '1970-01-01')
                ->count();
        @endphp
        <div class="relative" x-data="{ emeliaBadge: {{ $emeliaPending }}, aiAlertBadge: 0 }" x-init="
            fetch('/web/ai/proactive-alerts', { headers:{'Accept':'application/json'}, credentials:'same-origin' })
                .then(r => r.ok ? r.json() : null)
                .then(d => { if(d) aiAlertBadge = d.critical || 0; })
                .catch(() => {});
        ">
            <a href="/contacts"
               class="rail-ic {{ $active === 'contacts' ? 'on' : '' }}"
               @click="if(emeliaBadge > 0) { fetch('/notifications/emelia-replies/seen', { method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, credentials:'same-origin' }); emeliaBadge = 0; }">
                <svg class="ic" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="tt">Contacts</span>
            </a>
            {{-- Badge Emelia (rouge) --}}
            <template x-if="emeliaBadge > 0">
                <span x-text="emeliaBadge > 9 ? '9+' : emeliaBadge"
                      style="position:absolute;top:3px;right:3px;min-width:14px;height:14px;padding:0 3px;border-radius:7px;font-size:8px;font-weight:700;line-height:14px;text-align:center;background:var(--err);color:#fff;pointer-events:none;"></span>
            </template>
            {{-- Badge alertes IA critique (orange/amber — au-dessus à gauche) --}}
            <template x-if="aiAlertBadge > 0 && emeliaBadge === 0">
                <span x-text="aiAlertBadge > 9 ? '9+' : aiAlertBadge"
                      style="position:absolute;top:3px;right:3px;min-width:14px;height:14px;padding:0 3px;border-radius:7px;font-size:8px;font-weight:700;line-height:14px;text-align:center;background:var(--accent);color:#fff;pointer-events:none;"
                      title="Alerte(s) IA critique(s)"></span>
            </template>
        </div>
        <x-rail-icon href="/companies" :active="$active === 'companies'" tooltip="Entreprises">
            <svg class="ic" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9v2M9 13v2M9 17v2M15 9v2M15 13v2M15 17v2"/></svg>
        </x-rail-icon>
        <x-rail-icon href="/activities" :active="$active === 'activities'" tooltip="Activités">
            <svg class="ic" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </x-rail-icon>
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <x-rail-icon href="/reports" :active="$active === 'reports'" tooltip="Rapports">
            <svg class="ic" viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
        </x-rail-icon>
        @endif

        <div class="my-2 h-px w-6" style="background: var(--border);"></div>

        <x-rail-icon href="/segments" :active="$active === 'segments'" tooltip="Segments">
            <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
        </x-rail-icon>
        <x-rail-icon route="stages.index" :active="$active === 'stages'" tooltip="Étapes">
            <svg class="ic" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="14" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="6" cy="18" r="2" fill="currentColor" stroke="none"/></svg>
        </x-rail-icon>
        <x-rail-icon route="fields.index" :active="$active === 'fields'" tooltip="Champs perso">
            <svg class="ic" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </x-rail-icon>
        <x-rail-icon route="email-templates.index" :active="$active === 'email-templates'" tooltip="Modèles d'email">
            <svg class="ic" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
        </x-rail-icon>
        @if(in_array(auth()->user()?->role, ['admin','manager']))
        <x-rail-icon route="trash.index" :active="$active === 'trash'" tooltip="Corbeille">
            <svg class="ic" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </x-rail-icon>
        @endif
        @if(auth()->user()?->isAdmin())
        <x-rail-icon route="console.index" :active="$active === 'console'" tooltip="Console Admin">
            <svg class="ic" viewBox="0 0 24 24"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
        </x-rail-icon>
        @endif

        {{-- User avatar (bottom) --}}
        <div class="mt-auto"></div>
        @php
            $user = auth()->user();
            $initials = $user ? \App\Helpers\Avatar::initials($user->name ?? $user->email) : '?';
            $color = $user ? \App\Helpers\Avatar::color($user->name ?? $user->email) : 'c1';
        @endphp
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="rail-ic av {{ $color }}" style="border-radius: 9px; width:36px; height:36px; font-size:11px;">
                {{ $initials }}
                <span class="tt">{{ $user?->email ?? '' }}</span>
            </button>
            <div x-show="open" @click.outside="open = false"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute bottom-full left-full ml-2 mb-1 card shadow-pop min-w-[160px] py-1"
                 style="z-index: 50;">
                <div class="px-3 py-2 border-b border-default">
                    <div class="text-[12px] font-medium text-primary truncate">{{ $user?->name ?? $user?->email }}</div>
                    <div class="mono-label mt-0.5">{{ $user?->role ?? '' }}</div>
                </div>
                {{-- Theme toggle --}}
                <button onclick="toggleTheme()" class="w-full text-left px-3 py-2 text-[13px] text-secondary hover:bg-surface2 flex items-center gap-2">
                    <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    Changer de thème
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-3 py-2 text-[13px] text-secondary hover:bg-surface2 flex items-center gap-2">
                        <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ===== MAIN ===== --}}
    <main class="flex-1 flex flex-col overflow-hidden">

        {{-- Sticky header --}}
        <header class="flex-shrink-0 sticky top-0 z-20 border-b" style="background: var(--bg); border-color: var(--border);">
            <div class="flex items-center justify-between px-7 py-3">
                <div class="flex items-center gap-3">
                    <span class="mono-label">workspace</span>
                    <span class="text-tertiary text-xs">/</span>
                    <span class="text-sm text-secondary">{{ $breadcrumb ?? '' }}</span>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Global search — ouvre la palette ⌘K --}}
                    <button @click="$dispatch('open-cmd-palette')"
                            class="flex items-center gap-2 px-3 py-1.5 border rounded-lg text-sm"
                            style="border-color: var(--border); background: var(--surface); width: 320px; cursor: text; text-align:left;">
                        <svg class="ic" style="color: var(--text3);" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        <span class="text-sm text-tertiary flex-1">Rechercher deal, contact, entreprise…</span>
                        <span class="kbd">⌘ K</span>
                    </button>

                    {{-- Header actions slot (optional override) --}}
                    {{ $actions ?? '' }}

                    {{-- Default header buttons --}}
                    @unless(isset($actions))
                    <a href="#" class="btn">
                        <svg class="ic" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Tâches
                    </a>
                    @endunless
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <div class="flex-1 overflow-auto" id="pageScroll">
            {{ $slot }}
        </div>
    </main>
</div>

<script>
function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
}
</script>

<x-command-palette />
</x-layouts.app>
