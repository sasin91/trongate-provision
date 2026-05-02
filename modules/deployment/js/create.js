'use strict';

function toggleSource(type) {
    const git = document.getElementById('src-git-fields');
    const zip = document.getElementById('src-zip-fields');
    if (git) git.classList.toggle('is-hidden', type !== 'git');
    if (zip) zip.classList.toggle('is-hidden', type !== 'zip');
}

function applyEnvLock(sel) {
    const opt = sel.options[sel.selectedIndex];
    const locked = parseInt(opt.dataset.lockedServer || '0', 10);
    const name = opt.dataset.lockedName || '';
    const serverSelect = document.getElementById('server-select');
    const hint = document.getElementById('server-lock-hint');
    if (!serverSelect || !hint) return;

    if (locked) {
        for (let i = 0; i < serverSelect.options.length; i++) {
            if (parseInt(serverSelect.options[i].value, 10) === locked) {
                serverSelect.selectedIndex = i;
                break;
            }
        }
        serverSelect.onchange = event => {
            event.preventDefault();
            applyEnvLock(sel);
        };
        hint.textContent = 'Locked to "' + name + '"';
        hint.style.display = '';
    } else {
        serverSelect.onchange = undefined;
        hint.style.display = 'none';
    }
}

function setStatus(value) {
    const status = document.getElementById('wizard-status');
    if (status) status.textContent = value;
}

function setPanelEnabled(id, enabled) {
    const panel = document.getElementById(id);
    if (panel) panel.classList.toggle('is-muted', !enabled);
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(body || {})
    });
    const data = await response.json().catch(() => ({ok: false, message: 'Invalid JSON response.'}));
    if (!response.ok || data.ok === false) {
        throw new Error(data.message || 'Request failed.');
    }
    return data;
}

async function getJson(url) {
    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    const data = await response.json().catch(() => ({ok: false, message: 'Invalid JSON response.'}));
    if (!response.ok || data.ok === false) {
        throw new Error(data.message || 'Request failed.');
    }
    return data;
}

function renderSqlFiles(files) {
    const list = document.getElementById('sql-list');
    const msg = document.getElementById('sql-msg');
    const deleteBtn = document.getElementById('delete-sql-btn');
    const promoteBtn = document.getElementById('promote-btn');
    if (!list || !msg || !deleteBtn || !promoteBtn) return;

    list.textContent = '';
    deleteBtn.classList.add('is-hidden');
    promoteBtn.disabled = true;

    if (!files.length) {
        msg.textContent = 'No SQL files found in the staged release.';
        promoteBtn.disabled = false;
        setPanelEnabled('promote-panel', true);
        return;
    }

    msg.textContent = 'Copy and run these SQL files manually, then remove them from the staged release.';
    files.forEach(file => {
        const details = document.createElement('details');
        details.className = 'sql-details';

        const summary = document.createElement('summary');
        summary.textContent = file.path;

        const pre = document.createElement('pre');
        pre.textContent = file.sql;

        const actions = document.createElement('div');
        actions.className = 'sql-actions';

        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'btn-secondary-onboarding';
        copy.textContent = 'Copy SQL';
        copy.addEventListener('click', async () => {
            await navigator.clipboard.writeText(file.sql);
            copy.textContent = 'Copied';
            window.setTimeout(() => {
                copy.textContent = 'Copy SQL';
            }, 1400);
        });

        actions.appendChild(copy);
        details.appendChild(summary);
        details.appendChild(pre);
        details.appendChild(actions);
        list.appendChild(details);
    });

    deleteBtn.classList.remove('is-hidden');
    deleteBtn.dataset.paths = JSON.stringify(files.map(file => file.path));
}

async function scanSql(card) {
    const msg = document.getElementById('sql-msg');
    if (msg) msg.textContent = 'Scanning staged release for SQL files...';
    setPanelEnabled('sql-panel', true);
    const result = await getJson(card.dataset.scanSqlUrl);
    renderSqlFiles(result.files || []);
}

function startDeploy(card) {
    const log = document.getElementById('wizard-log');
    const msg = document.getElementById('deploy-msg');
    if (!log || !msg || !card.dataset.streamUrl) return;

    log.textContent = 'Connecting...\n';
    msg.textContent = 'Staging release on the server.';
    setStatus('running');

    const es = new EventSource(card.dataset.streamUrl);
    es.onmessage = event => {
        log.textContent += event.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('state', event => {
        const state = JSON.parse(event.data);
        if (state.status === 'running') {
            log.textContent += state.message + '\n';
            log.scrollTop = log.scrollHeight;
            msg.textContent = 'Deployment is already running.';
        }
    });

    es.addEventListener('done', async event => {
        es.close();
        const result = JSON.parse(event.data);
        if (result.status === 'running') {
            msg.textContent = 'Deployment is still running. Refreshing shortly.';
            window.setTimeout(() => window.location.reload(), 5000);
            return;
        }
        if (result.status !== 'staged') {
            setStatus(result.status || 'failed');
            msg.textContent = result.status === 'missing_zip'
                ? 'The uploaded zip is no longer available. Create the deployment again with a fresh zip.'
                : 'Deployment failed. Open deployment details for the full log.';
            return;
        }

        setStatus('staged');
        msg.textContent = 'Release staged.';
        try {
            await scanSql(card);
        } catch (error) {
            const sqlMsg = document.getElementById('sql-msg');
            if (sqlMsg) sqlMsg.textContent = error.message;
        }
    });

    es.onerror = () => {
        if (es.readyState === EventSource.CLOSED) return;
        es.close();
        log.textContent += '\n[connection closed]\n';
        msg.textContent = 'Deployment stream closed before completion.';
    };
}

function initWizard(card) {
    const deleteBtn = document.getElementById('delete-sql-btn');
    const promoteBtn = document.getElementById('promote-btn');

    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            const msg = document.getElementById('sql-msg');
            const paths = JSON.parse(deleteBtn.dataset.paths || '[]');
            deleteBtn.disabled = true;
            if (msg) msg.textContent = 'Deleting SQL files from staged release...';
            try {
                const result = await postJson(card.dataset.deleteSqlUrl, {paths});
                if (msg) msg.textContent = 'Deleted ' + result.deleted + ' SQL file(s) from the staged release.';
                deleteBtn.classList.add('is-hidden');
                if (promoteBtn) promoteBtn.disabled = false;
                setPanelEnabled('promote-panel', true);
            } catch (error) {
                if (msg) msg.textContent = error.message;
                deleteBtn.disabled = false;
            }
        });
    }

    if (promoteBtn) {
        promoteBtn.addEventListener('click', async () => {
            const msg = document.getElementById('promote-msg');
            promoteBtn.disabled = true;
            if (msg) msg.textContent = 'Promoting release...';
            try {
                const result = await postJson(card.dataset.promoteUrl);
                setStatus('success');
                const health = result.health || {};
                if (msg) {
                    msg.textContent = 'Release is live. Health checks: '
                        + (health.healthy || 0) + ' healthy, '
                        + (health.unhealthy || 0) + ' unhealthy, '
                        + (health.unknown || 0) + ' unknown.';
                }
            } catch (error) {
                if (msg) msg.textContent = error.message;
                promoteBtn.disabled = false;
            }
        });
    }

    if (card.dataset.status === 'script_ready') {
        startDeploy(card);
    } else if (card.dataset.status === 'running') {
        startDeploy(card);
    } else if (card.dataset.status === 'staged') {
        scanSql(card).catch(error => {
            const msg = document.getElementById('sql-msg');
            if (msg) msg.textContent = error.message;
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const envSelect = document.getElementById('env-select');
    if (envSelect) {
        envSelect.addEventListener('change', () => applyEnvLock(envSelect));
        if (envSelect.value) applyEnvLock(envSelect);
    }

    const checked = document.querySelector('input[name=source_type]:checked');
    document.querySelectorAll('input[name=source_type]').forEach(input => {
        input.addEventListener('change', () => toggleSource(input.value));
    });
    if (checked || document.getElementById('src-git-fields')) {
        toggleSource(checked ? checked.value : 'git');
    }

    const card = document.querySelector('[data-deployment-id]');
    if (card) initWizard(card);
});
