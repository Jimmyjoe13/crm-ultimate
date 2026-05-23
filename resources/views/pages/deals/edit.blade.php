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
                <div class="field" x-data="{
                    setDate(days) {
                        const d = new Date();
                        d.setDate(d.getDate() + days);
                        this.$refs.closeInput._flatpickr.setDate(d, true);
                    },
                    setEndOfMonth() {
                        const d = new Date();
                        const nextMonth = new Date(d.getFullYear(), d.getMonth() + 1, 0);
                        this.$refs.closeInput._flatpickr.setDate(nextMonth, true);
                    },
                    setEndOfQuarter() {
                        const d = new Date();
                        const currentQuarter = Math.floor(d.getMonth() / 3);
                        const endOfQuarter = new Date(d.getFullYear(), (currentQuarter + 1) * 3, 0);
                        this.$refs.closeInput._flatpickr.setDate(endOfQuarter, true);
                    }
                }">
                    <label>Date de clôture</label>
                    <div class="relative flex items-center">
                        <input type="text" name="close_date" x-ref="closeInput" x-datepicker value="{{ old('close_date', $deal->close_date?->format('Y-m-d')) }}" placeholder="Sélectionnez une date..." class="w-full pr-10">
                        <span class="absolute right-3 pointer-events-none text-tertiary">
                            <svg class="ic sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        <button type="button" @click="setEndOfMonth()" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">Fin de mois</button>
                        <button type="button" @click="setEndOfQuarter()" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">Fin trim.</button>
                        <button type="button" @click="setDate(30)" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">+30j</button>
                        <button type="button" @click="setDate(90)" class="btn ghost sm text-[10.5px] py-0.5 px-2 bg-surface2 border border-default hover:border-strong font-mono">+90j</button>
                    </div>
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
                <x-custom-fields-form entity-type="deal" :values="old('custom_values', $deal->custom_values ?? [])" />
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ '/deals/' . $deal->id }}" class="btn ghost">Annuler</a>
            <button type="submit" class="btn primary">Enregistrer</button>
        </div>
    </form>
</div>

</x-app-shell>
