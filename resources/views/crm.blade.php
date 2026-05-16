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
        /* ── Import mapping ── */
        .import-step-bar { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--muted); margin-bottom: 0.65rem; }
        .import-step-bar .step { padding: 0.2rem 0.55rem; border-radius: 99px; border: 1px solid var(--line); }
        .import-step-bar .step.active { background: var(--brand); color: #fff; border-color: var(--brand); font-weight: 700; }
        .mapping-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; margin-top: 0.4rem; }
        .mapping-table th { text-align: left; padding: 0.3rem 0.5rem; color: var(--muted); font-weight: 600; border-bottom: 1px solid var(--line); font-size: 0.73rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .mapping-table td { padding: 0.3rem 0.5rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .mapping-table select { font-size: 0.79rem; padding: 0.18rem 0.4rem; border: 1px solid var(--line); border-radius: 4px; background: var(--panel); max-width: 200px; width: 100%; }
        .mapping-table .sample-val { color: var(--muted); font-size: 0.74rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mapping-actions { display: flex; gap: 0.5rem; margin-top: 0.7rem; align-items: center; flex-wrap: wrap; }
        /* ── Custom field inputs in forms ── */
        .custom-fields-divider { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin: 0.85rem 0 0.35rem; border-top: 1px solid var(--line); padding-top: 0.65rem; }
        /* ── Detail page 3-column layout ── */
        .layout-detail { display:grid; grid-template-columns:300px 1fr 320px; gap:16px; align-items:start; }
        @media (max-width:1200px) { .layout-detail { grid-template-columns:1fr; } }
        .detail-breadcrumb { display:flex; align-items:center; gap:8px; font-size:0.82rem; color:var(--muted); margin-bottom:16px; }
        .detail-breadcrumb button { background:none; border:none; padding:0; color:var(--brand); cursor:pointer; font-size:0.82rem; font-weight:700; }
        .detail-breadcrumb button:hover { text-decoration:underline; }
        /* ── Lifecycle badge ── */
        .lifecycle-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; }
        .lc-lead { background:#e0f2fe; color:#0369a1; } .lc-mql { background:#fef9c3; color:#854d0e; } .lc-sql { background:#fed7aa; color:#9a3412; }
        .lc-opportunity { background:#ddd6fe; color:#5b21b6; } .lc-customer { background:#dcfce7; color:#15803d; }
        .lc-evangelist { background:#fce7f3; color:#9d174d; } .lc-other { background:var(--soft); color:var(--muted); }
        /* ── Association chips ── */
        .assoc-list { display:flex; flex-direction:column; gap:6px; }
        .assoc-chip { display:flex; align-items:center; justify-content:space-between; background:var(--soft); border:1px solid var(--line); border-radius:8px; padding:8px 10px; gap:6px; }
        .assoc-chip-name { font-weight:600; font-size:0.85rem; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:pointer; }
        .assoc-chip-name:hover { color:var(--brand); }
        .assoc-chip-role { font-size:0.7rem; color:var(--muted); background:var(--panel); border:1px solid var(--line); border-radius:12px; padding:1px 7px; white-space:nowrap; }
        .assoc-chip-del { background:none; border:none; color:var(--danger); cursor:pointer; font-size:0.85rem; padding:2px 4px; opacity:0.6; flex-shrink:0; }
        .assoc-chip-del:hover { opacity:1; }
        .assoc-section-title { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--muted); margin:14px 0 6px; }
        .assoc-section-title:first-child { margin-top:0; }
        .assoc-add-btn { width:100%; background:none; border:1px dashed var(--line); border-radius:8px; padding:6px; color:var(--muted); font-size:0.78rem; cursor:pointer; margin-top:4px; }
        .assoc-add-btn:hover { border-color:var(--brand); color:var(--brand); }
        /* ── Modal ── */
        .modal-overlay { position:fixed; inset:0; background:rgba(23,32,51,.52); z-index:1000; display:flex; align-items:center; justify-content:center; padding:24px; }
        .modal-box { background:var(--panel); border-radius:12px; box-shadow:var(--shadow); width:min(600px,100%); max-height:90vh; overflow-y:auto; display:flex; flex-direction:column; }
        .modal-box.narrow { width:min(440px,100%); }
        .modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--line); }
        .modal-header h3 { margin:0; font-size:1rem; }
        .modal-close { background:none; border:none; font-size:1.3rem; cursor:pointer; color:var(--muted); padding:2px 8px; border-radius:4px; line-height:1; }
        .modal-close:hover { background:var(--soft); }
        .modal-body { padding:20px; flex:1; overflow-y:auto; }
        .modal-footer { display:flex; gap:8px; justify-content:flex-end; padding:14px 20px; border-top:1px solid var(--line); flex-shrink:0; }
        /* ── Autocomplete ── */
        .autocomplete-wrap { position:relative; }
        .autocomplete-dropdown { position:absolute; top:100%; left:0; right:0; background:var(--panel); border:1px solid var(--line); border-radius:8px; box-shadow:var(--shadow-soft); z-index:600; max-height:200px; overflow-y:auto; margin-top:2px; }
        .autocomplete-item { padding:8px 12px; cursor:pointer; font-size:0.85rem; display:flex; flex-direction:column; gap:2px; border-bottom:1px solid var(--line); }
        .autocomplete-item:last-child { border-bottom:none; }
        .autocomplete-item:hover { background:var(--soft); }
        .autocomplete-tags { display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; }
        .autocomplete-tag { display:inline-flex; align-items:center; gap:4px; background:var(--accent-soft); border:1px solid var(--accent); color:var(--accent); border-radius:20px; padding:2px 8px; font-size:0.76rem; font-weight:600; }
        .autocomplete-tag button { background:none; border:none; color:var(--accent); cursor:pointer; padding:0 2px; font-size:1rem; line-height:1; }
        /* ── "Nouveau deal" sidebar shortcut ── */
        .sidebar-deal-btn { width:100%; margin-top:8px; background:rgba(255,107,53,.15); border:1px solid rgba(255,107,53,.35); color:var(--brand); border-radius:8px; padding:8px 11px; text-align:left; display:flex; align-items:center; gap:8px; font-weight:700; font-size:0.82rem; cursor:pointer; }
        .sidebar-deal-btn:hover { background:rgba(255,107,53,.25); }
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
                columns: ['id', 'name', 'domain', 'industry', 'lifecycle_stage', 'city'],
                fields: [
                    ['name', 'Nom', 'text', true],
                    ['domain', 'Domaine', 'text'],
                    ['industry', 'Secteur', 'text'],
                    ['phone', 'Telephone', 'text'],
                    ['website', 'Site web', 'url'],
                    ['city', 'Ville', 'text'],
                    ['country', 'Pays', 'text'],
                ],
                details: ['name', 'domain', 'industry', 'phone', 'website', 'city', 'country', 'lifecycle_stage'],
            },
            contacts: {
                label: 'Contacts',
                icon: 'CT',
                endpoint: '/contacts',
                title: row => [row.first_name, row.last_name].filter(Boolean).join(' '),
                subtitle: row => [row.email, row.job_title].filter(Boolean).join(' - '),
                columns: ['id', 'first_name', 'last_name', 'email', 'lifecycle_stage', 'job_title'],
                fields: [
                    ['first_name', 'Prenom', 'text', true],
                    ['last_name', 'Nom', 'text'],
                    ['email', 'Email', 'email'],
                    ['phone', 'Telephone', 'text'],
                    ['job_title', 'Poste', 'text'],
                ],
                details: ['first_name', 'last_name', 'email', 'phone', 'job_title', 'lifecycle_stage'],
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
                ],
                details: ['name', 'amount', 'currency', 'status', 'close_date', 'pipeline_id', 'pipeline_stage_id'],
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
                adminOnly: true,
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
            'custom-fields': {
                label: 'Champs perso',
                icon: 'CF',
                adminOnly: true,
                endpoint: '/custom-fields',
                title: row => `${row.entity_type}: ${row.label}`,
                subtitle: row => `${row.field_type}${row.is_required ? ' (requis)' : ''}`,
                columns: ['id', 'entity_type', 'key', 'label', 'field_type'],
                fields: [
                    ['entity_type', 'Entite', 'select', true, ['company', 'contact', 'deal']],
                    ['key', 'Cle (snake_case)', 'text', true],
                    ['label', 'Libelle', 'text', true],
                    ['field_type', 'Type', 'select', true, ['text', 'number', 'date', 'boolean', 'select']],
                    ['is_required', 'Requis', 'select', false, ['0', '1']],
                    ['position', 'Position', 'number'],
                ],
                details: ['id', 'entity_type', 'key', 'label', 'field_type', 'is_required', 'position'],
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
            importPreview: null,
            customFieldsCache: {},
            totalRows: 0,
            currentPage: 1,
            detailId: null,
            detailData: null,
            detailActiveTab: 'overview',
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
            state.detailId = null;
            state.detailData = null;
            state.detailActiveTab = 'overview';
            state.message = '';
            state.error = '';
            state.importPreview = null;
            state.currentPage = 1;
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
                } else if (state.mode === 'detail' && state.detailId) {
                    const resource = resources[state.current];
                    state.detailData = await request(`${resource.endpoint}/${state.detailId}`);
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
            const params = new URLSearchParams({ per_page: 100, page: state.currentPage });
            if (state.search) {
                params.set('search', state.search);
                state.currentPage = 1;
                params.set('page', 1);
            }
            const data = await request(`${resource.endpoint}?${params.toString()}`);
            state.rows = data.data || [];
            state.totalRows = data.total ?? data.data?.length ?? 0;

            const entityType = { companies: 'company', contacts: 'contact', deals: 'deal' }[state.current];
            if (entityType && !state.customFieldsCache[entityType]) {
                loadCustomFields(entityType);
            }
        }

        async function loadCustomFields(entityType) {
            try {
                const data = await request(`/custom-fields?entity_type=${entityType}&per_page=100`);
                state.customFieldsCache[entityType] = data.data || [];
            } catch (_) {
                state.customFieldsCache[entityType] = [];
            }
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
                            ${Object.entries(resources).filter(([, item]) => !item.adminOnly || ['admin', 'manager'].includes(state.user?.role)).map(([key, item]) => `
                                <button type="button" class="${key === state.current ? 'active' : ''}" data-nav="${key}">
                                    <span class="nav-icon">${escapeHtml(item.icon || item.label.slice(0, 2).toUpperCase())}</span>
                                    ${escapeHtml(item.label)}
                                </button>`).join('')}
                        </nav>
                        <button class="sidebar-deal-btn" type="button" id="sidebarNewDealBtn">
                            <span>+</span> Nouveau deal
                        </button>
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
                                <button class="btn secondary" type="button" id="newDealTopBtn">+ Deal</button>
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
            if (state.mode === 'detail') {
                return renderDetailPage();
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
            const isMainEntity = ['companies', 'contacts', 'deals'].includes(state.current);
            const canImport = isMainEntity && ['admin', 'manager'].includes(state.user?.role);
            const showSelected = !isMainEntity && state.selected;
            return `
                <section class="layout">
                    <div class="panel">
                        <div class="panel-head">
                            <h2>Liste</h2>
                            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                                <span class="status">
                                    ${state.rows.length} / ${state.totalRows} element(s)
                                    ${state.totalRows > 100 ? `— page ${state.currentPage} / ${Math.ceil(state.totalRows / 100)}` : ''}
                                </span>
                                ${state.totalRows > 100 ? `
                                    <button class="btn secondary" type="button" id="prevPageBtn" ${state.currentPage <= 1 ? 'disabled' : ''} style="font-size:0.78rem;padding:0.25rem 0.5rem">←</button>
                                    <button class="btn secondary" type="button" id="nextPageBtn" ${state.rows.length < 100 ? 'disabled' : ''} style="font-size:0.78rem;padding:0.25rem 0.5rem">→</button>
                                ` : ''}
                                ${canImport ? '<button class="btn secondary" type="button" id="importToggleBtn" style="font-size:0.8rem;padding:0.3rem 0.7rem">↑ Importer CSV</button>' : ''}
                                ${isMainEntity ? '<span class="muted" style="font-size:0.78rem">Cliquer sur une ligne pour ouvrir la fiche</span>' : ''}
                            </div>
                        </div>
                        ${canImport ? renderImportPanel() : ''}
                        <div class="panel-body" style="padding:0">${renderTable(resource)}</div>
                    </div>
                    <aside class="panel">
                        <div class="panel-head">
                            <div class="detail-title">
                                <h2>${showSelected ? 'Fiche detaillee' : 'Creation'}</h2>
                                <p class="muted">${showSelected ? escapeHtml(resource.title(state.selected)) : 'Nouvel enregistrement'}</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            ${showSelected ? renderDetail(resource) : renderForm(resource, 'createForm')}
                            <p class="message ${state.error ? 'error' : ''}">${escapeHtml(state.error || state.message)}</p>
                        </div>
                    </aside>
                </section>`;
        }

        function renderCell(column, value) {
            if (column === 'lifecycle_stage' && value) {
                const labels = { lead: 'Lead', mql: 'MQL', sql: 'SQL', opportunity: 'Opportunité', customer: 'Client', evangelist: 'Évangéliste', other: 'Autre' };
                return `<span class="lifecycle-badge lc-${escapeHtml(value)}">${escapeHtml(labels[value] || value)}</span>`;
            }
            return escapeHtml(value ?? '');
        }

        function renderTable(resource) {
            if (!state.rows.length) {
                return '<div class="empty">Aucune donnee pour le moment.</div>';
            }
            const isMainEntity = ['companies', 'contacts', 'deals'].includes(state.current);
            const selectAttr = isMainEntity ? 'data-open-detail' : 'data-select';
            return `
                <table>
                    <thead><tr>${resource.columns.map(column => `<th>${escapeHtml(column)}</th>`).join('')}</tr></thead>
                    <tbody>
                        ${state.rows.map(row => `
                            <tr ${selectAttr}="${row.id}" class="${state.selected?.id === row.id ? 'selected' : ''}">
                                ${resource.columns.map(column => `<td>${renderCell(column, row[column])}</td>`).join('')}
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
            const detailSource = state.detailData || state.selectedDetail;
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
                    ${renderTimeline(detailSource?.activities || [])}
                </form>`;
        }

        function renderHistory() {
            const logs = (state.detailData || state.selectedDetail)?.audit_logs || [];
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
            const entityType = { companies: 'company', contacts: 'contact', deals: 'deal' }[state.current];
            const customFields = entityType ? (state.customFieldsCache[entityType] || []) : [];
            const customValues = values.custom_values || {};
            const customFieldsHtml = customFields.length
                ? `<div class="custom-fields-divider">Champs personnalisés</div>${customFields.map(cf => renderCustomField(cf, customValues[cf.key])).join('')}`
                : '';
            return `
                <form id="${formId}">
                    ${resource.fields.map(([name, label, type, required, options]) => renderField(name, label, type, required, options, values[name])).join('')}
                    ${customFieldsHtml}
                    <button class="btn" type="submit">${formId === 'editForm' ? 'Enregistrer' : 'Creer'}</button>
                </form>`;
        }

        function renderCustomField(cf, value = '') {
            const inputName = `custom_values[${cf.key}]`;
            if (cf.field_type === 'boolean') {
                return `<div class="field"><label>${escapeHtml(cf.label)}</label><select name="${inputName}"><option value="">—</option><option value="1" ${value == 1 ? 'selected' : ''}>Oui</option><option value="0" ${value == 0 && value !== '' ? 'selected' : ''}>Non</option></select></div>`;
            }
            if (cf.field_type === 'select' && Array.isArray(cf.options)) {
                return `<div class="field"><label>${escapeHtml(cf.label)}</label><select name="${inputName}" ${cf.is_required ? 'required' : ''}><option value=""></option>${cf.options.map(o => `<option value="${escapeHtml(o)}" ${value === o ? 'selected' : ''}>${escapeHtml(o)}</option>`).join('')}</select></div>`;
            }
            const inputType = cf.field_type === 'number' ? 'number' : cf.field_type === 'date' ? 'date' : 'text';
            return `<div class="field"><label>${escapeHtml(cf.label)}</label><input name="${inputName}" type="${inputType}" value="${escapeHtml(value)}" ${cf.is_required ? 'required' : ''}></div>`;
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
                            <span class="muted">${escapeHtml(deal.companies?.[0]?.name || deal.contacts?.[0]?.email || '')}</span>
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
            document.getElementById('prevPageBtn')?.addEventListener('click', async () => {
                if (state.currentPage > 1) { state.currentPage--; await loadList(); renderShell(); }
            });
            document.getElementById('nextPageBtn')?.addEventListener('click', async () => {
                if (state.rows.length >= 100) { state.currentPage++; await loadList(); renderShell(); }
            });
            document.getElementById('importToggleBtn')?.addEventListener('click', () => {
                const panel = document.getElementById('importPanel');
                if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            });
            document.getElementById('importForm')?.addEventListener('submit', previewImport);
            const entityType = { companies: 'company', contacts: 'contact', deals: 'deal' }[state.current];
            if (entityType) bindImportMappingEvents(entityType);
            document.getElementById('createForm')?.addEventListener('submit', createRecord);
            document.getElementById('editForm')?.addEventListener('submit', updateRecord);
            document.getElementById('activityForm')?.addEventListener('submit', createActivity);
            document.getElementById('deleteRecord')?.addEventListener('click', deleteRecord);
            document.querySelectorAll('[data-select]').forEach(row => {
                row.addEventListener('click', () => selectRecord(Number(row.dataset.select)));
            });
            document.querySelectorAll('[data-open-detail]').forEach(row => {
                row.addEventListener('click', () => openDetailPage(Number(row.dataset.openDetail)));
            });
            document.getElementById('sidebarNewDealBtn')?.addEventListener('click', () => openDealModal({}));
            document.getElementById('newDealTopBtn')?.addEventListener('click', () => openDealModal({}));
            document.querySelectorAll('[data-search-nav]').forEach(item => {
                item.addEventListener('click', async () => {
                    const navKey = item.dataset.searchNav;
                    const id = Number(item.dataset.searchId);
                    state.globalSearch = '';
                    state.globalResults = null;
                    state.current = navKey;
                    state.rows = [];
                    if (['companies', 'contacts', 'deals'].includes(navKey)) {
                        await openDetailPage(id);
                    } else {
                        await navigate(navKey);
                        await selectRecord(id);
                    }
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
                    openDetailPage(Number(card.dataset.openDeal));
                });
            });
            document.querySelectorAll('[data-move-select]').forEach(select => {
                select.addEventListener('change', event => moveDeal(Number(select.dataset.moveSelect), Number(event.target.value)));
            });
            if (state.mode === 'detail') {
                bindDetailPageEvents();
            }
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
            state.detailActiveTab = tab;
            const target = document.getElementById('detailTab');
            if (!target) return;
            document.querySelectorAll('[data-detail-tab]').forEach(button => button.classList.toggle('active', button.dataset.detailTab === tab));
            if (tab === 'overview') {
                const record = state.detailData?.data || state.selected;
                target.innerHTML = renderOverviewTab(record, resources[state.current]);
            } else if (tab === 'activity') {
                target.innerHTML = renderActivityForm();
                document.getElementById('activityForm')?.addEventListener('submit', createActivity);
            } else if (tab === 'history') {
                target.innerHTML = renderHistory();
            } else if (tab === 'ai') {
                target.innerHTML = renderAiPanel();
                bindAiButtons();
            } else {
                target.innerHTML = renderEditForm(resources[state.current]);
                document.getElementById('editForm')?.addEventListener('submit', updateRecord);
            }
        }

        function formPayload(form) {
            const payload = {};
            const formData = new FormData(form);
            for (const [key, value] of formData.entries()) {
                if (value === '') {
                    continue;
                }
                const customMatch = key.match(/^custom_values\[(.+)\]$/);
                if (customMatch) {
                    if (!payload.custom_values) payload.custom_values = {};
                    payload.custom_values[customMatch[1]] = value;
                    continue;
                }
                const element = form.elements[key];
                payload[key] = element && element.type === 'number' ? Number(value) : value;
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
                const id = state.detailId || state.selected?.id;
                await request(`${resource.endpoint}/${id}`, {
                    method: 'PATCH',
                    body: JSON.stringify(formPayload(event.target)),
                });
                state.message = 'Modification enregistree.';
                if (state.detailId) {
                    state.detailData = await request(`${resource.endpoint}/${state.detailId}`);
                    renderShell();
                } else {
                    await loadCurrent();
                }
            } catch (error) {
                state.error = error.message;
                renderShell();
            }
        }

        async function deleteRecord() {
            const id = state.detailId || state.selected?.id;
            if (!id || !confirm('Supprimer cet enregistrement ?')) {
                return;
            }
            try {
                await request(`${resources[state.current].endpoint}/${id}`, { method: 'DELETE' });
                state.selected = null;
                state.selectedDetail = null;
                state.message = 'Suppression effectuee.';
                if (state.detailId) {
                    goBackToList();
                } else {
                    await loadCurrent();
                }
            } catch (error) {
                state.error = error.message;
                renderShell();
            }
        }

        async function createActivity(event) {
            event.preventDefault();
            try {
                const subjectId = state.detailId || state.selected?.id;
                await request('/activities', {
                    method: 'POST',
                    body: JSON.stringify({
                        ...formPayload(event.target),
                        subject_type: subjectTypes[state.current],
                        subject_id: subjectId,
                    }),
                });
                const resource = resources[state.current];
                if (state.detailId) {
                    state.detailData = await request(`${resource.endpoint}/${state.detailId}`);
                } else {
                    state.selectedDetail = await request(`${resource.endpoint}/${state.selected.id}`);
                }
                state.message = 'Activite ajoutee.';
                renderShell();
                // Restore activity tab after re-render
                if (state.mode === 'detail') {
                    switchDetailTab('activity');
                }
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
            const p = state.importPreview;
            return `
                <div id="importPanel" class="import-panel" style="display:none">
                    ${p ? renderMappingStep(p, entityType) : renderUploadStep(entityType)}
                </div>`;
        }

        function renderUploadStep(entityType) {
            return `
                <div class="import-step-bar">
                    <span class="step active">1 Upload</span>
                    <span>→</span>
                    <span class="step">2 Mapping</span>
                    <span>→</span>
                    <span class="step">3 Import</span>
                </div>
                <form id="importForm" style="display:flex;align-items:flex-end;gap:0.75rem;flex-wrap:wrap">
                    <div class="field" style="margin:0;flex:1;min-width:200px">
                        <label style="font-size:0.8rem">Fichier CSV</label>
                        <input type="file" name="file" accept=".csv,.txt" required>
                    </div>
                    <input type="hidden" name="entity_type" value="${entityType}">
                    <button class="btn" type="submit" style="white-space:nowrap">Analyser →</button>
                    <span id="importMsg" class="muted" style="font-size:0.82rem"></span>
                </form>`;
        }

        function renderMappingStep(p, entityType) {
            const opts = p.available_fields.map(f => {
                const badge = f.type === 'custom' ? ' (perso)' : f.type === 'virtual' ? ' ✦' : '';
                return `<option value="${escapeHtml(f.key)}">${escapeHtml(f.label)}${badge}</option>`;
            }).join('');
            const rows = p.headers.map((header, i) => {
                const mapped = p.auto_mapping[header] || '';
                const samples = (p.sample_rows || []).map(r => escapeHtml(r[i] || '')).join(', ');
                return `<tr id="map-row-${i}">
                    <td><strong>${escapeHtml(header)}</strong></td>
                    <td>
                        <select data-map-header="${escapeHtml(header)}" data-row-idx="${i}">
                            <option value="">(ignorer)</option>
                            ${opts.replace(`value="${escapeHtml(mapped)}"`, `value="${escapeHtml(mapped)}" selected`)}
                            <option value="__create_new__">+ Créer un champ personnalisé…</option>
                        </select>
                        <div id="create-field-form-${i}" style="display:none;margin-top:0.4rem;background:var(--panel);border:1px solid var(--line);border-radius:6px;padding:0.5rem;font-size:0.79rem">
                            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;align-items:flex-end">
                                <div>
                                    <div style="font-size:0.72rem;color:var(--muted);margin-bottom:2px">Libellé</div>
                                    <input id="cf-label-${i}" type="text" placeholder="ex: Segment" style="width:110px;padding:0.2rem 0.35rem;border:1px solid var(--line);border-radius:4px;font-size:0.79rem">
                                </div>
                                <div>
                                    <div style="font-size:0.72rem;color:var(--muted);margin-bottom:2px">Type</div>
                                    <select id="cf-type-${i}" style="padding:0.2rem 0.35rem;border:1px solid var(--line);border-radius:4px;font-size:0.79rem">
                                        <option value="text">Texte</option>
                                        <option value="number">Nombre</option>
                                        <option value="date">Date</option>
                                        <option value="select">Liste</option>
                                    </select>
                                </div>
                                <button type="button" class="btn" style="padding:0.2rem 0.5rem;font-size:0.79rem" onclick="createCustomFieldFromMapping(${i}, '${escapeHtml(entityType)}', '${escapeHtml(header)}')">Créer</button>
                                <button type="button" class="btn secondary" style="padding:0.2rem 0.5rem;font-size:0.79rem" onclick="cancelCreateField(${i})">Annuler</button>
                            </div>
                            <div id="cf-error-${i}" style="color:var(--danger);font-size:0.75rem;margin-top:0.25rem"></div>
                        </div>
                    </td>
                    <td class="sample-val" title="${samples}">${samples}</td>
                </tr>`;
            }).join('');
            return `
                <div class="import-step-bar">
                    <span class="step">1 Upload</span>
                    <span>→</span>
                    <span class="step active">2 Mapping</span>
                    <span>→</span>
                    <span class="step">3 Import</span>
                </div>
                <table class="mapping-table">
                    <thead><tr><th>Colonne CSV</th><th>Mapper vers</th><th>Aperçu</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="mapping-actions">
                    <button class="btn" type="button" id="importConfirmBtn">Importer →</button>
                    <button class="btn secondary" type="button" id="importResetBtn">← Recommencer</button>
                    <span id="importMsg" class="muted" style="font-size:0.82rem"></span>
                </div>`;
        }

        async function createCustomFieldFromMapping(rowIdx, entityType, csvHeader) {
            const label = document.getElementById(`cf-label-${rowIdx}`)?.value?.trim();
            const fieldType = document.getElementById(`cf-type-${rowIdx}`)?.value;
            const errEl = document.getElementById(`cf-error-${rowIdx}`);
            if (!label) { errEl.textContent = 'Le libellé est requis.'; return; }
            const key = label.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
            try {
                const cf = await request('/custom-fields', {
                    method: 'POST',
                    body: JSON.stringify({ entity_type: entityType, key, label, field_type: fieldType }),
                });
                // Add option to all selects and select it for this row
                const sel = document.querySelector(`[data-map-header="${CSS.escape(csvHeader)}"]`);
                if (sel) {
                    const opt = new Option(`${label} (perso)`, cf.data.key);
                    const createOpt = sel.querySelector('option[value="__create_new__"]');
                    sel.insertBefore(opt, createOpt);
                    sel.value = cf.data.key;
                }
                // Also add to other selects so it's available for other columns
                document.querySelectorAll('[data-map-header]').forEach(s => {
                    if (s !== sel && !s.querySelector(`option[value="${cf.data.key}"]`)) {
                        const o = new Option(`${label} (perso)`, cf.data.key);
                        const co = s.querySelector('option[value="__create_new__"]');
                        s.insertBefore(o, co);
                    }
                });
                document.getElementById(`create-field-form-${rowIdx}`).style.display = 'none';
                errEl.textContent = '';
                if (state.importPreview) {
                    state.importPreview.available_fields.push({ key: cf.data.key, label, type: 'custom' });
                }
            } catch (err) {
                errEl.textContent = err.message;
            }
        }

        function cancelCreateField(rowIdx) {
            document.getElementById(`create-field-form-${rowIdx}`).style.display = 'none';
            const sel = document.querySelectorAll('[data-map-header]')[rowIdx];
            if (sel) sel.value = '';
        }

        async function previewImport(event) {
            event.preventDefault();
            const msg = document.getElementById('importMsg');
            const btn = event.target.querySelector('button[type=submit]');
            msg.style.color = 'var(--muted)';
            msg.textContent = 'Analyse...';
            btn.disabled = true;
            try {
                const data = await request('/imports/preview', { method: 'POST', body: new FormData(event.target) });
                state.importPreview = data;
                const panel = document.getElementById('importPanel');
                if (panel) {
                    panel.innerHTML = renderMappingStep(data, event.target.querySelector('[name=entity_type]').value);
                    bindImportMappingEvents(event.target.querySelector('[name=entity_type]').value);
                }
            } catch (err) {
                msg.style.color = 'var(--danger)';
                msg.textContent = `Erreur : ${err.message}`;
                btn.disabled = false;
            }
        }

        function bindImportMappingEvents(entityType) {
            document.getElementById('importResetBtn')?.addEventListener('click', () => {
                state.importPreview = null;
                const panel = document.getElementById('importPanel');
                if (panel) panel.innerHTML = renderUploadStep(entityType);
                document.getElementById('importForm')?.addEventListener('submit', previewImport);
            });
            document.getElementById('importConfirmBtn')?.addEventListener('click', () => finishImport(entityType));
            // Show inline create-field form when "__ create_new__" is chosen
            document.querySelectorAll('[data-map-header]').forEach(sel => {
                sel.addEventListener('change', () => {
                    const idx = sel.dataset.rowIdx;
                    const form = document.getElementById(`create-field-form-${idx}`);
                    if (form) form.style.display = sel.value === '__create_new__' ? 'block' : 'none';
                    if (sel.value === '__create_new__') sel.value = '';
                });
            });
        }

        async function finishImport(entityType) {
            const msg = document.getElementById('importMsg');
            const btn = document.getElementById('importConfirmBtn');
            msg.style.color = 'var(--muted)';
            msg.textContent = 'Lancement...';
            btn.disabled = true;
            const mapping = {};
            document.querySelectorAll('[data-map-header]').forEach(sel => {
                mapping[sel.dataset.mapHeader] = sel.value || null;
            });
            try {
                const data = await request('/imports', {
                    method: 'POST',
                    body: JSON.stringify({
                        entity_type: entityType,
                        preview_token: state.importPreview.preview_token,
                        mapping,
                    }),
                });
                msg.style.color = 'var(--ok)';
                msg.textContent = `Job #${data.data.id} créé. Rafraîchissez dans quelques instants.`;
                state.importPreview = null;
                btn.disabled = false;
            } catch (err) {
                msg.style.color = 'var(--danger)';
                msg.textContent = `Erreur : ${err.message}`;
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
                    const summaryText = String(data.data || '');
                    resultDiv.innerHTML = `<div class="ai-result-wrap">${escapeHtml(summaryText)}</div>${cached}<button class="btn secondary" type="button" id="saveAiNoteBtn" style="font-size:0.8rem;margin-top:0.5rem">📌 Ajouter comme note</button>`;
                    document.getElementById('saveAiNoteBtn')?.addEventListener('click', () => saveAiNote(summaryText));
                }
            } catch (err) {
                resultDiv.innerHTML = `<div class="ai-result-wrap" style="color:var(--danger)">Erreur : ${escapeHtml(err.message)}</div>`;
            } finally {
                document.querySelectorAll('.ai-btn').forEach(b => b.disabled = false);
            }
        }

        async function saveAiNote(text) {
            const btn = document.getElementById('saveAiNoteBtn');
            if (btn) { btn.disabled = true; btn.textContent = 'Enregistrement...'; }
            try {
                await request('/activities', {
                    method: 'POST',
                    body: JSON.stringify({
                        type: 'note',
                        title: `Résumé IA — ${resources[state.current]?.title(state.selected) || ''}`,
                        body: text,
                        status: 'done',
                        subject_type: subjectTypes[state.current],
                        subject_id: state.selected.id,
                    }),
                });
                if (btn) { btn.textContent = '✓ Note ajoutée'; btn.style.color = 'var(--ok)'; }
            } catch (err) {
                if (btn) { btn.disabled = false; btn.textContent = '📌 Ajouter comme note'; }
                document.getElementById('aiResult')?.insertAdjacentHTML('beforeend', `<div style="color:var(--danger);font-size:0.82rem;margin-top:0.25rem">${escapeHtml(err.message)}</div>`);
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

        // ── Detail page ──────────────────────────────────────────────────────────

        async function openDetailPage(id) {
            const resource = resources[state.current];
            state.mode = 'detail';
            state.detailId = id;
            state.detailActiveTab = 'overview';
            state.error = '';
            state.message = '';
            try {
                state.detailData = await request(`${resource.endpoint}/${id}`);
                const entityType = { companies: 'company', contacts: 'contact', deals: 'deal' }[state.current];
                if (entityType && !state.customFieldsCache[entityType]) {
                    await loadCustomFields(entityType);
                }
                renderShell();
            } catch (err) {
                state.error = err.message;
                state.mode = 'list';
                renderShell();
            }
        }

        function goBackToList() {
            state.mode = state.current === 'deals' ? 'board' : 'list';
            state.detailId = null;
            state.detailData = null;
            state.detailActiveTab = 'overview';
            state.error = '';
            state.message = '';
            if (state.mode === 'board' || !state.rows.length) {
                loadCurrent();
            } else {
                renderShell();
            }
        }

        function renderDetailPage() {
            const resource = resources[state.current];
            const detail = state.detailData;
            if (!detail) {
                return '<div class="panel"><div class="empty">Chargement...</div></div>';
            }
            const record = detail.data;
            return `
                <section>
                    <div class="detail-breadcrumb">
                        <button type="button" id="backToListBtn">← ${escapeHtml(resource.label)}</button>
                        <span>/</span>
                        <span>${escapeHtml(resource.title(record))}</span>
                    </div>
                    <div class="layout-detail">
                        ${renderPropertiesPanel(record, resource)}
                        ${renderDetailCenterPanel(record, resource, detail)}
                        ${renderAssociationsPanel(record, resource)}
                    </div>
                    <p class="message ${state.error ? 'error' : ''}" style="margin-top:8px">${escapeHtml(state.error || state.message)}</p>
                </section>`;
        }

        function renderPropertiesPanel(record, resource) {
            const entityType = { companies: 'company', contacts: 'contact', deals: 'deal' }[state.current];
            const customFields = entityType ? (state.customFieldsCache[entityType] || []) : [];
            const hasLifecycle = ['companies', 'contacts'].includes(state.current);
            const lcStages = { lead: 'Lead', mql: 'MQL', sql: 'SQL', opportunity: 'Opportunité', customer: 'Client', evangelist: 'Évangéliste', other: 'Autre' };
            const lcStatuses = { new: 'Nouveau', open: 'Ouvert', in_progress: 'En cours', connected: 'Connecté', unqualified: 'Non qualifié', bad_fit: 'Hors cible' };
            return `
                <div class="panel">
                    <div class="panel-head"><h2>Propriétés</h2></div>
                    <div class="panel-body">
                        <form id="editForm">
                            ${resource.fields.map(([name, label, type, required, options]) => renderField(name, label, type, required, options, record[name])).join('')}
                            ${hasLifecycle ? `
                                <div class="field">
                                    <label>Lifecycle</label>
                                    <select name="lifecycle_stage">
                                        ${Object.entries(lcStages).map(([k, v]) => `<option value="${k}" ${record.lifecycle_stage === k ? 'selected' : ''}>${escapeHtml(v)}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Statut lead</label>
                                    <select name="lead_status">
                                        <option value="">—</option>
                                        ${Object.entries(lcStatuses).map(([k, v]) => `<option value="${k}" ${record.lead_status === k ? 'selected' : ''}>${escapeHtml(v)}</option>`).join('')}
                                    </select>
                                </div>` : ''}
                            ${customFields.length ? `<div class="custom-fields-divider">Champs personnalisés</div>${customFields.map(cf => renderCustomField(cf, (record.custom_values || {})[cf.key])).join('')}` : ''}
                            <button class="btn" type="submit">Enregistrer</button>
                        </form>
                        <button class="btn danger" type="button" id="deleteRecord" style="width:100%;margin-top:8px">Supprimer</button>
                    </div>
                </div>`;
        }

        function renderDetailCenterPanel(record, resource, detail) {
            const cur = state.current;
            const activeTab = state.detailActiveTab || 'overview';
            let tabContent;
            if (activeTab === 'overview') {
                tabContent = renderOverviewTab(record, resource);
            } else if (activeTab === 'activity') {
                tabContent = renderActivityForm();
            } else if (activeTab === 'history') {
                tabContent = renderHistory();
            } else if (activeTab === 'ai') {
                tabContent = renderAiPanel();
            } else {
                tabContent = renderOverviewTab(record, resource);
            }
            return `
                <div class="panel">
                    <div class="panel-head">
                        <div class="tabs">
                            <button class="tab ${activeTab === 'overview' ? 'active' : ''}" type="button" data-detail-tab="overview">Aperçu</button>
                            <button class="tab ${activeTab === 'activity' ? 'active' : ''}" type="button" data-detail-tab="activity">Activités</button>
                            <button class="tab ${activeTab === 'history' ? 'active' : ''}" type="button" data-detail-tab="history">Historique</button>
                            ${['companies', 'contacts', 'deals'].includes(cur) ? `<button class="tab ${activeTab === 'ai' ? 'active' : ''}" type="button" data-detail-tab="ai">✦ IA</button>` : ''}
                        </div>
                    </div>
                    <div class="panel-body">
                        <div id="detailTab">${tabContent}</div>
                    </div>
                </div>`;
        }

        function renderOverviewTab(record, resource) {
            if (!record) return '<div class="empty">Aucune donnée.</div>';
            const skipKeys = new Set(['company_id', 'contact_id', 'custom_values', 'lifecycle_stage', 'lead_status', 'owner_id', 'updated_at']);
            const items = (resource.details || []).filter(k => !skipKeys.has(k));
            return `<div>
                ${items.map(key => `<div class="kv"><span>${escapeHtml(key)}</span><strong>${escapeHtml(record[key] ?? '')}</strong></div>`).join('')}
                ${record.lifecycle_stage ? `<div class="kv"><span>Lifecycle</span><strong>${renderCell('lifecycle_stage', record.lifecycle_stage)}</strong></div>` : ''}
                ${record.owner ? `<div class="kv"><span>Responsable</span><strong>${escapeHtml(record.owner.name || '')}</strong></div>` : ''}
            </div>`;
        }

        function renderAssociationsPanel(record, resource) {
            const cur = state.current;
            let sections = '';

            if (cur === 'companies') {
                const contacts = record.contacts || [];
                const deals = record.deals || [];
                sections = `
                    <div class="assoc-section-title">Contacts (${contacts.length})</div>
                    <div class="assoc-list">
                        ${contacts.map(c => renderAssocChip(c.id, `${c.first_name || ''} ${c.last_name || ''}`.trim(), c.pivot?.role || 'employee', c.pivot?.is_primary, 'company-contact')).join('') || '<div class="empty" style="padding:6px 0;font-size:0.82rem">Aucun contact</div>'}
                    </div>
                    <button class="assoc-add-btn" type="button" data-add-assoc="company-contact">+ Ajouter un contact</button>
                    <div class="assoc-section-title">Deals (${deals.length})</div>
                    <div class="assoc-list">
                        ${deals.map(d => renderAssocChip(d.id, d.name, d.pivot?.role || 'customer', null, 'company-deal')).join('') || '<div class="empty" style="padding:6px 0;font-size:0.82rem">Aucun deal</div>'}
                    </div>
                    <button class="assoc-add-btn" type="button" data-new-deal-prefill="company">+ Nouveau deal</button>`;
            } else if (cur === 'contacts') {
                const companies = record.companies || [];
                const deals = record.deals || [];
                sections = `
                    <div class="assoc-section-title">Entreprises (${companies.length})</div>
                    <div class="assoc-list">
                        ${companies.map(c => renderAssocChip(c.id, c.name, c.pivot?.role || 'employee', c.pivot?.is_primary, 'contact-company')).join('') || '<div class="empty" style="padding:6px 0;font-size:0.82rem">Aucune entreprise</div>'}
                    </div>
                    <button class="assoc-add-btn" type="button" data-add-assoc="contact-company">+ Ajouter une entreprise</button>
                    <div class="assoc-section-title">Deals (${deals.length})</div>
                    <div class="assoc-list">
                        ${deals.map(d => renderAssocChip(d.id, d.name, d.pivot?.role || 'primary', null, 'contact-deal')).join('') || '<div class="empty" style="padding:6px 0;font-size:0.82rem">Aucun deal</div>'}
                    </div>
                    <button class="assoc-add-btn" type="button" data-new-deal-prefill="contact">+ Nouveau deal</button>`;
            } else if (cur === 'deals') {
                const companies = record.companies || [];
                const contacts = record.contacts || [];
                sections = `
                    <div class="assoc-section-title">Entreprises (${companies.length})</div>
                    <div class="assoc-list">
                        ${companies.map(c => renderAssocChip(c.id, c.name, c.pivot?.role || 'customer', c.pivot?.is_primary, 'deal-company')).join('') || '<div class="empty" style="padding:6px 0;font-size:0.82rem">Aucune entreprise</div>'}
                    </div>
                    <button class="assoc-add-btn" type="button" data-add-assoc="deal-company">+ Ajouter une entreprise</button>
                    <div class="assoc-section-title">Contacts (${contacts.length})</div>
                    <div class="assoc-list">
                        ${contacts.map(c => renderAssocChip(c.id, `${c.first_name || ''} ${c.last_name || ''}`.trim(), c.pivot?.role || 'primary', null, 'deal-contact')).join('') || '<div class="empty" style="padding:6px 0;font-size:0.82rem">Aucun contact</div>'}
                    </div>
                    <button class="assoc-add-btn" type="button" data-add-assoc="deal-contact">+ Ajouter un contact</button>`;
            }

            return `
                <div class="panel">
                    <div class="panel-head"><h2>Associations</h2></div>
                    <div class="panel-body" style="padding:12px 16px">${sections}</div>
                </div>`;
        }

        function renderAssocChip(id, name, role, isPrimary, assocType) {
            const primaryBadge = isPrimary ? '<span class="status" style="font-size:0.62rem;padding:1px 5px;margin-left:4px">Princ.</span>' : '';
            return `<div class="assoc-chip">
                <span class="assoc-chip-name" data-nav-to-detail="${assocType.split('-')[1]}s" data-nav-to-id="${id}">${escapeHtml(name)}${primaryBadge}</span>
                <span class="assoc-chip-role">${escapeHtml(role)}</span>
                <button class="assoc-chip-del" type="button" data-detach-assoc="${escapeHtml(assocType)}" data-detach-id="${id}" title="Détacher">×</button>
            </div>`;
        }

        function bindDetailPageEvents() {
            document.getElementById('backToListBtn')?.addEventListener('click', goBackToList);
            document.getElementById('editForm')?.addEventListener('submit', updateRecord);
            document.getElementById('deleteRecord')?.addEventListener('click', deleteRecord);
            document.querySelectorAll('[data-detail-tab]').forEach(tab => {
                tab.addEventListener('click', () => switchDetailTab(tab.dataset.detailTab));
            });
            if (state.detailActiveTab === 'activity') {
                document.getElementById('activityForm')?.addEventListener('submit', createActivity);
            } else if (state.detailActiveTab === 'ai') {
                bindAiButtons();
            }
            // Association add buttons
            document.querySelectorAll('[data-add-assoc]').forEach(btn => {
                btn.addEventListener('click', () => openAssociationPicker(btn.dataset.addAssoc));
            });
            // Detach buttons
            document.querySelectorAll('[data-detach-assoc]').forEach(btn => {
                btn.addEventListener('click', () => detachAssociation(btn.dataset.detachAssoc, Number(btn.dataset.detachId)));
            });
            // New deal from association panel
            document.querySelectorAll('[data-new-deal-prefill]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const side = btn.dataset.newDealPrefill;
                    const record = state.detailData?.data;
                    const prefill = {};
                    if (side === 'company' && record) prefill.companies = [{ id: record.id, name: record.name }];
                    if (side === 'contact' && record) prefill.contacts = [{ id: record.id, name: `${record.first_name || ''} ${record.last_name || ''}`.trim() }];
                    openDealModal(prefill);
                });
            });
            // Navigate to another record's detail from chip name
            document.querySelectorAll('[data-nav-to-detail]').forEach(el => {
                el.addEventListener('click', () => {
                    const targetResource = el.dataset.navToDetail;
                    const targetId = Number(el.dataset.navToId);
                    if (resources[targetResource]) {
                        state.current = targetResource;
                        state.rows = [];
                        openDetailPage(targetId);
                    }
                });
            });
        }

        async function detachAssociation(assocType, childId) {
            if (!confirm('Détacher cette association ?')) return;
            const parentId = state.detailId;
            let endpoint;
            if (assocType === 'company-contact') endpoint = `/companies/${parentId}/contacts/${childId}`;
            else if (assocType === 'contact-company') endpoint = `/contacts/${parentId}/companies/${childId}`;
            else if (assocType === 'deal-contact') endpoint = `/deals/${parentId}/contacts/${childId}`;
            else if (assocType === 'deal-company') endpoint = `/deals/${parentId}/companies/${childId}`;
            else if (assocType === 'contact-deal') endpoint = `/deals/${childId}/contacts/${parentId}`;
            else if (assocType === 'company-deal') endpoint = `/deals/${childId}/companies/${parentId}`;
            else return;
            try {
                await request(endpoint, { method: 'DELETE' });
                state.detailData = await request(`${resources[state.current].endpoint}/${parentId}`);
                renderShell();
            } catch (err) {
                state.error = err.message;
                renderShell();
            }
        }

        // ── Association picker modal ─────────────────────────────────────────────

        function openAssociationPicker(assocType) {
            const parentId = state.detailId;
            const childType = assocType.split('-')[1];
            const childLabel = { contact: 'contact', company: 'entreprise', deal: 'deal' }[childType] || childType;
            const childEndpoint = { contact: '/contacts', company: '/companies', deal: '/deals' }[childType];
            const childNameFn = childType === 'contact'
                ? r => `${r.first_name || ''} ${r.last_name || ''}`.trim()
                : r => r.name;
            const childSubFn = childType === 'contact'
                ? r => r.email || ''
                : childType === 'company'
                    ? r => r.domain || r.industry || ''
                    : r => money(r.amount, r.currency);

            const roleOptions = {
                'company-contact': ['employee', 'decision_maker', 'influencer', 'former'],
                'contact-company': ['employee', 'decision_maker', 'influencer', 'former'],
                'deal-contact': ['primary', 'technical', 'billing', 'other'],
                'deal-company': ['customer', 'partner', 'reseller'],
                'contact-deal': ['primary', 'technical', 'billing', 'other'],
            }[assocType] || ['employee'];

            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.id = 'assocModal';
            modal.innerHTML = `
                <div class="modal-box narrow">
                    <div class="modal-header">
                        <h3>Associer un ${escapeHtml(childLabel)}</h3>
                        <button class="modal-close" type="button" id="assocModalClose">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="field">
                            <label>Rôle</label>
                            <select id="pickerRole">
                                ${roleOptions.map(r => `<option value="${r}">${escapeHtml(r)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="field">
                            <label>Rechercher</label>
                            <div class="autocomplete-wrap">
                                <input id="pickerSearch" type="text" placeholder="Tapez pour rechercher..." autocomplete="off">
                                <div class="autocomplete-dropdown" id="pickerDropdown" style="display:none"></div>
                            </div>
                        </div>
                        <p id="pickerMsg" class="message"></p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn secondary" type="button" id="assocModalCancel">Annuler</button>
                    </div>
                </div>`;
            document.body.appendChild(modal);

            const closeModal = () => modal.remove();
            document.getElementById('assocModalClose').addEventListener('click', closeModal);
            document.getElementById('assocModalCancel').addEventListener('click', closeModal);
            modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

            const searchInput = document.getElementById('pickerSearch');
            const dropdown = document.getElementById('pickerDropdown');
            const msgEl = document.getElementById('pickerMsg');

            searchInput.addEventListener('input', debounce(async () => {
                const q = searchInput.value.trim();
                if (q.length < 1) { dropdown.style.display = 'none'; return; }
                try {
                    const data = await request(`${childEndpoint}?search=${encodeURIComponent(q)}&per_page=10`);
                    const items = data.data || [];
                    dropdown.style.display = items.length ? 'block' : 'none';
                    dropdown.innerHTML = items.length
                        ? items.map(item => `<div class="autocomplete-item" data-pick-id="${item.id}"><strong>${escapeHtml(childNameFn(item))}</strong><span class="muted">${escapeHtml(childSubFn(item))}</span></div>`).join('')
                        : '<div class="autocomplete-item"><span class="muted">Aucun résultat</span></div>';
                    dropdown.querySelectorAll('[data-pick-id]').forEach(el => {
                        el.addEventListener('click', async () => {
                            const childId = Number(el.dataset.pickId);
                            const role = document.getElementById('pickerRole')?.value || roleOptions[0];
                            let endpoint, payload;
                            if (assocType === 'contact-deal') {
                                endpoint = `/deals/${childId}/contacts`;
                                payload = { contact_id: parentId, role };
                            } else if (assocType === 'company-deal') {
                                endpoint = `/deals/${childId}/companies`;
                                payload = { company_id: parentId, role };
                            } else if (assocType === 'company-contact') {
                                endpoint = `/companies/${parentId}/contacts`;
                                payload = { contact_id: childId, role };
                            } else if (assocType === 'contact-company') {
                                endpoint = `/contacts/${parentId}/companies`;
                                payload = { company_id: childId, role };
                            } else if (assocType === 'deal-contact') {
                                endpoint = `/deals/${parentId}/contacts`;
                                payload = { contact_id: childId, role };
                            } else if (assocType === 'deal-company') {
                                endpoint = `/deals/${parentId}/companies`;
                                payload = { company_id: childId, role };
                            }
                            try {
                                await request(endpoint, { method: 'POST', body: JSON.stringify(payload) });
                                closeModal();
                                state.detailData = await request(`${resources[state.current].endpoint}/${parentId}`);
                                renderShell();
                            } catch (err) {
                                msgEl.className = 'message error';
                                msgEl.textContent = err.message;
                            }
                        });
                    });
                } catch (_) {
                    dropdown.style.display = 'none';
                }
            }, 300));

            setTimeout(() => searchInput.focus(), 50);
        }

        // ── Deal modal ───────────────────────────────────────────────────────────

        async function openDealModal(prefill = {}) {
            let pipelines = [], users = [], stages = [];
            try {
                const [pipData, usersData] = await Promise.all([
                    request('/pipelines?per_page=100'),
                    request('/users?per_page=100'),
                ]);
                pipelines = pipData.data || [];
                users = usersData.data || [];
            } catch (_) {}

            const firstPipeline = pipelines[0];
            if (firstPipeline) {
                try {
                    const pipDetail = await request(`/pipelines/${firstPipeline.id}`);
                    stages = pipDetail.data?.stages || [];
                } catch (_) {}
            }

            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.id = 'dealModal';
            modal.innerHTML = buildDealModalHtml(pipelines, stages, users, prefill);
            document.body.appendChild(modal);

            // State for autocomplete selections
            const selectedCompanies = prefill.companies ? [...prefill.companies] : [];
            const selectedContacts = prefill.contacts ? [...prefill.contacts] : [];

            const closeModal = () => modal.remove();
            document.getElementById('dealModalClose').addEventListener('click', closeModal);
            document.getElementById('dealModalCancel').addEventListener('click', closeModal);
            modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

            // Pipeline → stage dependency
            const pipelineSelect = document.getElementById('dealPipelineSelect');
            const stageSelect = document.getElementById('dealStageSelect');
            pipelineSelect?.addEventListener('change', async () => {
                const pipId = Number(pipelineSelect.value);
                const pipData = await request(`/pipelines/${pipId}`).catch(() => null);
                const pipeStages = pipData?.data?.stages || [];
                stageSelect.innerHTML = pipeStages.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
            });

            // Company autocomplete
            bindModalAutocomplete('dealCompanySearch', 'dealCompanyDropdown', 'dealCompanyTags',
                '/companies', r => r.name, r => r.domain || '', selectedCompanies);
            // Contact autocomplete
            bindModalAutocomplete('dealContactSearch', 'dealContactDropdown', 'dealContactTags',
                '/contacts', r => `${r.first_name || ''} ${r.last_name || ''}`.trim(), r => r.email || '', selectedContacts);

            renderTagList('dealCompanyTags', selectedCompanies);
            renderTagList('dealContactTags', selectedContacts);

            document.getElementById('dealModalSubmit').addEventListener('click', async () => {
                const form = document.getElementById('dealModalForm');
                if (!form.reportValidity()) return;
                const btn = document.getElementById('dealModalSubmit');
                const msgEl = document.getElementById('dealModalMsg');
                btn.disabled = true;
                msgEl.textContent = '';
                try {
                    const payload = formPayload(form);
                    const deal = await request('/deals', { method: 'POST', body: JSON.stringify(payload) });
                    const dealId = deal.data.id;
                    // Attach companies
                    for (const [i, co] of selectedCompanies.entries()) {
                        await request(`/deals/${dealId}/companies`, {
                            method: 'POST',
                            body: JSON.stringify({ company_id: co.id, role: 'customer', is_primary: i === 0 }),
                        }).catch(() => {});
                    }
                    // Attach contacts
                    for (const [i, ct] of selectedContacts.entries()) {
                        await request(`/deals/${dealId}/contacts`, {
                            method: 'POST',
                            body: JSON.stringify({ contact_id: ct.id, role: i === 0 ? 'primary' : 'technical' }),
                        }).catch(() => {});
                    }
                    closeModal();
                    // Navigate to new deal detail page
                    state.current = 'deals';
                    await openDetailPage(dealId);
                } catch (err) {
                    msgEl.className = 'message error';
                    msgEl.textContent = err.message;
                    btn.disabled = false;
                }
            });
        }

        function buildDealModalHtml(pipelines, stages, users, prefill) {
            return `
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>Nouveau deal</h3>
                        <button class="modal-close" type="button" id="dealModalClose">×</button>
                    </div>
                    <div class="modal-body">
                        <form id="dealModalForm">
                            <div class="field"><label>Nom <span style="color:var(--danger)">*</span></label><input name="name" required placeholder="Nom du deal"></div>
                            <div style="display:grid;grid-template-columns:1fr 80px;gap:8px">
                                <div class="field"><label>Montant</label><input name="amount" type="number" min="0" step="0.01" placeholder="0"></div>
                                <div class="field"><label>Devise</label><select name="currency"><option value="EUR">EUR</option><option value="USD">USD</option><option value="GBP">GBP</option></select></div>
                            </div>
                            <div class="field"><label>Date de clôture</label><input name="close_date" type="date"></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                                <div class="field">
                                    <label>Pipeline <span style="color:var(--danger)">*</span></label>
                                    <select name="pipeline_id" id="dealPipelineSelect" required>
                                        ${pipelines.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('') || '<option value="">Aucun pipeline</option>'}
                                    </select>
                                </div>
                                <div class="field">
                                    <label>Étape <span style="color:var(--danger)">*</span></label>
                                    <select name="pipeline_stage_id" id="dealStageSelect" required>
                                        ${stages.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('') || '<option value="">Choisir un pipeline</option>'}
                                    </select>
                                </div>
                            </div>
                            ${users.length ? `<div class="field"><label>Responsable</label><select name="owner_id"><option value="">—</option>${users.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('')}</select></div>` : ''}
                            <div class="field">
                                <label>Entreprises</label>
                                <div class="autocomplete-wrap">
                                    <input id="dealCompanySearch" type="text" placeholder="Rechercher une entreprise..." autocomplete="off">
                                    <div class="autocomplete-dropdown" id="dealCompanyDropdown" style="display:none"></div>
                                </div>
                                <div class="autocomplete-tags" id="dealCompanyTags"></div>
                            </div>
                            <div class="field">
                                <label>Contacts</label>
                                <div class="autocomplete-wrap">
                                    <input id="dealContactSearch" type="text" placeholder="Rechercher un contact..." autocomplete="off">
                                    <div class="autocomplete-dropdown" id="dealContactDropdown" style="display:none"></div>
                                </div>
                                <div class="autocomplete-tags" id="dealContactTags"></div>
                            </div>
                        </form>
                        <p id="dealModalMsg" class="message"></p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn secondary" type="button" id="dealModalCancel">Annuler</button>
                        <button class="btn" type="button" id="dealModalSubmit">Créer le deal →</button>
                    </div>
                </div>`;
        }

        function bindModalAutocomplete(inputId, dropdownId, tagsId, endpoint, nameFn, subFn, selectedList) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            if (!input || !dropdown) return;
            input.addEventListener('input', debounce(async () => {
                const q = input.value.trim();
                if (q.length < 1) { dropdown.style.display = 'none'; return; }
                try {
                    const data = await request(`${endpoint}?search=${encodeURIComponent(q)}&per_page=8`);
                    const items = (data.data || []).filter(it => !selectedList.find(s => s.id === it.id));
                    dropdown.style.display = items.length ? 'block' : 'none';
                    dropdown.innerHTML = items.map(item => `
                        <div class="autocomplete-item" data-sel-id="${item.id}" data-sel-name="${escapeHtml(nameFn(item))}">
                            <strong>${escapeHtml(nameFn(item))}</strong>
                            <span class="muted">${escapeHtml(subFn(item))}</span>
                        </div>`).join('');
                    dropdown.querySelectorAll('[data-sel-id]').forEach(el => {
                        el.addEventListener('click', () => {
                            selectedList.push({ id: Number(el.dataset.selId), name: el.dataset.selName });
                            input.value = '';
                            dropdown.style.display = 'none';
                            renderTagList(tagsId, selectedList);
                        });
                    });
                } catch (_) {
                    dropdown.style.display = 'none';
                }
            }, 300));
        }

        function renderTagList(containerId, items) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = items.map((item, idx) => `
                <span class="autocomplete-tag">
                    ${escapeHtml(item.name)}
                    <button type="button" data-rm-tag="${idx}" title="Retirer">×</button>
                </span>`).join('');
            container.querySelectorAll('[data-rm-tag]').forEach(btn => {
                btn.addEventListener('click', () => {
                    items.splice(Number(btn.dataset.rmTag), 1);
                    renderTagList(containerId, items);
                });
            });
        }

        if (state.token) {
            loadCurrent();
        } else {
            renderLogin();
        }
    </script>
</body>
</html>
