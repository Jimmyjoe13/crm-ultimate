// Drag & drop du pipeline Kanban — SortableJS bundlé par Vite (plus de CDN).
import Sortable from 'sortablejs';

export function initKanban() {
    const lists = document.querySelectorAll('.sortable-list');
    if (!lists.length) return;

    lists.forEach(function (list) {
        Sortable.create(list, {
            group: 'kanban',
            animation: 150,
            ghostClass: 'k-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function (evt) {
                const dealId = evt.item.dataset.deal;
                const stageId = evt.to.dataset.stage;
                if (!dealId || !stageId) return;
                // Pas de changement d'étape → rien à persister.
                if (evt.from === evt.to) return;

                const token = document.querySelector('meta[name="csrf-token"]');

                fetch('/api/v1/deals/' + dealId + '/move', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token ? token.content : '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ pipeline_stage_id: parseInt(stageId, 10) }),
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('move failed');
                        if (window.toast) window.toast('Deal déplacé.', 'success');
                    })
                    .catch(function () {
                        if (window.toast) window.toast('Échec du déplacement du deal.', 'error');
                        // Revert visuel : on replace la carte à sa position d'origine.
                        const ref = evt.from.children[evt.oldIndex] || null;
                        evt.from.insertBefore(evt.item, ref);
                    });
            },
        });
    });
}
