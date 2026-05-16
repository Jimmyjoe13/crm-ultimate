<div x-data="toastStore()"
     @toast.window="add($event.detail)"
     class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"
     style="max-width: 380px; min-width: 300px;">

    <template x-for="t in toasts" :key="t.id">
        <div x-show="t.visible"
             x-transition:enter="transition ease-out duration-200 transform"
             x-transition:enter-start="translate-x-full opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             x-transition:leave="transition ease-in duration-150 transform"
             x-transition:leave-start="translate-x-0 opacity-100"
             x-transition:leave-end="translate-x-full opacity-0"
             class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-lg border"
             :style="'background: var(--surface); border-color: var(--border); box-shadow: var(--shadow-pop);'">

            {{-- Icon --}}
            <div class="flex-shrink-0 mt-0.5">
                <template x-if="t.type === 'success'">
                    <svg class="ic" style="color:var(--ok);" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                </template>
                <template x-if="t.type === 'error'">
                    <svg class="ic" style="color:var(--err);" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                </template>
                <template x-if="t.type === 'warning'">
                    <svg class="ic" style="color:var(--warn);" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                </template>
                <template x-if="t.type === 'info'">
                    <svg class="ic" style="color:var(--info);" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                </template>
            </div>

            {{-- Message --}}
            <p class="flex-1 text-sm" style="color: var(--text); margin: 0;" x-text="t.message"></p>

            {{-- Dismiss --}}
            <button @click="dismiss(t.id)" class="flex-shrink-0 opacity-50 hover:opacity-100" style="background:none;border:none;padding:0;cursor:pointer;color:var(--text3);">
                <svg style="width:12px;height:12px;" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>

<script>
function toastStore() {
    return {
        toasts: [],
        nextId: 0,
        add(detail) {
            const id = ++this.nextId;
            const toast = { id, message: detail.message || detail, type: detail.type || 'info', visible: true };

            this.toasts.push(toast);

            // Max 3 visible (FIFO)
            while (this.toasts.length > 3) {
                this.toasts.shift();
            }

            // Auto-dismiss after 4s
            setTimeout(() => this.dismiss(id), 4000);
        },
        dismiss(id) {
            const t = this.toasts.find(x => x.id === id);
            if (t) t.visible = false;
            setTimeout(() => { this.toasts = this.toasts.filter(x => x.id !== id); }, 200);
        }
    };
}

// Global helper: window.toast('message', 'success')
window.toast = function(message, type = 'info') {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
};
</script>
