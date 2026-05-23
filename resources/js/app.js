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
    selectAllMode: { contact: false, company: false, deal: false },

    toggle(entity, id) {
        const s = this.selections[entity];
        if (s.has(id)) { s.delete(id); } else { s.add(id); }
        this.selectAllMode[entity] = false;
    },

    toggleAll(entity, ids) {
        const s = this.selections[entity];
        if (ids.every(id => s.has(id))) {
            ids.forEach(id => s.delete(id));
        } else {
            ids.forEach(id => s.add(id));
        }
        this.selectAllMode[entity] = false;
    },

    count(entity) {
        return this.selections[entity]?.size ?? 0;
    },

    ids(entity) {
        return [...(this.selections[entity] ?? new Set())];
    },

    clear(entity) {
        this.selections[entity] = new Set();
        this.selectAllMode[entity] = false;
    },

    allSelected(entity, ids) {
        return ids.length > 0 && ids.every(id => this.selections[entity]?.has(id));
    },

    enableSelectAll(entity) {
        this.selectAllMode[entity] = true;
    },

    isSelectAllMode(entity) {
        return this.selectAllMode[entity] === true;
    },
});

window.copyToClipboard = function(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text);
    }
    const el = document.createElement('textarea');
    el.value = text;
    el.style.position = 'absolute';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    el.select();
    try {
        const success = document.execCommand('copy');
        document.body.removeChild(el);
        return success ? Promise.resolve() : Promise.reject();
    } catch (err) {
        document.body.removeChild(el);
        return Promise.reject(err);
    }
};

import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';

// Register x-datepicker directive
Alpine.directive('datepicker', (el, { expression }, { evaluate }) => {
    if (el._flatpickr) return;

    const options = {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        allowInput: true,
        locale: French,
        onChange: function(selectedDates, dateStr, instance) {
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    if (expression) {
        Object.assign(options, evaluate(expression));
    }

    flatpickr(el, options);
});

Alpine.start();
