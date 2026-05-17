<x-app-shell active="deals" breadcrumb="Deals / {{ $deal->name }} / Modifier">

<div class="px-7 pt-6 pb-3 flex items-center gap-4">
    <a href="{{ '/deals/' . $deal->id }}" class="btn ghost icon">
        <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <h1>Modifier le deal</h1>
</div>

<div class="px-7 pb-12 max-w-2xl">
    <form method="POST" action="{{ '/deals/' . $deal->id }}" class="flex flex-col gap-4">
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
                    <label>Nom du deal <span class="text-err">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $deal->name) }}" required autofocus>
                    @error('name')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Montant (€) <span class="text-err">*</span></label>
                    <input type="number" name="amount" value="{{ old('amount', $deal->amount) }}" step="0.01" min="0" required>
                    @error('amount')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Date de clôture</label>
                    <input type="date" name="close_date" value="{{ old('close_date', $deal->close_date?->format('Y-m-d')) }}">
                </div>
                <div class="field col-span-2">
                    <label>Étape <span class="text-err">*</span></label>
                    <select name="pipeline_stage_id" class="select-arrow" required>
                        @foreach($stages as $stage)
                        <option value="{{ $stage->id }}" {{ old('pipeline_stage_id', $deal->pipeline_stage_id) == $stage->id ? 'selected' : '' }}>
                            {{ $stage->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('pipeline_stage_id')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ '/deals/' . $deal->id }}" class="btn ghost">Annuler</a>
            <button type="submit" class="btn primary">Enregistrer</button>
        </div>
    </form>
</div>

</x-app-shell>
