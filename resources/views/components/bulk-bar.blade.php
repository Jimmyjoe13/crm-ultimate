@props(['entity', 'deleteAction', 'totalCount' => null])

{{-- Barre d'actions bulk — apparaît en bas d'écran quand au moins 1 ligne est sélectionnée --}}
<div
    x-data
    x-show="$store.bulk.count('{{ $entity }}') > 0 || $store.bulk.isSelectAllMode('{{ $entity }}')"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-y-4 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-4 opacity-0"
    style="position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:50; display:none;"
    class="card shadow-pop flex items-center gap-4 px-5 py-3"
>
    <span class="text-sm font-medium">
        <template x-if="$store.bulk.isSelectAllMode('{{ $entity }}')">
            <span>{{ $totalCount !== null ? $totalCount : '' }} <span x-text="'sélectionné(s) (tous)'"></span></span>
        </template>
        <template x-if="!$store.bulk.isSelectAllMode('{{ $entity }}')">
            <span><span x-text="$store.bulk.count('{{ $entity }}')"></span> sélectionné(s)</span>
        </template>
    </span>

    <form method="POST" action="{{ $deleteAction }}"
          @submit.prevent="
            const allMode = $store.bulk.isSelectAllMode('{{ $entity }}');
            const count = allMode ? {{ $totalCount ?? 0 }} : $store.bulk.count('{{ $entity }}');
            const msg = allMode
                ? 'Supprimer TOUS les ' + count + ' éléments ? Cette action est irréversible.'
                : 'Supprimer les ' + count + ' éléments sélectionnés ?';
            if (!confirm(msg)) return;

            $el.querySelectorAll('input[name=\'ids[]\'], input[name=\'select_all\']').forEach(el => el.remove());

            if (allMode) {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'select_all'; inp.value = '1';
                $el.appendChild(inp);
            } else {
                $store.bulk.ids('{{ $entity }}').forEach(id => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                    $el.appendChild(inp);
                });
            }

            $store.bulk.clear('{{ $entity }}');
            $el.submit();
          "
          class="flex items-center gap-2"
    >
        @csrf
        <button type="submit" class="btn danger sm">
            Supprimer la sélection
        </button>
    </form>

    <button type="button" class="btn ghost sm" @click="$store.bulk.clear('{{ $entity }}')">
        Annuler
    </button>
</div>
