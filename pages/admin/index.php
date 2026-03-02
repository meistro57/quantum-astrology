<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\AdminGate;
use QuantumAstrology\Core\Auth;

Auth::requireLogin();
$user = Auth::user();
if (!AdminGate::canAccess($user)) {
    http_response_code(403);
    echo '<h1 style="color:#fff;background:#0b0e14;padding:2rem;font-family:system-ui">403 - Admin access required</h1>';
    exit;
}

$csrfToken = (string)($_SESSION['csrf_token'] ?? '');
$pageTitle = 'System Admin Panel - Quantum Astrology';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .admin-wrap { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .admin-title { font-size: 2.2rem; margin: 0 0 0.35rem; background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .admin-sub { color: rgba(255,255,255,0.72); margin-bottom: 1.5rem; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 1rem; backdrop-filter: blur(12px); }
        .metric-label { color: rgba(255,255,255,0.65); font-size: 0.85rem; }
        .metric-value { font-size: 1.8rem; font-weight: 700; color: var(--quantum-text); margin-top: 0.25rem; }
        .section-title { color: var(--quantum-gold); margin: 0 0 0.8rem; }
        .actions { display: flex; gap: 0.65rem; flex-wrap: wrap; }
        .btn { padding: 0.7rem 1rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.08); color: var(--quantum-text); cursor: pointer; }
        .btn-primary { background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple)); border: none; color: #fff; }
        .form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.7rem; }
        .form-row { margin-bottom: 0.7rem; }
        .input { width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.09); color: #fff; }
        textarea.input { min-height: 130px; resize: vertical; }
        .input::placeholder { color: rgba(255,255,255,0.45); }
        .table-wrap { overflow: auto; max-height: 420px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.55rem 0.65rem; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; white-space: nowrap; }
        th { position: sticky; top: 0; background: #131826; z-index: 1; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.83rem; }
        .status { margin-top: 0.8rem; color: #86efac; min-height: 1.1rem; }
        .status.error { color: #ff8e8e; }
        .log { background: #0c121d; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 0.7rem; max-height: 260px; overflow: auto; font-family: ui-monospace,monospace; font-size: 0.82rem; line-height: 1.35; white-space: pre-wrap; }
        @media (max-width: 1080px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    <div class="admin-wrap">
        <div class="page-actions">
            <button type="button" class="back-button" onclick="window.location.href='/dashboard'">
                <span class="icon" aria-hidden="true">←</span><span>Back</span>
            </button>
        </div>
        <h1 class="admin-title">System Admin Panel</h1>
        <p class="admin-sub">Operational controls, health telemetry, and manual user administration.</p>

        <div class="grid">
            <div class="card"><div class="metric-label">Users</div><div class="metric-value" id="m-users">-</div></div>
            <div class="card"><div class="metric-label">Charts</div><div class="metric-value" id="m-charts">-</div></div>
            <div class="card"><div class="metric-label">Public Charts</div><div class="metric-value" id="m-public">-</div></div>
            <div class="card"><div class="metric-label">Errors Today</div><div class="metric-value" id="m-errors">-</div></div>
        </div>

        <div class="card">
            <h3 class="section-title">Maintenance</h3>
            <div class="actions">
                <button class="btn" data-action="clear_chart_cache">Clear Chart Cache</button>
                <button class="btn" data-action="clear_ai_cache">Clear AI Cache</button>
                <button class="btn" data-action="clear_all_cache">Clear All Cache</button>
                <button class="btn btn-primary" id="reload-overview">Refresh Overview</button>
            </div>
            <div class="status" id="action-status"></div>
        </div>

        <div class="card">
            <h3 class="section-title">Master AI API Configuration</h3>
            <div class="form-grid">
                <select id="ai-master-provider" class="input"></select>
                <input id="ai-master-model" class="input" placeholder="Model (optional, e.g. gpt-4o-mini)">
                <input id="ai-master-key" class="input" type="password" placeholder="Master API key (leave blank to keep current)">
            </div>
            <div class="form-row" style="margin-top:0.55rem;">
                <label style="display:inline-flex;align-items:center;gap:0.45rem;color:rgba(255,255,255,0.8);">
                    <input id="ai-master-clear-key" type="checkbox"> Clear stored master API key
                </label>
            </div>
            <div class="actions" style="margin-top:0.8rem;">
                <button class="btn btn-primary" id="save-ai-master-config">Save AI Config</button>
                <span id="ai-master-key-state" style="color: rgba(255,255,255,0.75); font-size: 0.9rem;"></span>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">AI Summary Settings</h3>
            <div class="form-row">
                <label style="display:block;color:rgba(255,255,255,0.8);margin-bottom:0.4rem;">System Prompt</label>
                <textarea id="ai-summary-system-prompt" class="input" placeholder="System instructions for AI summary generation"></textarea>
            </div>
            <div class="form-grid">
                <select id="ai-summary-style" class="input">
                    <option value="professional">Professional</option>
                    <option value="empathetic">Empathetic</option>
                    <option value="direct">Direct</option>
                    <option value="technical">Technical</option>
                </select>
                <select id="ai-summary-length" class="input">
                    <option value="short">Short</option>
                    <option value="medium">Medium</option>
                    <option value="long">Long</option>
                </select>
                <input id="ai-summary-focus-template" class="input" placeholder="Focus template (use {report_type})">
            </div>
            <div class="actions" style="margin-top:0.8rem;">
                <button class="btn btn-primary" id="save-ai-summary-config">Save Summary Settings</button>
                <span id="ai-summary-state" style="color: rgba(255,255,255,0.75); font-size: 0.9rem;"></span>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">Create User</h3>
            <div class="form-grid">
                <input id="new-username" class="input" placeholder="Username">
                <input id="new-email" class="input" placeholder="Email">
                <input id="new-password" class="input" type="password" placeholder="Temporary password (8+ chars)">
                <input id="new-first" class="input" placeholder="First name (optional)">
                <input id="new-last" class="input" placeholder="Last name (optional)">
                <input id="new-timezone" class="input" placeholder="Timezone (default UTC)" value="UTC">
            </div>
            <div class="form-row" style="margin-top:0.55rem;">
                <label style="display:inline-flex;align-items:center;gap:0.45rem;color:rgba(255,255,255,0.8);">
                    <input id="new-is-admin" type="checkbox"> Create as admin user
                </label>
            </div>
            <div class="actions" style="margin-top:0.8rem;">
                <button class="btn btn-primary" id="create-user">Create User</button>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">Reset User Password</h3>
            <div class="form-grid">
                <input id="reset-user-id" class="input" placeholder="User ID" type="number" min="1">
                <input id="reset-password" class="input" placeholder="New password (8+ chars)" type="password">
                <div></div>
            </div>
            <div class="actions" style="margin-top:0.8rem;">
                <button class="btn btn-primary" id="reset-user-password">Reset Password</button>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">Users</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Timezone</th><th>Admin</th><th>Charts</th><th>Created</th><th>Last Login</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody"></tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">Recent Logs</h3>
            <div class="log mono" id="log-tail">Loading...</div>
        </div>
    </div>

    <script>
        const csrf = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const actionStatus = document.getElementById('action-status');
        let aiProvidersMeta = {};

        function setStatus(message, isError = false) {
            actionStatus.textContent = message;
            actionStatus.classList.toggle('error', isError);
        }

        async function postAdminAction(action, payload = {}) {
            const response = await fetch('/api/admin/actions', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action, csrf, ...payload })
            });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Action failed');
            }
            return data;
        }

        function fmtBytes(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let idx = 0;
            let value = Number(bytes);
            while (value >= 1024 && idx < units.length - 1) {
                value /= 1024;
                idx++;
            }
            return `${value.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
        }

        async function loadOverview() {
            const response = await fetch('/api/admin/overview');
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Failed to load overview');
            }

            document.getElementById('m-users').textContent = String(data.counts.users ?? 0);
            document.getElementById('m-charts').textContent = String(data.counts.charts ?? 0);
            document.getElementById('m-public').textContent = String(data.counts.public_charts ?? 0);
            document.getElementById('m-errors').textContent = String(data.errors_today ?? 0);

            const logText = (data.log_tail || []).join('\n');
            document.getElementById('log-tail').textContent =
                `${data.log_file || 'No log file'}\n\n` +
                `Storage: logs=${fmtBytes(data.storage?.logs_bytes)} cache=${fmtBytes(data.storage?.cache_bytes)} charts=${fmtBytes(data.storage?.charts_bytes)}\n\n` +
                (logText || 'No log entries yet.');
        }

        function renderAiProviderOptions(selectedProvider) {
            const select = document.getElementById('ai-master-provider');
            const providers = Object.keys(aiProvidersMeta);
            if (!providers.length) {
                select.innerHTML = '<option value="ollama">ollama</option>';
                select.value = 'ollama';
                return;
            }
            select.innerHTML = providers.map((provider) => {
                const label = aiProvidersMeta[provider]?.name || provider;
                return `<option value="${provider}">${label}</option>`;
            }).join('');
            if (selectedProvider && providers.includes(selectedProvider)) {
                select.value = selectedProvider;
            } else {
                select.value = providers[0];
            }
        }

        async function loadAiMasterConfig() {
            const [providersResp, configResp] = await Promise.all([
                fetch('/api/ai/providers'),
                postAdminAction('get_ai_master_config'),
            ]);
            const providersData = await providersResp.json();
            if (!providersResp.ok || providersData.error) {
                throw new Error(providersData.error || 'Failed to load AI providers');
            }

            aiProvidersMeta = providersData.providers || {};
            renderAiProviderOptions(configResp.provider || providersData.default_provider || 'ollama');

            document.getElementById('ai-master-model').value = configResp.model || '';
            document.getElementById('ai-master-key').value = '';
            document.getElementById('ai-master-clear-key').checked = false;

            const keyState = document.getElementById('ai-master-key-state');
            keyState.textContent = configResp.api_key_set
                ? `Master key set${configResp.updated_at ? ` (updated ${configResp.updated_at})` : ''}`
                : 'No master key saved';
        }

        async function loadAiSummaryConfig() {
            const cfg = await postAdminAction('get_ai_summary_config');
            document.getElementById('ai-summary-system-prompt').value = cfg.system_prompt || '';
            document.getElementById('ai-summary-style').value = cfg.style || 'professional';
            document.getElementById('ai-summary-length').value = cfg.length || 'short';
            document.getElementById('ai-summary-focus-template').value = cfg.focus_template || '';
            document.getElementById('ai-summary-state').textContent = cfg.updated_at
                ? `Updated ${cfg.updated_at}`
                : 'Using defaults';
        }

        async function loadUsers() {
            const data = await postAdminAction('list_users');
            const tbody = document.getElementById('users-tbody');
            tbody.innerHTML = '';
            (data.users || []).forEach((u) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${u.id ?? ''}</td>
                    <td>${u.username ?? ''}</td>
                    <td>${u.email ?? ''}</td>
                    <td>${[u.first_name || '', u.last_name || ''].join(' ').trim()}</td>
                    <td>${u.timezone ?? ''}</td>
                    <td>${Number(u.is_admin) === 1 ? 'Yes' : 'No'}</td>
                    <td>${u.chart_count ?? 0}</td>
                    <td class="mono">${u.created_at ?? ''}</td>
                    <td class="mono">${u.last_login_at ?? ''}</td>
                    <td>
                        <button class="btn" data-user-id="${u.id}" data-is-admin="${Number(u.is_admin) === 1 ? '1' : '0'}">
                            ${Number(u.is_admin) === 1 ? 'Revoke Admin' : 'Make Admin'}
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('button[data-user-id]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const userId = Number(btn.getAttribute('data-user-id') || '0');
                    const isAdminNow = btn.getAttribute('data-is-admin') === '1';
                    try {
                        const res = await postAdminAction('set_user_admin', { user_id: userId, is_admin: !isAdminNow });
                        setStatus(res.message || 'Admin flag updated.');
                        await loadUsers();
                    } catch (error) {
                        setStatus(error.message || 'Failed to update admin flag', true);
                    }
                });
            });
        }

        document.querySelectorAll('[data-action]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const action = btn.getAttribute('data-action');
                try {
                    const result = await postAdminAction(action);
                    setStatus(`${action}: removed ${result.deleted_files ?? 0} files.`);
                } catch (error) {
                    setStatus(error.message || 'Action failed', true);
                }
            });
        });

        document.getElementById('reload-overview').addEventListener('click', async () => {
            try {
                await Promise.all([loadOverview(), loadUsers()]);
                setStatus('Overview refreshed.');
            } catch (error) {
                setStatus(error.message || 'Failed to refresh', true);
            }
        });

        document.getElementById('create-user').addEventListener('click', async () => {
            const payload = {
                username: document.getElementById('new-username').value.trim(),
                email: document.getElementById('new-email').value.trim(),
                password: document.getElementById('new-password').value,
                first_name: document.getElementById('new-first').value.trim(),
                last_name: document.getElementById('new-last').value.trim(),
                timezone: document.getElementById('new-timezone').value.trim() || 'UTC',
                is_admin: document.getElementById('new-is-admin').checked,
            };
            try {
                const result = await postAdminAction('create_user', payload);
                setStatus(result.message || 'User created.');
                await loadUsers();
            } catch (error) {
                setStatus(error.message || 'Create user failed', true);
            }
        });

        document.getElementById('reset-user-password').addEventListener('click', async () => {
            const userId = Number(document.getElementById('reset-user-id').value || 0);
            const newPassword = document.getElementById('reset-password').value;
            try {
                const result = await postAdminAction('reset_user_password', { user_id: userId, new_password: newPassword });
                setStatus(result.message || 'Password reset.');
            } catch (error) {
                setStatus(error.message || 'Reset password failed', true);
            }
        });

        document.getElementById('save-ai-master-config').addEventListener('click', async () => {
            const payload = {
                provider: document.getElementById('ai-master-provider').value,
                model: document.getElementById('ai-master-model').value.trim(),
                api_key: document.getElementById('ai-master-key').value.trim(),
                clear_api_key: document.getElementById('ai-master-clear-key').checked,
            };
            try {
                const result = await postAdminAction('set_ai_master_config', payload);
                setStatus(result.message || 'Master AI configuration saved.');
                await loadAiMasterConfig();
            } catch (error) {
                setStatus(error.message || 'Failed to save AI configuration', true);
            }
        });

        document.getElementById('save-ai-summary-config').addEventListener('click', async () => {
            const payload = {
                system_prompt: document.getElementById('ai-summary-system-prompt').value.trim(),
                style: document.getElementById('ai-summary-style').value,
                length: document.getElementById('ai-summary-length').value,
                focus_template: document.getElementById('ai-summary-focus-template').value.trim(),
            };
            try {
                const result = await postAdminAction('set_ai_summary_config', payload);
                setStatus(result.message || 'AI summary settings saved.');
                await loadAiSummaryConfig();
            } catch (error) {
                setStatus(error.message || 'Failed to save AI summary settings', true);
            }
        });

        (async () => {
            try {
                await Promise.all([loadOverview(), loadUsers(), loadAiMasterConfig(), loadAiSummaryConfig()]);
                setStatus('Admin panel ready.');
            } catch (error) {
                setStatus(error.message || 'Failed to load admin panel', true);
            }
        })();
    </script>
</body>
</html>
