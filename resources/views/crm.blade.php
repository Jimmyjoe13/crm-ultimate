<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRM Ultimate</title>
    <style>
        :root {
            --bg: #eef2f7;
            --panel: #ffffff;
            --panel-strong: #f8fafc;
            --soft: #f1f5f9;
            --text: #243447;
            --muted: #6b7a90;
            --line: #d6dee8;
            --brand: #ff6b35;
            --brand-dark: #d94f1f;
            --accent: #0f766e;
            --accent-soft: #dff7f1;
            --danger: #b42318;
            --warn: #b54708;
            --ok: #087443;
            --ink: #172033;
            --ink-2: #213146;
            --ink-3: #2b3f58;
            --shadow: 0 18px 42px rgba(23, 32, 51, .12);
            --shadow-soft: 0 8px 22px rgba(23, 32, 51, .08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                linear-gradient(180deg, #dfe7f1 0, var(--bg) 260px);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            letter-spacing: 0;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                linear-gradient(135deg, #172033 0%, #253650 55%, #314762 100%);
        }

        .login-box,
        .panel,
        .topbar {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .login-box {
            width: min(430px, 100%);
            padding: 26px;
            display: grid;
            gap: 16px;
            border-color: rgba(255, 255, 255, .24);
        }

        .shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 264px minmax(0, 1fr);
        }

        .sidebar {
            background: linear-gradient(180deg, var(--ink) 0%, var(--ink-2) 100%);
            color: #f9fafb;
            padding: 22px 16px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            border-right: 1px solid rgba(255, 255, 255, .08);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 18px;
            min-height: 42px;
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: var(--brand);
            display: grid;
            place-items: center;
            color: #ffffff;
            box-shadow: 0 10px 22px rgba(255, 107, 53, .28);
        }

        .nav {
            display: grid;
            gap: 6px;
        }

        .nav button {
            width: 100%;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #cbd5e1;
            padding: 10px 11px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav button.active,
        .nav button:hover {
            background: rgba(255, 255, 255, .11);
            color: #ffffff;
        }

        .nav button.active {
            box-shadow: inset 3px 0 0 var(--brand);
        }

        .nav-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, .08);
            color: #ffffff;
            font-size: 12px;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .main {
            min-width: 0;
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .topbar {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            border-color: rgba(214, 222, 232, .9);
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        h1 {
            font-size: 24px;
            line-height: 1.2;
            color: var(--ink);
        }

        h2 {
            font-size: 16px;
        }

        h3 {
            font-size: 14px;
        }

        .muted {
            color: var(--muted);
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(340px, 420px);
            gap: 18px;
            align-items: start;
        }

        .panel {
            overflow: hidden;
            border-color: rgba(214, 222, 232, .9);
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 15px 16px;
            border-bottom: 1px solid var(--line);
            background: var(--panel-strong);
        }

        .panel-body {
            padding: 16px;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--line);
            background: #ffffff;
            border-radius: 999px;
            padding: 7px 10px;
            color: var(--muted);
            font-weight: 700;
        }

        .user-dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--accent);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 900;
        }

        .tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tab,
        .btn {
            border: 1px solid transparent;
            border-radius: 8px;
            min-height: 38px;
            padding: 9px 12px;
            font-weight: 700;
            transition: background .16s ease, border-color .16s ease, box-shadow .16s ease, transform .16s ease;
        }

        .btn {
            background: var(--brand);
            color: #ffffff;
            box-shadow: 0 8px 18px rgba(255, 107, 53, .22);
        }

        .btn:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
        }

        .btn.secondary,
        .tab {
            background: #ffffff;
            color: var(--text);
            border-color: var(--line);
            box-shadow: none;
        }

        .tab.active {
            background: var(--ink-2);
            color: #ffffff;
            border-color: var(--ink-2);
        }

        .btn.danger {
            background: #ffffff;
            color: var(--danger);
            border-color: #fda29b;
            box-shadow: none;
        }

        .field {
            display: grid;
            gap: 6px;
            margin-bottom: 12px;
        }

        label {
            color: #344054;
            font-weight: 700;
            font-size: 13px;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            color: var(--text);
            padding: 10px 12px;
            outline: none;
        }

        textarea {
            min-height: 92px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, .13);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f7f9fc;
            color: #475467;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        tr {
            cursor: pointer;
        }

        tr:hover td,
        tr.selected td {
            background: #fff8f4;
        }

        .status {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            border-radius: 999px;
            padding: 3px 9px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 800;
            font-size: 12px;
        }

        .message {
            min-height: 22px;
            color: var(--muted);
        }

        .message.error {
            color: var(--danger);
        }

        .empty {
            padding: 24px;
            color: var(--muted);
            text-align: center;
        }

        .detail-title {
            display: grid;
            gap: 4px;
        }

        .kv {
            display: grid;
            grid-template-columns: 130px minmax(0, 1fr);
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid var(--line);
        }

        .kv span:first-child {
            color: var(--muted);
            font-weight: 700;
        }

        .stack {
            display: grid;
            gap: 12px;
        }

        .timeline-item {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
            display: grid;
            gap: 6px;
            background: #ffffff;
            box-shadow: var(--shadow-soft);
        }

        .metric-card {
            min-height: 132px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
            background: #ffffff;
            display: grid;
            align-content: space-between;
            box-shadow: var(--shadow-soft);
            border-top: 4px solid var(--brand);
        }

        .metric-card h1 {
            font-size: 30px;
        }

        .split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .board {
            display: grid;
            grid-auto-flow: column;
            grid-auto-columns: minmax(260px, 1fr);
            gap: 14px;
            overflow-x: auto;
            padding: 4px 2px 12px;
        }

        .column {
            background: #f7f9fc;
            border: 1px solid var(--line);
            border-radius: 8px;
            min-height: 360px;
            padding: 12px;
        }

        .column-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }

        .deal-card {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #ffffff;
            text-align: left;
            padding: 12px;
            display: grid;
            gap: 7px;
            margin-bottom: 10px;
            box-shadow: var(--shadow-soft);
        }

        .deal-card:hover {
            border-color: var(--brand);
            box-shadow: 0 12px 26px rgba(255, 107, 53, .15);
        }

        .move-row {
            display: flex;
            gap: 8px;
        }

        .move-row select {
            min-width: 0;
        }

        @media (max-width: 1050px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .nav {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .nav button {
                text-align: center;
            }
        }

        @media (max-width: 620px) {
            .main {
                padding: 12px;
            }

            .topbar {
                align-items: stretch;
                flex-direction: column;
            }

            .toolbar .btn,
            .toolbar input {
                width: 100%;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .split {
                grid-template-columns: 1fr;
            }
        }

        /* ── AI Panel ── */
        .ai-tab-content { display: flex; flex-direction: column; gap: 0.75rem; }
        .ai-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .ai-btn { padding: 0.38rem 0.7rem; font-size: 0.78rem; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: opacity 0.15s; font-weight: 600; }
        .ai-btn:hover { opacity: 0.82; }
        .ai-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .ai-result-wrap { background: var(--soft); border: 1px solid var(--line); border-radius: 8px; padding: 0.8rem 1rem; font-size: 0.85rem; line-height: 1.65; white-space: pre-wrap; color: var(--text); }
        .ai-loading { color: var(--muted); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0; }
        .ai-loading::before { content: ''; width: 14px; height: 14px; border: 2px solid var(--line); border-top-color: #7c3aed; border-radius: 50%; animation: spin 0.7s linear infinite; flex-shrink: 0; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .score-gauge-wrap { display: flex; flex-direction: column; gap: 0.45rem; }
        .score-track { background: var(--line); border-radius: 99px; height: 10px; overflow: hidden; }
        .score-bar { height: 100%; border-radius: 99px; transition: width 0.5s ease; }
        .score-label { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--muted); }
        .score-reasons { list-style: disc; padding-left: 1.25rem; font-size: 0.8rem; color: var(--muted); margin: 0.25rem 0 0; }
        .json-result { font-family: monospace; font-size: 0.78rem; background: var(--ink); color: #a8d8a8; padding: 0.75rem; border-radius: 6px; white-space: pre-wrap; }
        .ai-cached-note { font-size: 0.72rem; color: var(--muted); text-align: right; margin-top: 0.25rem; }
        /* ── Global search ── */
        .global-search-wrap { position: relative; }
        .global-search-input { background: var(--soft); border: 1px solid var(--line); color: var(--text); border-radius: 8px; padding: 0.38rem 0.8rem; font-size: 0.85rem; width: 200px; }
        .global-search-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 2px rgba(255,107,53,.18); }
        .search-dropdown { position: absolute; top: calc(100% + 6px); left: 0; width: 340px; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; box-shadow: var(--shadow); z-index: 200; overflow: hidden; max-height: 400px; overflow-y: auto; }
        .search-section-title { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); padding: 0.55rem 0.85rem 0.2rem; }
        .search-result-item { display: flex; align-items: center; gap: 0.6rem; padding: 0.45rem 0.85rem; cursor: pointer; transition: background 0.1s; font-size: 0.85rem; }
        .search-result-item:hover { background: var(--soft); }
        .search-result-icon { width: 26px; height: 26px; border-radius: 6px; background: rgba(255,107,53,.12); color: var(--brand); display: flex; align-items: center; justify-content: center; font-size: 0.68rem; font-weight: 700; flex-shrink: 0; }
        .search-empty { padding: 0.75rem 0.85rem; color: var(--muted); font-size: 0.85rem; }
        /* ── Task badge ── */
        .task-badge-wrap { position: relative; }
        .task-badge-btn { background: var(--soft); border: 1px solid var(--line); border-radius: 8px; cursor: pointer; padding: 0.35rem 0.6rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.35rem; color: var(--text); transition: background 0.12s; }
        .task-badge-btn:hover { background: var(--line); }
        .badge-count { background: var(--brand); color: #fff; font-size: 0.65rem; font-weight: 700; border-radius: 99px; min-width: 17px; height: 17px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; }
        .badge-count.overdue { background: var(--danger); }
        .due-dropdown { position: absolute; top: calc(100% + 6px); right: 0; width: 280px; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; box-shadow: var(--shadow); z-index: 200; overflow: hidden; max-height: 360px; overflow-y: auto; }
        .due-task-item { padding: 0.55rem 0.85rem; border-bottom: 1px solid var(--line); font-size: 0.84rem; }
        .due-task-item:last-child { border-bottom: none; }
        .due-task-title { font-weight: 600; }
        .due-task-date { font-size: 0.74rem; color: var(--muted); margin-top: 2px; }
        .due-task-date.overdue { color: var(--danger); font-weight: 600; }
        .due-empty { padding: 0.75rem 0.85rem; color: var(--muted); font-size: 0.85rem; }
        /* ── Import CSV ── */
        .import-panel { background: var(--soft); border: 1px solid var(--line); border-radius: 0; padding: 0.85rem 1.1rem; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .import-panel input[type=file] { padding: 0.25rem 0; }
    </style>
</head>
<body>
    <div id="app"></div>

    <script>
        const API = '/api/v1';
        const tokenKey = 'crm_ultimate_token';
        const subjectTypes = {
            companies: 'App\\Models\\Company',
            contacts: 'App\\Models\\Contact',
            deals: 'App\\Models\\Deal',
        };

        const resources = {
            dashboard: { label: 'Dashboard', type: 'dashboard', icon: 'DB' },
            companies: {
                label: 'Entreprises',
                icon: 'CO',
                endpoint: '/companies',
                title: row => row.name,
                subtitle: row => [row.domain, row.industry].filter(Boolean).join(' - '),
                columns: ['id', 'name', 'domain', 'industry', 'city', 'country'],
                fields: [
                    ['name', 'Nom', 'text', true],
                    ['domain', 'Domaine', 'text'],
                    ['industry', 'Secteur', 'text'],
                    ['phone', 'Telephone', 'text'],
                    ['website', 'Site web', 'url'],
                    ['city', 'Ville', 'text'],
                    ['country', 'Pays', 'text'],
                ],
                details: ['name', 'domain', 'industry', 'phone', 'website', 'city', 'country'],
            },
            contacts: {
                label: 'Contacts',
                icon: 'CT',
                endpoint: '/contacts',
                title: row => [row.first_name, row.last_name].filter(Boolean).join(' '),
                subtitle: row => [row.email, row.job_title].filter(Boolean).join(' - '),
                columns: ['id', 'first_name', 'last_name', 'email', 'phone', 'job_title'],
                fields: [
                    ['first_name', 'Prenom', 'text', true],
                    ['last_name', 'Nom', 'text'],
                    ['email', 'Email', 'email'],
                    ['phone', 'Telephone', 'text'],
                    ['job_title', 'Poste', 'text'],
                    ['company_id', 'ID entreprise', 'number'],
                ],
                details: ['first_name', 'last_name', 'email', 'phone', 'job_title', 'company_id'],
            },
            deals: {
                label: 'Deals',
                icon: 'DL',
                endpoint: '/deals',
                title: row => row.name,
                subtitle: row => `${money(row.amount, row.currency)} - ${row.status || 'open'}`,
                columns: ['id', 'name', 'amount', 'currency', 'status', 'pipeline_stage_id'],
                fields: [
                    ['name', 'Nom', 'text', true],
                    ['amount', 'Montant', 'number'],
                    ['currency', 'Devise', 'text'],
                    ['close_date', 'Date de cloture', 'date'],
                    ['pipeline_id', 'ID pipeline', 'number', true],
                    ['pipeline_stage_id', 'ID etape', 'number', true],
                    ['company_id', 'ID entreprise', 'number'],
                    ['contact_id', 'ID contact', 'number'],
                ],
                details: ['name', 'amount', 'currency', 'status', 'close_date', 'company_id', 'contact_id', 'pipeline_id', 'pipeline_stage_id'],
            },
            activities: {
                label: 'Activites',
                icon: 'AC',
                endpoint: '/activities',
                title: row => row.title,
                subtitle: row => `${row.type} - ${row.status}`,
                columns: ['id', 'type', 'title', 'status', 'due_at'],
                fields: [
                    ['type', 'Type', 'select', true, ['note', 'task', 'call', 'email']],
                    ['title', 'Titre', 'text', true],
                    ['body', 'Description', 'textarea'],
                    ['status', 'Statut', 'select', false, ['open', 'done', 'cancelled']],
                    ['due_at', 'Echeance', 'datetime-local'],
                ],
                details: ['type', 'title', 'body', 'status', 'due_at', 'completed_at'],
            },
            pipelines: {
                label: 'Pipelines',
                icon: 'PL',
                endpoint: '/pipelines',
                title: row => row.name,
                subtitle: row => row.is_default ? 'Pipeline par defaut' : '',
                columns: ['id', 'name', 'is_default'],
                fields: [
                    ['name', 'Nom', 'text', true],
                    ['is_default', 'Pipeline par defaut', 'select', false, ['0', '1']],
                ],
                details: ['id', 'name', 'is_default'],
            },
            stages: {
                label: 'Etapes',
                icon: 'ET',
                endpoint: '/pipeline-stages',
                title: row => row.name,
                subtitle: row => `Pipeline ${row.pipeline_id} - ${row.probability || 0}%`,
                columns: ['id', 'pipeline_id', 'name', 'position', 'probability'],
                fields: [
                    ['pipeline_id', 'ID pipeline', 'number', true],
                    ['name', 'Nom', 'text', true],
                    ['position', 'Position', 'number'],
                    ['probability', 'Probabilite', 'number'],
                    ['is_won', 'Gagnee', 'select', false, ['0', '1']],
                    ['is_lost', 'Perdue', 'select', false, ['0', '1']],
                ],
                details: ['id', 'pipeline_id', 'name', 'position', 'probability', 'is_won', 'is_lost'],
            },
        };

        const state = {
            token: localStorage.getItem(tokenKey),
            user: null,
            current: 'dashboard',
            mode: 'list',
            rows: [],
            selected: null,
            selectedDetail: null,
            board: null,
            search: '',
            message: '',
            error: '',
            dashboardData: null,
            dueTasks: [],
            globalSearch: '',
            globalResults: null,
        };

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
            }[char]));
        }

        function money(amount, currency = 'EUR') {
            const value = Number(amount || 0);
            return `${value.toLocaleString('fr-FR')} ${currency || 'EUR'}`;
        }

        function isDetailResource() {
            return ['companies', 'contacts', 'deals'].includes(state.current);
        }

        async function request(path, options = {}) {
            const response = await fetch(API + path, {
                ...options,
                headers: {
                    Accept: 'application/json',
                    ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                    ...(state.token ? { Authorization: `Bearer ${state.token}` } : {}),
                    ...(options.headers || {}),
                },
            });
            const text = await response.text();
            const data = text ? JSON.parse(text) : null;
            if (!response.ok) {
                throw new Error(data?.message || `Erreur HTTP ${response.status}`);
            }
            return data;
        }

        function renderLogin() {
            document.getElementById('app').innerHTML = `
                <main class="login-page">
                    <form class="login-box" id="loginForm">
                        <div class="brand"><span class="brand-mark">C</span><span>CRM Ultimate</span></div>
                        <div>
                            <h1>Connexion</h1>
                            <p class="muted">Acces a l'espace CRM B2B</p>
                        </div>
                        <div class="field"><label>Email</label><input id="email" type="email" value="admin@example.com" required></div>
                        <div class="field"><label>Mot de passe</label><input id="password" type="password" value="password" required></div>
                        <button class="btn" type="submit">Se connecter</button>
                        <p id="loginMessage" class="message"></p>
                    </form>
                </main>`;
            document.getElementById('loginForm').addEventListener('submit', login);
        }

        async function login(event) {
            event.preventDefault();
            const message = document.getElementById('loginMessage');
            message.textContent = 'Connexion...';
            try {
                const data = await request('/auth/login', {
                    method: 'POST',
                    body: JSON.stringify({
                        email: document.getElementById('email').value,
                        password: document.getElementById('password').value,
                    }),
                });
                state.token = data.access_token;
                state.user = data.user;
                localStorage.setItem(tokenKey, state.token);
                await navigate('dashboard');
            } catch (error) {
                message.className = 'message error';
                message.textContent = error.message;
            }
        }

        function logout() {
            localStorage.removeItem(tokenKey);
            state.token = null;
            state.user = null;
            renderLogin();
        }

        async function navigate(key) {
            state.current = key;
            state.mode = key === 'deals' ? 'board' : 'list';
            state.search = '';
            state.selected = null;
            state.selectedDetail = null;
            state.message = '';
            state.error = '';
            await loadCurrent();
        }

        async function loadCurrent() {
            try {
                if (!state.user) {
                    state.user = (await request('/auth/me')).data;
                }
                if (state.current === 'dashboard') {
                    await loadDashboard();
                } else if (state.mode === 'board') {
                    await loadBoard();
                } else {
                    await loadList();
                }
                renderShell();
                loadDueTasks();
            } catch (error) {
                if (String(error.message).includes('Invalid') || String(error.message).includes('401')) {
                    logout();
                    return;
                }
                state.error = error.message;
                renderShell();
            }
        }

        async function loadDashboard() {
            state.dashboardData = await request('/dashboard');
        }

        async function loadList() {
            const resource = resources[state.current];
            const params = new URLSearchParams({ per_page: 50 });
            if (state.search) {
                params.set('search', state.search);
            }
            const data = await request(`${resource.endpoint}?${params.toString()}`);
            state.rows = data.data || [];
        }

        async function loadBoard() {
            state.board = await request('/deals/board');
            state.rows = state.board.columns.flatMap(column => column.deals);
        }

        function renderShell() {
            const resource = resources[state.current];
            document.getElementById('app').innerHTML = `
                <div class="shell">
                    <aside class="sidebar">
                        <div class="brand"><span class="brand-mark">C</span><span>CRM Ultimate</span></div>
                        <nav class="nav">
                            ${Object.entries(resources).map(([key, item]) => `
                                <button type="button" class="${key === state.current ? 'active' : ''}" data-nav="${key}">
                                    <span class="nav-icon">${escapeHtml(item.icon || item.label.slice(0, 2).toUpperCase())}</span>
                                    ${escapeHtml(item.label)}
                                </button>`).join('')}
                        </nav>
                    </aside>
                    <main class="main">
                        <header class="topbar">
                            <div>
                                <h1>${escapeHtml(resource.label)}</h1>
                                <p class="muted">Espace commercial unifie</p>
                            </div>
                            <div class="toolbar">
                                <div class="global-search-wrap">
                                    <input id="globalSearch" class="global-search-input" type="search" placeholder="Recherche globale..." value="${escapeHtml(state.globalSearch)}" autocomplete="off">
                                    ${state.globalResults ? renderSearchDropdown(state.globalResults) : ''}
                                </div>
                                ${state.current !== 'dashboard' && state.mode !== 'board' ? `<input id="search" type="search" placeholder="Filtrer..." value="${escapeHtml(state.search)}">` : ''}
                                ${state.current === 'deals' ? renderDealModeTabs() : ''}
                                <div class="task-badge-wrap">
                                    <button class="task-badge-btn" type="button" id="taskBadgeBtn">
                                        📋 Tâches
                                        ${state.dueTasks.length ? `<span class="badge-count ${state.dueTasks.some(t => t.due_at && new Date(t.due_at) < new Date()) ? 'overdue' : ''}">${state.dueTasks.length}</span>` : ''}
                                    </button>
                                    <div id="dueDropdown" style="display:none" class="due-dropdown">${renderDueDropdown()}</div>
                                </div>
                                <span class="user-pill"><span class="user-dot">${escapeHtml((state.user?.name || state.user?.email || 'U').slice(0, 1).toUpperCase())}</span>${escapeHtml(state.user?.email || '')}</span>
                                <button class="btn secondary" type="button" id="refresh">Rafraichir</button>
                                <button class="btn danger" type="button" id="logout">Deconnexion</button>
                            </div>
                        </header>
                        ${renderContent()}
                    </main>
                </div>`;
            bindShellEvents();
        }

        function renderDealModeTabs() {
            return `
                <div class="tabs">
                    <button class="tab ${state.mode === 'board' ? 'active' : ''}" type="button" data-mode="board">Kanban</button>
                    <button class="tab ${state.mode === 'list' ? 'active' : ''}" type="button" data-mode="list">Liste</button>
                </div>`;
        }

        function renderContent() {
            if (state.current === 'dashboard') {
                return renderDashboard();
            }
            if (state.mode === 'board') {
                return renderBoard();
            }
            return renderListLayout();
        }

        function renderDashboard() {
            const d = state.dashboardData;
            if (!d) {
                return '<section class="layout"><div class="panel"><div class="empty">Chargement des indicateurs...</div></div></section>';
            }
            const rate = d.conversion_rate_30d ? Math.round(d.conversion_rate_30d * 100) + '%' : '—';
            const kpis = [
                { label: 'Deals ouverts', value: d.open_deals_count ?? 0 },
                { label: 'Pipeline total', value: money(d.open_deals_value ?? 0, 'EUR') },
                { label: 'Gagnés ce mois', value: d.won_this_month ?? 0 },
                { label: 'Perdus ce mois', value: d.lost_this_month ?? 0 },
                { label: 'Taux conversion 30j', value: rate },
                { label: 'Tâches dues', value: d.activities_due_count ?? 0 },
                { label: 'Tâches en retard', value: d.activities_overdue_count ?? 0 },
            ];
            const byStage = (d.deals_by_stage || []).filter(s => Number(s.count) > 0);
            return `
                <section class="layout">
                    <div class="panel">
                        <div class="panel-head"><h2>Indicateurs</h2><span class="status">Temps reel</span></div>
                        <div class="panel-body split">
                            ${kpis.map(item => `
                                <div class="metric-card">
                                    <span class="muted">${escapeHtml(item.label)}</span>
                                    <h1>${escapeHtml(String(item.value))}</h1>
                                </div>`).join('')}
                        </div>
                    </div>
                    <div class="panel">
                        <div class="panel-head"><h2>Pipeline par étape</h2></div>
                        <div class="panel-body">
                            ${byStage.length ? byStage.map(s => `
                                <div class="kv"><span>${escapeHtml(s.stage)}</span><strong>${escapeHtml(String(s.count))} deal(s) — ${escapeHtml(money(s.value, 'EUR'))}</strong></div>`).join('') : '<div class="empty">Aucun deal ouvert.</div>'}
                        </div>
                    </div>
                    <div class="panel">
                        <div class="panel-head"><h2>Actions rapides</h2></div>
                        <div class="panel-body stack">
                            <button class="btn secondary" type="button" data-nav="companies">Nouvelle entreprise</button>
                            <button class="btn secondary" type="button" data-nav="contacts">Nouveau contact</button>
                            <button class="btn secondary" type="button" data-nav="deals">Pipeline deals</button>
                        </div>
                    </div>
                </section>`;
        }

        function renderListLayout() {
            const resource = resources[state.current];
            const canImport = ['companies', 'contacts', 'deals'].includes(state.current) && ['admin', 'manager'].includes(state.user?.role);
            return `
                <section class="layout">
                    <div class="panel">
                        <div class="panel-head">
                            <h2>Liste</h2>
                            <div style="display:flex;gap:0.5rem;align-items:center">
                                <span class="status">${state.rows.length} element(s)</span>
                                ${canImport ? '<button class="btn secondary" type="button" id="importToggleBtn" style="font-size:0.8rem;padding:0.3rem 0.7rem">↑ Importer CSV</button>' : ''}
                            </div>
                        </div>
                        ${canImport ? renderImportPanel() : ''}
                        <div class="panel-body" style="padding:0">${renderTable(resource)}</div>
                    </div>
                    <aside class="panel">
                        <div class="panel-head">
                            <div class="detail-title">
                                <h2>${state.selected ? 'Fiche detaillee' : 'Creation'}</h2>
                                <p class="muted">${state.selected ? escapeHtml(resource.title(state.selected)) : 'Nouvel enregistrement'}</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            ${state.selected ? renderDetail(resource) : renderForm(resource, 'createForm')}
                            <p class="message ${state.error ? 'error' : ''}">${escapeHtml(state.error || state.message)}</p>
                        </div>
                    </aside>
                </section>`;
        }

        function renderTable(resource) {
            if (!state.rows.length) {
                return '<div class="empty">Aucune donnee pour le moment.</div>';
            }
            return `
                <table>
                    <thead><tr>${resource.columns.map(column => `<th>${escapeHtml(column)}</th>`).join('')}</tr></thead>
                    <tbody>
                        ${state.rows.map(row => `
                            <tr data-select="${row.id}" class="${state.selected?.id === row.id ? 'selected' : ''}">
                                ${resource.columns.map(column => `<td>${escapeHtml(row[column])}</td>`).join('')}
                            </tr>`).join('')}
                    </tbody>
                </table>`;
        }

        function renderDetail(resource) {
            return `
                <div class="stack">
                    <div>
                        ${(resource.details || []).map(key => `
                            <div class="kv"><span>${escapeHtml(key)}</span><strong>${escapeHtml(state.selected[key])}</strong></div>`).join('')}
                    </div>
                    <div class="tabs">
                        <button class="tab active" type="button" data-detail-tab="edit">Modifier</button>
                        ${isDetailResource() ? '<button class="tab" type="button" data-detail-tab="activity">Activite</button>' : ''}
                        ${isDetailResource() ? '<button class="tab" type="button" data-detail-tab="history">Historique</button>' : ''}
                        ${['companies', 'contacts', 'deals'].includes(state.current) ? '<button class="tab" type="button" data-detail-tab="ai">✦ IA</button>' : ''}
                    </div>
                    <div id="detailTab">${renderEditForm(resource)}</div>
                    <button class="btn danger" type="button" id="deleteRecord">Supprimer</button>
                </div>`;
        }

        function renderEditForm(resource) {
            return renderForm(resource, 'editForm', state.selected);
        }

        function renderActivityForm() {
            return `
                <form id="activityForm" class="stack">
                    <div class="split">
                        <div class="field">
                            <label>Type</label>
                            <select name="type" required>
                                <option value="note">note</option>
                                <option value="task">task</option>
                                <option value="call">call</option>
                                <option value="email">email</option>
                            </select>
                        </div>
                        <div class="field"><label>Statut</label><select name="status"><option value="open">open</option><option value="done">done</option></select></div>
                    </div>
                    <div class="field"><label>Titre</label><input name="title" required></div>
                    <div class="field"><label>Description</label><textarea name="body"></textarea></div>
                    <button class="btn" type="submit">Ajouter l'activite</button>
                    ${renderTimeline(state.selectedDetail?.activities || [])}
                </form>`;
        }

        function renderHistory() {
            const logs = state.selectedDetail?.audit_logs || [];
            if (!logs.length) {
                return '<div class="empty">Aucun historique disponible.</div>';
            }
            return renderTimeline(logs.map(log => ({
                type: log.event,
                title: `${log.event} #${log.id}`,
                body: JSON.stringify(log.new_values || {}),
                created_at: log.created_at,
            })));
        }

        function renderTimeline(items) {
            if (!items.length) {
                return '<div class="empty">Aucun element.</div>';
            }
            return `<div class="stack">${items.map(item => `
                <article class="timeline-item">
                    <strong>${escapeHtml(item.title || item.type)}</strong>
                    <span class="muted">${escapeHtml(item.type || item.event || '')} ${escapeHtml(item.status || '')}</span>
                    <p>${escapeHtml(item.body || '')}</p>
                </article>`).join('')}</div>`;
        }

        function renderForm(resource, formId, values = {}) {
            return `
                <form id="${formId}">
                    ${resource.fields.map(([name, label, type, required, options]) => renderField(name, label, type, required, options, values[name])).join('')}
                    <button class="btn" type="submit">${formId === 'editForm' ? 'Enregistrer' : 'Creer'}</button>
                </form>`;
        }

        function renderField(name, label, type, required, options, value = '') {
            if (type === 'textarea') {
                return `<div class="field"><label>${escapeHtml(label)}</label><textarea name="${name}" ${required ? 'required' : ''}>${escapeHtml(value)}</textarea></div>`;
            }
            if (type === 'select') {
                return `
                    <div class="field">
                        <label>${escapeHtml(label)}</label>
                        <select name="${name}" ${required ? 'required' : ''}>
                            <option value=""></option>
                            ${options.map(option => `<option value="${escapeHtml(option)}" ${String(value) === String(option) ? 'selected' : ''}>${escapeHtml(option)}</option>`).join('')}
                        </select>
                    </div>`;
            }
            return `<div class="field"><label>${escapeHtml(label)}</label><input name="${name}" type="${type}" value="${escapeHtml(value)}" ${required ? 'required' : ''}></div>`;
        }

        function renderBoard() {
            if (!state.board) {
                return '<div class="panel"><div class="empty">Chargement du board.</div></div>';
            }
            return `
                <section class="panel">
                    <div class="panel-head">
                        <h2>${escapeHtml(state.board.pipeline.name)}</h2>
                        <button class="btn secondary" type="button" data-mode="list">Voir la liste</button>
                    </div>
                    <div class="panel-body">
                        <div class="board">
                            ${state.board.columns.map(column => renderBoardColumn(column)).join('')}
                        </div>
                    </div>
                </section>`;
        }

        function renderBoardColumn(column) {
            return `
                <div class="column">
                    <div class="column-head">
                        <h3>${escapeHtml(column.stage.name)}</h3>
                        <span class="status">${column.deals.length}</span>
                    </div>
                    ${column.deals.map(deal => `
                        <button class="deal-card" type="button" data-open-deal="${deal.id}">
                            <strong>${escapeHtml(deal.name)}</strong>
                            <span>${escapeHtml(money(deal.amount, deal.currency))}</span>
                            <span class="muted">${escapeHtml(deal.company?.name || deal.contact?.email || '')}</span>
                            <div class="move-row">
                                <select data-move-select="${deal.id}">
                                    ${state.board.columns.map(target => `<option value="${target.stage.id}" ${target.stage.id === deal.pipeline_stage_id ? 'selected' : ''}>${escapeHtml(target.stage.name)}</option>`).join('')}
                                </select>
                                <span class="status">${escapeHtml(deal.status || 'open')}</span>
                            </div>
                        </button>`).join('')}
                </div>`;
        }

        function bindShellEvents() {
            document.querySelectorAll('[data-nav]').forEach(button => {
                button.addEventListener('click', () => navigate(button.dataset.nav));
            });
            document.querySelectorAll('[data-mode]').forEach(button => {
                button.addEventListener('click', async () => {
                    state.mode = button.dataset.mode;
                    state.selected = null;
                    await loadCurrent();
                });
            });
            document.getElementById('refresh')?.addEventListener('click', loadCurrent);
            document.getElementById('logout')?.addEventListener('click', logout);
            document.getElementById('globalSearch')?.addEventListener('input', debounce(async event => {
                state.globalSearch = event.target.value;
                if (state.globalSearch.length < 2) {
                    state.globalResults = null;
                    renderShell();
                    return;
                }
                try {
                    state.globalResults = await request(`/search?q=${encodeURIComponent(state.globalSearch)}`);
                } catch (_) {
                    state.globalResults = null;
                }
                renderShell();
            }, 300));
            document.getElementById('globalSearch')?.addEventListener('blur', () => {
                setTimeout(() => { state.globalResults = null; renderShell(); }, 200);
            });
            document.getElementById('taskBadgeBtn')?.addEventListener('click', event => {
                event.stopPropagation();
                const dd = document.getElementById('dueDropdown');
                if (dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
            });
            document.addEventListener('click', () => {
                const dd = document.getElementById('dueDropdown');
                if (dd) dd.style.display = 'none';
            }, { once: true });
            document.getElementById('search')?.addEventListener('input', debounce(async event => {
                state.search = event.target.value;
                await loadList();
                renderShell();
            }, 350));
            document.getElementById('importToggleBtn')?.addEventListener('click', () => {
                const panel = document.getElementById('importPanel');
                if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            });
            document.getElementById('importForm')?.addEventListener('submit', submitImport);
            document.getElementById('createForm')?.addEventListener('submit', createRecord);
            document.getElementById('editForm')?.addEventListener('submit', updateRecord);
            document.getElementById('activityForm')?.addEventListener('submit', createActivity);
            document.getElementById('deleteRecord')?.addEventListener('click', deleteRecord);
            document.querySelectorAll('[data-select]').forEach(row => {
                row.addEventListener('click', () => selectRecord(Number(row.dataset.select)));
            });
            document.querySelectorAll('[data-search-nav]').forEach(item => {
                item.addEventListener('click', async () => {
                    const navKey = item.dataset.searchNav;
                    const id = Number(item.dataset.searchId);
                    state.globalSearch = '';
                    state.globalResults = null;
                    await navigate(navKey);
                    await selectRecord(id);
                });
            });
            document.querySelectorAll('[data-detail-tab]').forEach(tab => {
                tab.addEventListener('click', () => switchDetailTab(tab.dataset.detailTab));
            });
            document.querySelectorAll('[data-open-deal]').forEach(card => {
                card.addEventListener('click', event => {
                    if (event.target.matches('select')) {
                        return;
                    }
                    state.mode = 'list';
                    selectRecord(Number(card.dataset.openDeal));
                });
            });
            document.querySelectorAll('[data-move-select]').forEach(select => {
                select.addEventListener('change', event => moveDeal(Number(select.dataset.moveSelect), Number(event.target.value)));
            });
        }

        async function selectRecord(id) {
            const resource = resources[state.current];
            state.selected = state.rows.find(row => row.id === id) || null;
            if (state.selected && isDetailResource()) {
                state.selectedDetail = await request(`${resource.endpoint}/${id}`);
                state.selected = state.selectedDetail.data;
            } else {
                state.selectedDetail = null;
            }
            renderShell();
        }

        async function switchDetailTab(tab) {
            const target = document.getElementById('detailTab');
            document.querySelectorAll('[data-detail-tab]').forEach(button => button.classList.toggle('active', button.dataset.detailTab === tab));
            if (tab === 'activity') {
                target.innerHTML = renderActivityForm();
                document.getElementById('activityForm').addEventListener('submit', createActivity);
            } else if (tab === 'history') {
                target.innerHTML = renderHistory();
            } else if (tab === 'ai') {
                target.innerHTML = renderAiPanel();
                bindAiButtons();
            } else {
                target.innerHTML = renderEditForm(resources[state.current]);
                document.getElementById('editForm').addEventListener('submit', updateRecord);
            }
        }

        function formPayload(form) {
            const payload = {};
            const formData = new FormData(form);
            for (const [key, value] of formData.entries()) {
                if (value === '') {
                    continue;
                }
                const element = form.elements[key];
                payload[key] = element.type === 'number' ? Number(value) : value;
            }
            return payload;
        }

        async function createRecord(event) {
            event.preventDefault();
            try {
                await request(resources[state.current].endpoint, {
                    method: 'POST',
                    body: JSON.stringify(formPayload(event.target)),
                });
                state.message = 'Creation effectuee.';
                await loadCurrent();
            } catch (error) {
                state.error = error.message;
                renderShell();
            }
        }

        async function updateRecord(event) {
            event.preventDefault();
            try {
                const resource = resources[state.current];
                await request(`${resource.endpoint}/${state.selected.id}`, {
                    method: 'PATCH',
                    body: JSON.stringify(formPayload(event.target)),
                });
                state.message = 'Modification enregistree.';
                await loadCurrent();
            } catch (error) {
                state.error = error.message;
                renderShell();
            }
        }

        async function deleteRecord() {
            if (!state.selected || !confirm('Supprimer cet enregistrement ?')) {
                return;
            }
            try {
                await request(`${resources[state.current].endpoint}/${state.selected.id}`, { method: 'DELETE' });
                state.selected = null;
                state.selectedDetail = null;
                state.message = 'Suppression effectuee.';
                await loadCurrent();
            } catch (error) {
                state.error = error.message;
                renderShell();
            }
        }

        async function createActivity(event) {
            event.preventDefault();
            try {
                await request('/activities', {
                    method: 'POST',
                    body: JSON.stringify({
                        ...formPayload(event.target),
                        subject_type: subjectTypes[state.current],
                        subject_id: state.selected.id,
                    }),
                });
                state.selectedDetail = await request(`${resources[state.current].endpoint}/${state.selected.id}`);
                state.message = 'Activite ajoutee.';
                renderShell();
            } catch (error) {
                state.error = error.message;
                renderShell();
            }
        }

        async function moveDeal(dealId, stageId) {
            try {
                await request(`/deals/${dealId}/move`, {
                    method: 'POST',
                    body: JSON.stringify({ pipeline_stage_id: stageId }),
                });
                await loadBoard();
                renderShell();
            } catch (error) {
                state.error = error.message;
                renderShell();
            }
        }

        function renderImportPanel() {
            const entityType = { companies: 'company', contacts: 'contact', deals: 'deal' }[state.current] || 'company';
            return `
                <div id="importPanel" class="import-panel" style="display:none">
                    <form id="importForm" style="display:flex;align-items:flex-end;gap:0.75rem;flex-wrap:wrap">
                        <div class="field" style="margin:0;flex:1;min-width:200px">
                            <label style="font-size:0.8rem">Fichier CSV</label>
                            <input type="file" name="file" accept=".csv,.txt" required>
                        </div>
                        <input type="hidden" name="entity_type" value="${entityType}">
                        <button class="btn" type="submit" style="white-space:nowrap">Lancer l'import</button>
                        <span id="importMsg" class="muted" style="font-size:0.82rem"></span>
                    </form>
                </div>`;
        }

        async function submitImport(event) {
            event.preventDefault();
            const msg = document.getElementById('importMsg');
            const btn = event.target.querySelector('button[type=submit]');
            msg.style.color = 'var(--muted)';
            msg.textContent = 'Import en cours...';
            btn.disabled = true;
            try {
                const data = await request('/imports', { method: 'POST', body: new FormData(event.target) });
                msg.style.color = 'var(--ok)';
                msg.textContent = `Job #${data.data.id} créé — ${data.data.status}. Rafraîchissez dans quelques secondes.`;
                event.target.reset();
            } catch (err) {
                msg.style.color = 'var(--danger)';
                msg.textContent = `Erreur : ${err.message}`;
            } finally {
                btn.disabled = false;
            }
        }

        function debounce(callback, delay) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => callback(...args), delay);
            };
        }

        async function loadDueTasks() {
            if (!state.token) return;
            try {
                const data = await request('/activities/due');
                state.dueTasks = data.data || [];
                const btn = document.getElementById('taskBadgeBtn');
                if (btn) {
                    const overdue = state.dueTasks.some(t => t.due_at && new Date(t.due_at) < new Date());
                    const count = state.dueTasks.length;
                    const badge = count ? `<span class="badge-count ${overdue ? 'overdue' : ''}">${count}</span>` : '';
                    btn.innerHTML = `📋 Tâches ${badge}`;
                }
            } catch (_) {}
        }

        function renderDueDropdown() {
            if (!state.dueTasks.length) return '<div class="due-empty">Aucune tâche en attente.</div>';
            return state.dueTasks.slice(0, 10).map(t => {
                const isOverdue = t.due_at && new Date(t.due_at) < new Date();
                const dateStr = t.due_at ? new Date(t.due_at).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'Sans échéance';
                return `<div class="due-task-item">
                    <div class="due-task-title">${escapeHtml(t.title)}</div>
                    <div class="due-task-date ${isOverdue ? 'overdue' : ''}">${isOverdue ? '⚠ En retard — ' : ''}${escapeHtml(dateStr)}</div>
                </div>`;
            }).join('');
        }

        function renderSearchDropdown(results) {
            const companies = results.companies || [];
            const contacts = results.contacts || [];
            const deals = results.deals || [];
            if (!companies.length && !contacts.length && !deals.length) {
                return `<div class="search-dropdown"><div class="search-empty">Aucun résultat pour "${escapeHtml(state.globalSearch)}"</div></div>`;
            }
            const section = (title, icon, items, navKey, labelFn, subFn) => {
                if (!items.length) return '';
                return `<div class="search-section-title">${escapeHtml(title)}</div>` +
                    items.map(item => `
                        <div class="search-result-item" data-search-nav="${navKey}" data-search-id="${item.id}">
                            <span class="search-result-icon">${escapeHtml(icon)}</span>
                            <div><div>${escapeHtml(labelFn(item))}</div><div class="muted" style="font-size:0.76rem">${escapeHtml(subFn(item))}</div></div>
                        </div>`).join('');
            };
            return `<div class="search-dropdown">
                ${section('Entreprises', 'CO', companies, 'companies', c => c.name, c => c.domain || c.industry || '')}
                ${section('Contacts', 'CT', contacts, 'contacts', c => `${c.first_name} ${c.last_name || ''}`.trim(), c => c.email || c.job_title || '')}
                ${section('Deals', 'DL', deals, 'deals', d => d.name, d => `${money(d.amount, d.currency)} — ${d.status || 'open'}`)}
            </div>`;
        }

        function renderAiPanel() {
            const isDeal = state.current === 'deals';
            const isContact = state.current === 'contacts';
            return `
                <div class="ai-tab-content">
                    <div class="ai-buttons">
                        <button class="ai-btn" type="button" id="aiSummarizeBtn">✦ Résumer</button>
                        ${isDeal ? '<button class="ai-btn" type="button" id="aiNextActionBtn">→ Prochaine action</button>' : ''}
                        ${isDeal ? '<button class="ai-btn" type="button" id="aiScoreBtn">◎ Score commercial</button>' : ''}
                    </div>
                    <div id="aiResult"></div>
                </div>`;
        }

        function bindAiButtons() {
            const entity = state.current === 'deals' ? 'deal' : 'contact';
            const id = state.selected?.id;
            document.getElementById('aiSummarizeBtn')?.addEventListener('click', () => aiCall(`/ai/summarize/${entity}/${id}`, 'summarize'));
            document.getElementById('aiNextActionBtn')?.addEventListener('click', () => aiCall(`/ai/next-action/deal/${id}`, 'next-action'));
            document.getElementById('aiScoreBtn')?.addEventListener('click', () => aiCall(`/ai/score/deal/${id}`, 'score'));
        }

        async function aiCall(endpoint, type) {
            const resultDiv = document.getElementById('aiResult');
            if (!resultDiv) return;
            resultDiv.innerHTML = '<div class="ai-loading">Génération en cours...</div>';
            document.querySelectorAll('.ai-btn').forEach(b => b.disabled = true);
            try {
                const data = await request(endpoint, { method: 'POST' });
                const cached = data.cached ? '<div class="ai-cached-note">Résultat depuis le cache</div>' : '';
                if (type === 'score') {
                    resultDiv.innerHTML = renderScorePanel(data.data) + cached;
                } else if (type === 'next-action') {
                    resultDiv.innerHTML = renderNextActionPanel(data.data) + cached;
                } else {
                    resultDiv.innerHTML = `<div class="ai-result-wrap">${escapeHtml(String(data.data || ''))}</div>${cached}`;
                }
            } catch (err) {
                resultDiv.innerHTML = `<div class="ai-result-wrap" style="color:var(--danger)">Erreur : ${escapeHtml(err.message)}</div>`;
            } finally {
                document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
            }
        }

        function renderScorePanel(score) {
            if (typeof score !== 'object' || !score) return `<div class="ai-result-wrap">${escapeHtml(String(score))}</div>`;
            const value = Number(score.score) || 0;
            const trend = score.trend || 'stable';
            const reasons = Array.isArray(score.reasons) ? score.reasons : [];
            const color = value >= 70 ? 'var(--ok)' : value >= 40 ? 'var(--brand)' : 'var(--danger)';
            const trendIcon = trend === 'warming' ? '↑ Chaud' : trend === 'cooling' ? '↓ Froid' : '→ Stable';
            return `
                <div class="ai-result-wrap score-gauge-wrap">
                    <div class="score-label"><strong>${value}/100</strong><span>${escapeHtml(trendIcon)}</span></div>
                    <div class="score-track"><div class="score-bar" style="width:${value}%;background:${color}"></div></div>
                    ${reasons.length ? `<ul class="score-reasons">${reasons.map(r => `<li>${escapeHtml(r)}</li>`).join('')}</ul>` : ''}
                </div>`;
        }

        function renderNextActionPanel(data) {
            if (typeof data !== 'object' || !data) return `<div class="ai-result-wrap">${escapeHtml(String(data))}</div>`;
            const priorityColor = data.priority === 'high' ? 'var(--danger)' : data.priority === 'medium' ? 'var(--warn)' : 'var(--accent)';
            return `
                <div class="ai-result-wrap stack">
                    <div><strong>${escapeHtml(data.action || '')}</strong> <span style="color:${priorityColor};font-size:0.76rem;font-weight:700;text-transform:uppercase">${escapeHtml(data.priority || '')}</span></div>
                    ${data.rationale ? `<div style="color:var(--muted);font-size:0.82rem">${escapeHtml(data.rationale)}</div>` : ''}
                </div>`;
        }

        if (state.token) {
            loadCurrent();
        } else {
            renderLogin();
        }
    </script>
</body>
</html>
