/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
    './app/View/Components/**/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans:    ['"Patrick Hand"', 'system-ui', 'sans-serif'],   // body, UI
        display: ['Caveat', 'cursive'],                            // h1/h2/h3
        num:     ['Kalam', 'cursive'],                             // grands chiffres (KPI)
        mono:    ['"JetBrains Mono"', 'monospace'],                // data dense, labels
      },
      fontSize: {
        // ajouts custom (Tailwind couvre déjà xs/sm/base/lg/xl/2xl/3xl/4xl)
        '2xs': ['10.5px', { lineHeight: '1.4' }],
      },
      letterSpacing: {
        // pour les mono-labels
        wider2: '0.06em',
      },
      colors: {
        // ⚠️ ces couleurs servent surtout aux variants statiques.
        // Pour le thème light/dark dynamique, on utilise les CSS vars dans app.css
        // (--bg, --surface, --text, etc.) appliquées via les classes utilitaires
        // .bg-surface, .text-primary, etc.
        accent: {
          DEFAULT: '#ef6a2a',
          hover:   '#d85816',
          soft:    '#fff3eb',
          'soft-dk': 'rgba(239,106,42,0.14)',
        },
        ok:   { DEFAULT: '#2f8a5f', soft: '#e1f2e9' },
        warn: { DEFAULT: '#d4a017', soft: '#fbf3dc' },
        err:  { DEFAULT: '#c63d2f', soft: '#fce8e5' },
        info: { DEFAULT: '#2a5fb4', soft: '#e1eaf7' },
        // avatar palette
        avatar: {
          'c1-bg': '#ffe7d8', 'c1-fg': '#c44e10',
          'c2-bg': '#d6efe0', 'c2-fg': '#1d6b46',
          'c3-bg': '#dde8fa', 'c3-fg': '#1f4b94',
          'c4-bg': '#f4e0f7', 'c4-fg': '#7d2a93',
          'c5-bg': '#fbf3dc', 'c5-fg': '#8a6700',
        },
      },
      borderRadius: {
        // l'échelle Tailwind par défaut suffit, on rappelle juste les valeurs utilisées :
        // 'md'  → 6px (inputs, btn sm)
        // 'lg'  → 8px (btn, cards d'éléments)
        // 'xl'  → 12px (cards, modal, KPI hero)
      },
      boxShadow: {
        card: '0 1px 2px rgba(20,20,15,0.04), 0 0 0 1px var(--border)',
        pop:  '0 12px 32px -8px rgba(20,20,15,0.18), 0 0 0 1px var(--border)',
      },
      transitionTimingFunction: {
        'drawer': 'cubic-bezier(0.32, 0.72, 0, 1)',
      },
      transitionDuration: {
        '120': '120ms',
        '220': '220ms',
      },
      zIndex: {
        '10': '10', '20': '20', '30': '30', '40': '40', '50': '50',
        '60': '60', '100': '100',
      },
      maxWidth: {
        'drawer': '720px',
        'modal-sm': '420px',
        'modal-md': '580px',
        'modal-lg': '720px',
      },
      gridTemplateColumns: {
        // patterns récurrents
        'kpi-band':   'minmax(0,1.4fr) repeat(3, minmax(0,1fr))',
        'task-row':   '24px 36px 1fr auto auto',
        'stage-row':  '24px 36px 1fr 120px 120px 100px 60px',
        'drawer-body':'1fr 280px',
      },
      keyframes: {
        'fade-in':    { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
        'slide-in-r': { '0%': { transform: 'translateX(100%)' }, '100%': { transform: 'translateX(0)' } },
        'scale-in':   { '0%': { opacity: 0, transform: 'scale(0.96)' }, '100%': { opacity: 1, transform: 'scale(1)' } },
      },
      animation: {
        'fade-in':    'fade-in 160ms ease-out',
        'slide-in-r': 'slide-in-r 220ms cubic-bezier(0.32, 0.72, 0, 1)',
        'scale-in':   'scale-in 160ms ease-out',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')({ strategy: 'class' }),
  ],
};
