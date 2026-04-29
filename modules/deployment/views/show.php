<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="deployment">Deployments</a>
            <span class="breadcrumb-sep">/</span>
            <a href="server/show/<?= $deployment->server_id ?>"><?= htmlspecialchars($deployment->server_name) ?></a>
            <span class="breadcrumb-sep">/</span>
            #<?= $deployment->id ?>
        </div>
        <div class="page-title">
            Deployment #<?= $deployment->id ?>
            <?php if ((int)($deployment->is_canary ?? 0) === 1): ?>
                <span class="badge badge-development" style="vertical-align:middle;font-size:.65rem;margin-left:.5rem">&#9670; Canary <?= (int)$deployment->canary_weight ?>%</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="actions-row">
        <?= form_open('server-health/check/deployment/' . $deployment->id, ['style' => 'display:inline;margin:0']) ?>
        <button type="submit" class="btn btn-secondary">&#10003; Check Health</button>
        <?= form_close() ?>
        <a href="environment-services/create?environment=<?= $deployment->env_id ?>" class="btn btn-secondary">+ Service</a>
        <?php if ($deployment->status !== 'running'): ?>
            <button id="deploy-btn" class="btn btn-primary" onclick="startDeploy(<?= $deployment->id ?>)">&#9654; Deploy</button>
        <?php endif; ?>
        <?php if ((int)($deployment->is_canary ?? 0) === 1 && $deployment->status === 'success'): ?>
            <?= form_open('deployment/promote_canary/' . $deployment->id, ['style' => 'display:inline;margin:0']) ?>
            <button type="submit" class="btn btn-secondary" onclick="return confirm('Promote canary to full traffic?')">&#8679; Promote</button>
            <?= form_close() ?>
        <?php elseif ($deployment->status !== 'running'): ?>
            <?= form_open('deployment/mark_success/' . $deployment->id, ['style' => 'display:inline-flex;gap:.4rem;align-items:center;margin:0']) ?>
            <input type="text" name="deployed_sha" class="form-control" style="width:150px;font-family:monospace;font-size:.78rem;padding:.3rem .5rem" placeholder="git SHA (optional)" maxlength="40">
            <button type="submit" class="btn btn-secondary">Mark Successful</button>
            <?= form_close() ?>
        <?php endif; ?>
        <?= form_open('deployment/delete/' . $deployment->id, ['style' => 'display:inline;margin:0']) ?>
        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this deployment?')">Delete</button>
        <?= form_close() ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-item">
        <label>Environment</label>
        <span><a href="environment/show/<?= $deployment->env_id ?>" style="color:#6366f1;text-decoration:none"><?= htmlspecialchars($deployment->env_name) ?></a></span>
    </div>
    <div class="detail-item">
        <label>Server</label>
        <span><a href="server/show/<?= $deployment->server_id ?>" style="color:#6366f1;text-decoration:none"><?= htmlspecialchars($deployment->server_name) ?></a> <span style="color:#94a3b8;font-size:.8rem">(<?= htmlspecialchars($deployment->ip_address) ?>)</span></span>
    </div>
    <div class="detail-item">
        <label>Status</label>
        <span><span class="badge badge-<?= htmlspecialchars(str_replace('_', '-', $deployment->status)) ?>"><?= htmlspecialchars($deployment->status) ?></span></span>
    </div>
    <div class="detail-item">
        <label>Repository</label>
        <span style="word-break:break-all"><?= htmlspecialchars($deployment->repo_url ?: '—') ?></span>
    </div>
    <div class="detail-item">
        <label>Branch</label>
        <span><code style="color:#6366f1"><?= htmlspecialchars($deployment->branch) ?></code></span>
    </div>
    <div class="detail-item">
        <label>Web root</label>
        <span><code><?= htmlspecialchars($deployment->web_root) ?></code></span>
    </div>
    <?php if ($deployment->domain): ?>
    <div class="detail-item">
        <label>Domain</label>
        <span><?= htmlspecialchars($deployment->domain) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($deployment->db_name): ?>
    <div class="detail-item">
        <label>Database</label>
        <span><?= htmlspecialchars($deployment->db_name) ?></span>
    </div>
    <?php endif; ?>
    <div class="detail-item">
        <label>Created</label>
        <span><?= date('M j, Y H:i', strtotime($deployment->created_at)) ?></span>
    </div>
    <?php if (!empty($deployment->deployed_sha)): ?>
    <div class="detail-item">
        <label>Deployed SHA</label>
        <span><code style="font-size:.8rem;color:#6366f1"><?= htmlspecialchars(substr($deployment->deployed_sha, 0, 12)) ?></code></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($deployment->started_at)): ?>
    <div class="detail-item">
        <label>Started</label>
        <span><?= date('M j, Y H:i:s', strtotime($deployment->started_at)) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($deployment->finished_at)): ?>
    <div class="detail-item">
        <label>Finished</label>
        <span><?= date('M j, Y H:i:s', strtotime($deployment->finished_at)) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($latest_health)): ?>
    <div class="detail-item">
        <label>Last health check</label>
        <?php $hs = $latest_health->status; ?>
        <span>
            <span class="badge badge-<?= $hs === 'healthy' ? 'active' : ($hs === 'unhealthy' ? 'failed' : 'pending') ?>"><?= htmlspecialchars($hs) ?></span>
            <?php if ($latest_health->http_status): ?><code style="font-size:.78rem;margin-left:.4rem"><?= (int) $latest_health->http_status ?></code><?php endif; ?>
            <?php if ($latest_health->response_time_ms !== null): ?><span style="font-size:.78rem;color:#94a3b8;margin-left:.4rem"><?= $latest_health->response_time_ms ?>ms</span><?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Deployment Script</span>
            <?php if (!empty($deployment->script_name)): ?>
                <span style="margin-left:.6rem;font-size:.78rem;color:#6366f1">using: <strong><?= htmlspecialchars($deployment->script_name) ?></strong></span>
            <?php else: ?>
                <span style="margin-left:.6rem;font-size:.78rem;color:#94a3b8">default generated</span>
            <?php endif; ?>
        </div>
        <div class="actions-row">
            <button onclick="copyScript('deploy-script')" class="btn btn-secondary btn-sm">Copy</button>
        </div>
    </div>
    <!-- Script assignment -->
    <div style="padding:.75rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
        <span style="font-size:.8rem;color:#64748b;white-space:nowrap">Custom script:</span>
        <?= form_open('deployment/assign_script/' . $deployment->id, ['style' => 'display:flex;gap:.5rem;align-items:center;flex:1']) ?>
            <select name="script_id" class="form-control" style="max-width:260px;padding:.35rem .6rem;font-size:.82rem">
                <option value="">— default generated script —</option>
                <?php foreach ($deploy_scripts as $sc): ?>
                    <option value="<?= $sc->id ?>" <?= (int)($deployment->script_id ?? 0) === (int)$sc->id ? 'selected' : '' ?>><?= htmlspecialchars($sc->name) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($deploy_scripts)): ?>
                <a href="script/create?type=deploy" class="btn btn-secondary btn-sm" style="white-space:nowrap">+ Create Script</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
        <?= form_close() ?>
    </div>
    <div class="card-body" style="padding:0">
        <div class="code-block" id="deploy-script"><?= htmlspecialchars($deploy_script) ?></div>
    </div>
    <div style="padding:.75rem 1.25rem;border-top:1px solid #e2e8f0;font-size:.8rem;color:#64748b">
        Run this script as root on <strong><?= htmlspecialchars($deployment->server_name) ?></strong> after LAMP provisioning is complete.
        Then click <strong>Mark as Successful</strong> above.
    </div>
</div>

<div id="live-log-panel" style="display:none">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Deploying…</span>
            <span id="live-status-badge"></span>
        </div>
        <div style="padding:0">
            <pre id="live-log" style="margin:0;padding:1rem 1.25rem;font-size:.78rem;line-height:1.6;overflow-x:auto;overflow-y:auto;max-height:480px;background:#0f172a;color:#e2e8f0;border-radius:0 0 .5rem .5rem"></pre>
        </div>
    </div>
</div>

<script>
function startDeploy(id) {
    var btn = document.getElementById('deploy-btn');
    var panel = document.getElementById('live-log-panel');
    var log = document.getElementById('live-log');
    var badge = document.getElementById('live-status-badge');

    btn.disabled = true;
    btn.textContent = '⏳ Deploying…';
    log.textContent = '';
    panel.style.display = '';
    panel.scrollIntoView({behavior: 'smooth', block: 'start'});

    var es = new EventSource('<?= BASE_URL ?>deployment/stream/' + id);

    es.onmessage = function(e) {
        log.textContent += e.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('done', function(e) {
        es.close();
        var result = JSON.parse(e.data);
        var ok = result.status === 'success';

        panel.querySelector('.card-title').textContent = ok ? 'Deploy complete' : 'Deploy failed';

        badge.innerHTML = ok
            ? '<span class="badge badge-active">success</span>'
            : '<span class="badge badge-failed">failed</span>';

        // Update the status badge in the detail grid
        var statusBadge = document.querySelector('.detail-item .badge[class*="badge-"]');
        if (statusBadge) {
            statusBadge.className = 'badge badge-' + (ok ? 'active' : 'failed');
            statusBadge.textContent = result.status;
        }

        btn.disabled = false;
        btn.textContent = '↺ Re-deploy';

        if (ok && result.sha) {
            log.textContent += '\nSHA: ' + result.sha + '\n';
            log.scrollTop = log.scrollHeight;
        }
    });

    es.onerror = function() {
        if (es.readyState === EventSource.CLOSED) return;
        es.close();
        log.textContent += '\n[connection closed]\n';
        btn.disabled = false;
        btn.textContent = '↺ Re-deploy';
    };
}
</script>

<?php if (in_array($deployment->status, ['running', 'success', 'failed'], true)): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Run Log</span>
        <span style="font-size:.78rem;color:#64748b">
            <?php if ($deployment->status === 'running'): ?>
                <span class="badge badge-running">running</span>
            <?php elseif ($deployment->status === 'success'): ?>
                <span class="badge badge-active">success</span>
            <?php else: ?>
                <span class="badge badge-failed">failed</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="card-body" style="padding:0">
        <?php if (!empty($deployment->run_log)): ?>
            <pre style="margin:0;padding:1rem 1.25rem;font-size:.78rem;line-height:1.6;overflow-x:auto;background:#0f172a;color:#e2e8f0;border-radius:0 0 .5rem .5rem"><?= htmlspecialchars($deployment->run_log) ?></pre>
        <?php else: ?>
            <div style="padding:1rem 1.25rem;color:#94a3b8;font-size:.85rem">No output yet.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($deployment->db_name) && $deployment->status === 'success'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Database Access</span>
        <span style="font-size:.78rem;color:#64748b">SSH tunnel to MySQL — CLI or TablePlus</span>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:1.25rem">

        <div>
            <div style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.04em">1 — Open tunnel (run locally)</div>
            <div class="code-block" style="font-size:.82rem"><?= htmlspecialchars(
                'ssh -L 3307:127.0.0.1:3306 '
                . $deployment->ssh_user . '@' . $deployment->ip_address
                . ' -N'
            ) ?></div>
            <div style="font-size:.75rem;color:#94a3b8;margin-top:.35rem">Keep this terminal open for the duration of your session.</div>
        </div>

        <div>
            <div style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.04em">2a — MySQL CLI</div>
            <div class="code-block" style="font-size:.82rem"><?= htmlspecialchars(
                'mysql -u root -h 127.0.0.1 -P 3307 ' . $deployment->db_name
            ) ?></div>
        </div>

        <div>
            <div style="font-size:.78rem;font-weight:600;color:#475569;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.04em">2b — TablePlus / GUI (SSH tunnel mode)</div>
            <div style="display:grid;grid-template-columns:max-content 1fr;gap:.25rem .75rem;font-size:.82rem;font-family:monospace;color:#334155">
                <span style="color:#94a3b8">Host</span>     <span>127.0.0.1</span>
                <span style="color:#94a3b8">Port</span>     <span>3307</span>
                <span style="color:#94a3b8">User</span>     <span>root</span>
                <span style="color:#94a3b8">Database</span> <span><?= htmlspecialchars($deployment->db_name) ?></span>
            </div>
            <div style="font-size:.75rem;color:#94a3b8;margin-top:.5rem">
                TablePlus also supports native SSH tunnels — use host <code><?= htmlspecialchars($deployment->ip_address) ?></code>, SSH user <code><?= htmlspecialchars($deployment->ssh_user) ?></code>, and your SSH key.
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Services</span>
        <a href="environment-services/create?environment=<?= $deployment->env_id ?>" class="btn btn-secondary btn-sm">+ Add Service</a>
    </div>

    <?php if (empty($services)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#9889;</div>
            <div class="empty-title">No services attached</div>
            <div class="empty-desc">Attach MySQL, Redis, or other services to enable health checks.</div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr><th>Name</th><th>Type</th><th>Host : Port</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr>
                            <td><a href="environment-services/show/<?= $s->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none"><?= htmlspecialchars($s->name) ?></a></td>
                            <td><span class="badge badge-development"><?= htmlspecialchars(ucfirst($s->type)) ?></span></td>
                            <td><code style="font-size:.82rem"><?= htmlspecialchars($s->host) ?>:<?= (int) $s->port ?></code></td>
                            <td><span class="badge badge-<?= htmlspecialchars($s->status === 'running' ? 'active' : $s->status) ?>"><?= htmlspecialchars($s->status) ?></span></td>
                            <td>
                                <div class="actions-row">
                                    <?= form_open('server-health/check/service/' . $s->id, ['style' => 'display:inline;margin:0']) ?>
                                    <button type="submit" class="btn btn-secondary btn-sm">&#10003; Check</button>
                                    <?= form_close() ?>
                                    <a href="environment-services/show/<?= $s->id ?>" class="btn btn-secondary btn-sm">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($recent_events)): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Event History</span>
        <a href="event/timeline_for_deployment/<?= $deployment->id ?>" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div class="card-body" style="padding:0 1.25rem">
        <?php $events = $recent_events; include APPPATH . 'modules/event/views/timeline.php'; ?>
    </div>
</div>
<?php endif; ?>

