import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.store('toasts', {
    items: [],
    push(toast) {
        const id = Date.now();
        this.items.push({ id, ...toast });
        setTimeout(() => this.remove(id), toast.duration ?? 4000);
    },
    remove(id) {
        this.items = this.items.filter(t => t.id !== id);
    },
});

Alpine.store('bulk', {
    selections: { contact: new Set(), company: new Set(), deal: new Set() },

    toggle(entity, id) {
        const s = this.selections[entity];
        if (s.has(id)) { s.delete(id); } else { s.add(id); }
    },

    toggleAll(entity, ids) {
        const s = this.selections[entity];
        if (ids.every(id => s.has(id))) {
            ids.forEach(id => s.delete(id));
        } else {
            ids.forEach(id => s.add(id));
        }
    },

    count(entity) {
        return this.selections[entity]?.size ?? 0;
    },

    ids(entity) {
        return [...(this.selections[entity] ?? new Set())];
    },

    clear(entity) {
        this.selections[entity] = new Set();
    },

    allSelected(entity, ids) {
        return ids.length > 0 && ids.every(id => this.selections[entity]?.has(id));
    },
});

Alpine.start();
