<x-app-shell active="contacts" breadcrumb="Contacts / Nouveau contact">

<div class="px-7 pt-6 pb-3 flex items-center gap-4">
    <a href="/contacts" class="btn ghost icon">
        <svg class="ic" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
    <h1>Nouveau contact</h1>
</div>

<div class="px-7 pb-12 max-w-2xl">
    <form method="POST" action="/contacts" class="flex flex-col gap-4">
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
                <div class="field">
                    <label>Prénom <span class="text-err">*</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required autofocus>
                    @error('first_name')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Nom</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email') }}">
                    @error('email')<span class="text-xs text-err">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label>Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </div>
                <div class="field">
                    <label>Poste</label>
                    <input type="text" name="job_title" value="{{ old('job_title') }}">
                </div>
                <div class="field">
                    <label>Lifecycle stage</label>
                    <select name="lifecycle_stage" class="select-arrow">
                        <option value="">—</option>
                        @foreach(['lead','mql','sql','opportunity','customer','evangelist','other'] as $stage)
                        <option value="{{ $stage }}" {{ old('lifecycle_stage') === $stage ? 'selected' : '' }}>{{ $stage }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field col-span-2"
                     x-data="{
                         search: '{{ old('company_name', '') }}',
                         companyId: '{{ old('company_id', '') }}',
                         open: false,
                         companies: {{ Js::from($companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name])) }},
                         get filtered() {
                             if (!this.search) return this.companies.slice(0, 50);
                             const q = this.search.toLowerCase();
                             return this.companies.filter(c => c.name.toLowerCase().includes(q)).slice(0, 50);
                         },
                         select(c) { this.search = c.name; this.companyId = c.id; this.open = false; },
                         clear() { this.search = ''; this.companyId = ''; }
                     }">
                    <label>Entreprise</label>
                    <input type="hidden" name="company_id" :value="companyId">
                    <div class="relative">
                        <input type="text" x-model="search"
                               @focus="open = true"
                               @input="companyId = ''; open = true"
                               @click.outside="open = false"
                               placeholder="Rechercher une entreprise…"
                               autocomplete="off">
                        <button type="button" x-show="search" @click="clear()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">✕</button>
                        <div x-show="open && filtered.length > 0"
                             class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-pop mt-1 max-h-52 overflow-y-auto">
                            <template x-for="c in filtered" :key="c.id">
                                <div class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50"
                                     :class="companyId == c.id ? 'font-medium text-accent' : ''"
                                     @click="select(c)" x-text="c.name"></div>
                            </template>
                        </div>
                        <div x-show="open && search && filtered.length === 0"
                             class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-pop mt-1 px-3 py-2 text-sm text-gray-400">
                            Aucune entreprise trouvée
                        </div>
                    </div>
                </div>
                <x-custom-fields-form entity-type="contact" :values="old('custom_values', [])" />
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="/contacts" class="btn ghost">Annuler</a>
            <button type="submit" class="btn primary">Créer le contact</button>
        </div>
    </form>
</div>

</x-app-shell>
