

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CSV Import</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0e11;
            --surface: #13151a;
            --surface2: #1c1f27;
            --border: #2a2d38;
            --accent: #00e5a0;
            --accent-dim: rgba(0, 229, 160, 0.12);
            --accent-dim2: rgba(0, 229, 160, 0.06);
            --error: #ff4d6a;
            --error-dim: rgba(255, 77, 106, 0.1);
            --warn: #ffb347;
            --text: #e8eaf0;
            --text-muted: #6b7280;
            --text-dim: #9ca3af;
            --mono: 'Space Mono', monospace;
            --sans: 'DM Sans', sans-serif;
        }

        html { scroll-behavior: smooth; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: var(--sans);
            min-height: 100vh;
            padding: 0;
            overflow-x: hidden;
        }

        /* Background grid */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,229,160,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,229,160,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 48px 24px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        header {
            margin-bottom: 48px;
            animation: fadeDown 0.6s ease both;
        }

        .header-tag {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-tag::before {
            content: '';
            display: block;
            width: 24px;
            height: 1px;
            background: var(--accent);
        }

        h1 {
            font-family: var(--mono);
            font-size: clamp(24px, 4vw, 36px);
            font-weight: 700;
            color: var(--text);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        h1 span {
            color: var(--accent);
        }

        .subtitle {
            margin-top: 10px;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 300;
        }

        /* Cards */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px;
            margin-bottom: 24px;
            animation: fadeUp 0.5s ease both;
        }

        .card-title {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent);
        }

        /* Upload Zone */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 40px 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: var(--accent-dim2);
        }

        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--accent);
            background: var(--accent-dim);
        }

        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-icon {
            width: 40px;
            height: 40px;
            margin: 0 auto 12px;
            color: var(--accent);
        }

        .upload-label {
            font-size: 14px;
            color: var(--text-dim);
        }

        .upload-label strong {
            color: var(--accent);
            font-weight: 500;
        }

        .upload-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
            font-family: var(--mono);
        }

        .file-selected {
            margin-top: 14px;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--accent-dim);
            border: 1px solid rgba(0,229,160,0.2);
            border-radius: 6px;
            padding: 8px 14px;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--accent);
        }

        .file-selected.show { display: flex; }

        /* Button */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-family: var(--mono);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.15s ease;
            margin-top: 18px;
            width: 100%;
            justify-content: center;
        }

        .btn:hover { background: #00ffb3; transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

        .btn-outline {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
        }

        .btn-outline:hover { background: var(--accent-dim); }

        /* Spinner */
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0,0,0,0.3);
            border-top-color: #000;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }

        .btn.loading .spinner { display: block; }
        .btn.loading .btn-text { opacity: 0.6; }

        /* Stats row */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }

        .stat-box.success { border-color: rgba(0,229,160,0.3); background: var(--accent-dim2); }
        .stat-box.error { border-color: rgba(255,77,106,0.3); background: var(--error-dim); }

        .stat-number {
            font-family: var(--mono);
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-box.success .stat-number { color: var(--accent); }
        .stat-box.error .stat-number { color: var(--error); }
        .stat-box .stat-number { color: var(--text-dim); }

        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            font-family: var(--mono);
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* Imported rows */
        .imported-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .imported-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: var(--accent-dim2);
            border: 1px solid rgba(0,229,160,0.1);
            border-radius: 6px;
            font-size: 13px;
            animation: fadeUp 0.3s ease both;
        }

        .imported-row .id-badge {
            font-family: var(--mono);
            font-size: 10px;
            color: var(--accent);
            background: var(--accent-dim);
            padding: 2px 6px;
            border-radius: 4px;
            min-width: 36px;
            text-align: center;
        }

        .imported-row .name { font-weight: 500; color: var(--text); }
        .imported-row .email { color: var(--text-muted); font-size: 12px; margin-left: auto; font-family: var(--mono); }

        /* Error list */
        .error-list { display: flex; flex-direction: column; gap: 10px; }

        .error-row {
            background: var(--surface2);
            border: 1px solid rgba(255,77,106,0.2);
            border-radius: 8px;
            overflow: hidden;
            animation: fadeUp 0.3s ease both;
        }

        .error-row-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: var(--error-dim);
            cursor: pointer;
            user-select: none;
        }

        .row-badge {
            font-family: var(--mono);
            font-size: 10px;
            background: var(--error);
            color: #fff;
            padding: 2px 7px;
            border-radius: 4px;
            white-space: nowrap;
        }

        .error-count {
            font-size: 12px;
            color: var(--error);
            font-family: var(--mono);
        }

        .chevron {
            margin-left: auto;
            color: var(--text-muted);
            transition: transform 0.2s;
            font-size: 12px;
        }

        .error-row.open .chevron { transform: rotate(180deg); }

        .error-fields {
            display: none;
            padding: 10px 14px;
            gap: 6px;
            flex-direction: column;
        }

        .error-row.open .error-fields { display: flex; }

        .error-field {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
        }

        .field-name {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--warn);
            min-width: 110px;
            padding-top: 1px;
        }

        .field-msg { color: var(--text-dim); }

        /* Fatal error */
        .fatal-error {
            background: var(--error-dim);
            border: 1px solid rgba(255,77,106,0.3);
            border-radius: 10px;
            padding: 20px 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: fadeUp 0.4s ease both;
        }

        .fatal-error .icon { color: var(--error); font-size: 20px; line-height: 1; }
        .fatal-error .msg { font-size: 14px; color: var(--text); }
        .fatal-error .msg strong { color: var(--error); display: block; margin-bottom: 4px; font-family: var(--mono); font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; }

        /* Customers table */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead tr {
            border-bottom: 1px solid var(--border);
        }

        th {
            font-family: var(--mono);
            font-size: 10px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            color: var(--text-dim);
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        td.name-cell { color: var(--text); font-weight: 500; }
        td.email-cell { font-family: var(--mono); font-size: 12px; color: var(--accent); }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .page-btns { display: flex; gap: 6px; }

        .page-btn {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text-dim);
            border-radius: 6px;
            padding: 6px 12px;
            font-family: var(--mono);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .page-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
        .page-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        /* Hidden / visible */
        .hidden { display: none !important; }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .tab {
            font-family: var(--mono);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 10px 18px;
            cursor: pointer;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: all 0.15s;
        }

        .tab:hover { color: var(--text); }
        .tab.active { color: var(--accent); border-bottom-color: var(--accent); }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Empty state */
        .empty {
            text-align: center;
            padding: 32px;
            color: var(--text-muted);
            font-size: 13px;
            font-family: var(--mono);
        }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Result section */
        #result-section { animation: fadeUp 0.5s ease both; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--surface); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
    </style>
</head>
<body>
<div class="container">

    <!-- Header -->
    <header>
        <div class="header-tag">Data Pipeline</div>
        <h1>CSV <span>Import</span><br>& Validation</h1>
        <p class="subtitle">Upload a customer CSV file — valid rows import instantly, errors reported per row.</p>
    </header>

    <!-- Upload Card -->
    <div class="card" style="animation-delay:0.1s">
        <div class="card-title"><span class="dot"></span> Upload File</div>

        <div class="upload-zone" id="dropZone">
            <input type="file" id="csvFile" accept=".csv,.txt">
            <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
            </svg>
            <p class="upload-label"><strong>Click to browse</strong> or drag & drop</p>
            <p class="upload-hint">CSV files only · max 10MB</p>
        </div>

        <div class="file-selected" id="fileSelected">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span id="fileName">file.csv</span>
        </div>

        <button class="btn" id="importBtn" disabled onclick="runImport()">
            <div class="spinner"></div>
            <span class="btn-text">Import CSV</span>
        </button>
    </div>

    <!-- Result Section -->
    <div id="result-section" class="hidden">

        <!-- Fatal Error -->
        <div id="fatal-error" class="fatal-error hidden">
            <div class="icon">✕</div>
            <div class="msg">
                <strong>Import Failed</strong>
                <span id="fatal-msg"></span>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats" id="stats-row">
            <div class="stat-box">
                <div class="stat-number" id="stat-total">0</div>
                <div class="stat-label">Processed</div>
            </div>
            <div class="stat-box success">
                <div class="stat-number" id="stat-imported">0</div>
                <div class="stat-label">Imported</div>
            </div>
            <div class="stat-box error">
                <div class="stat-number" id="stat-failed">0</div>
                <div class="stat-label">Failed</div>
            </div>
        </div>

        <!-- Results Card with Tabs -->
        <div class="card" id="results-card">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('imported')">Imported</div>
                <div class="tab" onclick="switchTab('errors')" id="errors-tab">Errors</div>
            </div>

            <div class="tab-content active" id="tab-imported">
                <div id="imported-list" class="imported-list"></div>
                <div class="empty hidden" id="imported-empty">No rows were imported.</div>
            </div>

            <div class="tab-content" id="tab-errors">
                <div id="error-list" class="error-list"></div>
                <div class="empty hidden" id="errors-empty">No errors — all rows valid!</div>
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card" style="animation-delay:0.2s">
        <div class="card-title" style="justify-content: space-between; align-items:center; display:flex;">
            <div style="display:flex;align-items:center;gap:8px"><span class="dot"></span> Customers</div>
            <button class="page-btn" onclick="loadCustomers(1)" style="margin:0">Refresh</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Date of Birth</th>
                        <th>Annual Income</th>
                    </tr>
                </thead>
                <tbody id="customers-tbody">
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;font-family:var(--mono);font-size:12px;">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <span id="page-info" style="font-family:var(--mono);font-size:12px;"></span>
            <div class="page-btns">
                <button class="page-btn" id="prev-btn" onclick="changePage(-1)" disabled>← Prev</button>
                <button class="page-btn" id="next-btn" onclick="changePage(1)" disabled>Next →</button>
            </div>
        </div>
    </div>

</div>

<script>
    let currentPage = 1;
    let lastPage = 1;

    // --- File input ---
    const fileInput = document.getElementById('csvFile');
    const dropZone = document.getElementById('dropZone');
    const importBtn = document.getElementById('importBtn');

    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) {
            document.getElementById('fileName').textContent = fileInput.files[0].name;
            document.getElementById('fileSelected').classList.add('show');
            importBtn.disabled = false;
        }
    });

    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        if (e.dataTransfer.files[0]) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    // --- Import ---
    async function runImport() {
        const file = fileInput.files[0];
        if (!file) return;

        importBtn.classList.add('loading');
        importBtn.disabled = true;

        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch('/import', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: formData
            });

            const data = await res.json();
            document.getElementById('result-section').classList.remove('hidden');

            if (data.error) {
                showFatalError(data.error);
            } else {
                hideFatalError();
                showStats(data);
                showImported(data.imported || []);
                showErrors(data.errors || []);
                loadCustomers(1);
            }
        } catch (err) {
            showFatalError('Network error — could not reach the server.');
        }

        importBtn.classList.remove('loading');
        importBtn.disabled = false;
    }

    function showFatalError(msg) {
        document.getElementById('fatal-error').classList.remove('hidden');
        document.getElementById('fatal-msg').textContent = msg;
        document.getElementById('stats-row').classList.add('hidden');
        document.getElementById('results-card').classList.add('hidden');
    }

    function hideFatalError() {
        document.getElementById('fatal-error').classList.add('hidden');
        document.getElementById('stats-row').classList.remove('hidden');
        document.getElementById('results-card').classList.remove('hidden');
    }

    function showStats(data) {
        document.getElementById('stat-total').textContent = data.total_rows_processed;
        document.getElementById('stat-imported').textContent = data.imported_count;
        document.getElementById('stat-failed').textContent = data.failed_count;

        // Update errors tab label
        if (data.failed_count > 0) {
            document.getElementById('errors-tab').textContent = `Errors (${data.failed_count})`;
        }
    }

    function showImported(rows) {
        const list = document.getElementById('imported-list');
        const empty = document.getElementById('imported-empty');
        list.innerHTML = '';

        if (rows.length === 0) {
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');

        rows.forEach((r, i) => {
            const el = document.createElement('div');
            el.className = 'imported-row';
            el.style.animationDelay = `${i * 0.04}s`;
            el.innerHTML = `
                <span class="id-badge">#${r.id}</span>
                <span class="name">${esc(r.name)}</span>
                <span class="email">${esc(r.email)}</span>
            `;
            list.appendChild(el);
        });
    }

    function showErrors(errors) {
        const list = document.getElementById('error-list');
        const empty = document.getElementById('errors-empty');
        list.innerHTML = '';

        if (errors.length === 0) {
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');

        errors.forEach((e, i) => {
            const el = document.createElement('div');
            el.className = 'error-row open';
            el.style.animationDelay = `${i * 0.04}s`;

            const fields = e.errors.map(err => `
                <div class="error-field">
                    <span class="field-name">${esc(err.field)}</span>
                    <span class="field-msg">${esc(err.message)}</span>
                </div>
            `).join('');

            el.innerHTML = `
                <div class="error-row-header" onclick="this.parentElement.classList.toggle('open')">
                    <span class="row-badge">ROW ${e.row}</span>
                    <span class="error-count">${e.errors.length} error${e.errors.length > 1 ? 's' : ''}</span>
                    <span class="chevron">▼</span>
                </div>
                <div class="error-fields">${fields}</div>
            `;
            list.appendChild(el);
        });
    }

    function switchTab(name) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        document.getElementById('tab-' + name).classList.add('active');
    }

    // --- Customers table ---
    async function loadCustomers(page) {
        currentPage = page;
        const tbody = document.getElementById('customers-tbody');
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;font-family:var(--mono);font-size:12px;">Loading...</td></tr>`;

        try {
            const res = await fetch(`/customers?page=${page}&per_page=10`);
            const data = await res.json();
            lastPage = data.last_page || 1;

            document.getElementById('page-info').textContent =
                `Page ${data.current_page} of ${data.last_page} · ${data.total} total`;

            document.getElementById('prev-btn').disabled = data.current_page <= 1;
            document.getElementById('next-btn').disabled = data.current_page >= data.last_page;

            tbody.innerHTML = '';

            if (!data.data || data.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;font-family:var(--mono);font-size:12px;">No customers yet.</td></tr>`;
                return;
            }

            data.data.forEach(c => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-family:var(--mono);font-size:11px;color:var(--text-muted)">#${c.id}</td>
                    <td class="name-cell">${esc(c.name)}</td>
                    <td class="email-cell">${esc(c.email)}</td>
                    <td>${c.date_of_birth ? esc(c.date_of_birth) : '<span style="color:var(--text-muted)">—</span>'}</td>
                    <td>${c.annual_income ? '$' + Number(c.annual_income).toLocaleString() : '<span style="color:var(--text-muted)">—</span>'}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--error);padding:24px;font-family:var(--mono);font-size:12px;">Failed to load customers.</td></tr>`;
        }
    }

    function changePage(dir) {
        const next = currentPage + dir;
        if (next >= 1 && next <= lastPage) loadCustomers(next);
    }

    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Initial load
    loadCustomers(1);
</script>
</body>
</html>
