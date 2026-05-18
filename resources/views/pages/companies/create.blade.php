<x-app-shell active="companies" breadcrumb="Entreprises / Nouvelle entreprise">

<div class="px-7 pt-6 pb-3 flex items-center gap-4">
    <a href="/companies" class="btn ghost icon">
        <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <h1>Nouvelle entreprise</h1>
</div>

<div class="px-7 pb-12 max-w-2xl">
    <form method="POST" action="/companies" class="flex flex-col gap-4">
        @csrf

        @if($errors->any())
        <div class="chip err px-3 py-2 rounded-lg" style="border-radius:8px;">
            @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
            @endforeach
        </div>
        @endif

        <div class="card p-5">
            <div class="mono-label mb-4">Informations</div>
            <div class="grid grid-cols-2 gap-4">
                <div class="field col-span-2">
                    <label>Nom <span class="text-err">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus>
                    @error('name')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Domaine</label>
                    <input type="text" name="domain" value="{{ old('domain') }}" placeholder="example.com">
                </div>
                <div class="field">
                    <label>Industrie</label>
                    <input type="text" name="industry" value="{{ old('industry') }}">
                </div>
                <div class="field">
                    <label>Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </div>
                <div class="field">
                    <label>Site web</label>
                    <input type="url" name="website" value="{{ old('website') }}" placeholder="https://…">
                    @error('website')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Ville</label>
                    <input type="text" name="city" value="{{ old('city') }}">
                </div>
                <div class="field">
                    <label>Pays</label>
                    <input type="text" name="country" value="{{ old('country') }}">
                </div>
                <x-custom-fields-form entity-type="company" :values="old('custom_values', [])" />
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="/companies" class="btn ghost">Annuler</a>
            <button type="submit" class="btn primary">Créer l'entreprise</button>
        </div>
    </form>
</div>

</x-app-shell>
