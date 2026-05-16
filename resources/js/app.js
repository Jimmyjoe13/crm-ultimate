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

Alpine.start();
