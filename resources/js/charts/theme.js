// Lecture centralisée des tokens de couleur du design system (resources/css/app.css).
// getComputedStyle reflète automatiquement le thème actif (html.dark) au moment de l'appel.
export function chartColors() {
    const style = getComputedStyle(document.documentElement);
    const get = (name, fallback) => style.getPropertyValue(name).trim() || fallback;

    return {
        accent: get('--accent', '#ef6a2a'),
        ok: get('--ok', '#2f8a5f'),
        text2: get('--text2', '#5e5c55'),
        text3: get('--text3', '#92908a'),
        border: get('--border', '#e7e5df'),
    };
}
