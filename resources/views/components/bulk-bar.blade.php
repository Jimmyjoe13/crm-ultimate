@props(['entity', 'deleteAction'])

{{-- Barre d'actions bulk — apparaît en bas d'écran quand au moins 1 ligne est sélectionnée --}}
<div
    x-data
    x-show="$store.bulk.count('{{ $entity }}') > 0"
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
        <span x-text="$store.bulk.count('{{ $entity }}')"></span> sélectionné(s)
    </span>

    <form method="POST" action="{{ $deleteAction }}"
          @submit.prevent="
            if (!confirm('Supprimer les ' + $store.bulk.count('{{ $entity }}') + ' éléments sélectionnés ?')) return;
            $el.querySelectorAll('input[name=\'ids[]\']').forEach(el => el.remove());
            $store.bulk.ids('{{ $entity }}').forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
                $el.appendChild(inp);
            });
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
