<x-app-shell active="companies" breadcrumb="Entreprises / {{ $company->name }} / Modifier">

<div class="px-7 pt-6 pb-3 flex items-center gap-4">
    <a href="{{ '/companies/' . $company->id }}" class="btn ghost icon">
        <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <h1>Modifier l'entreprise</h1>
</div>

<div class="px-7 pb-12 max-w-2xl">
    <form method="POST" action="{{ '/companies/' . $company->id }}" class="flex flex-col gap-4">
        @csrf
        @method('PUT')

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
                    <input type="text" name="name" value="{{ old('name', $company->name) }}" required autofocus>
                    @error('name')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Domaine</label>
                    <input type="text" name="domain" value="{{ old('domain', $company->domain) }}" placeholder="example.com">
                </div>
                <div class="field">
                    <label>Industrie</label>
                    <input type="text" name="industry" value="{{ old('industry', $company->industry) }}">
                </div>
                <div class="field">
                    <label>Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $company->phone) }}">
                </div>
                <div class="field">
                    <label>Site web</label>
                    <input type="url" name="website" value="{{ old('website', $company->website) }}" placeholder="https://…">
                    @error('website')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Ville</label>
                    <input type="text" name="city" value="{{ old('city', $company->city) }}">
                </div>
                <div class="field">
                    <label>Pays</label>
                    <input type="text" name="country" value="{{ old('country', $company->country) }}">
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ '/companies/' . $company->id }}" class="btn ghost">Annuler</a>
            <button type="submit" class="btn primary">Enregistrer</button>
        </div>
    </form>
</div>

</x-app-shell>
