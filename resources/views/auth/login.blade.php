<x-layouts.app title="CRM Ultimate — Connexion">
<div class="min-h-screen flex items-center justify-center" style="background: var(--bg);">
    <div class="card shadow-pop w-full max-w-sm p-8">
        {{-- Brand --}}
        <div class="flex items-center gap-2 mb-8">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-white text-sm" style="background: var(--accent);">C</div>
            <span class="font-display text-xl font-semibold text-primary">CRM Ultimate</span>
        </div>

        <h2 class="text-2xl font-semibold mb-1">Connexion</h2>
        <p class="text-sm text-secondary mb-6">Bienvenue, entrez vos identifiants.</p>

        @if($errors->any())
            <div class="chip err mb-4 w-full justify-start rounded-lg px-3 py-2" style="border-radius: 8px;">
                <svg class="ic" style="width:14px;height:14px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
            @csrf
            <div class="field">
                <label for="email">Adresse email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus placeholder="admin@example.com" required>
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input id="password" type="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn primary w-full justify-center mt-2">
                Se connecter
            </button>
        </form>
    </div>
</div>
</x-layouts.app>
