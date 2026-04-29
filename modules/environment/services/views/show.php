<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="environment-services">Services</a>
            <span class="breadcrumb-sep">/</span>
            <?= htmlspecialchars($service->name) ?>
        </div>
        <div class="page-title"><?= htmlspecialchars($service->name) ?></div>
    </div>
    <div class="actions-row">
        <?= form_open('server-health/check/service/' . $service->id, ['style' => 'display:inline;margin:0']) ?>
        <button type="submit" class="btn btn-secondary">&#10003; Check Health</button>
        <?= form_close() ?>
        <?php if ($service->status !== 'running'): ?>
            <?= form_open('environment-services/mark_running/' . $service->id, ['style' => 'display:inline;margin:0']) ?>
            <button type="submit" class="btn btn-primary">Mark as Running</button>
            <?= form_close() ?>
        <?php endif; ?>
        <?= form_open('environment-services/delete/' . $service->id, ['style' => 'display:inline;margin:0']) ?>
        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this service?')">Delete</button>
        <?= form_close() ?>
    </div>
</div>

<div class="detail-grid">
    <div class="detail-item">
        <label>Type</label>
        <span><span class="badge badge-development"><?= htmlspecialchars($type_label) ?></span></span>
    </div>
    <div class="detail-item">
        <label>Status</label>
        <span>
            <?php
            $hs = $latest_health->status ?? 'unknown';
            $badge = match($hs) { 'healthy' => 'active', 'unhealthy' => 'failed', default => 'pending' };
            ?>
            <span class="badge badge-<?= htmlspecialchars($service->status === 'running' ? 'active' : $service->status) ?>"><?= htmlspecialchars($service->status) ?></span>
        </span>
    </div>
    <div class="detail-item">
        <label>Host</label>
        <span><code><?= htmlspecialchars($service->host) ?>:<?= (int) $service->port ?></code></span>
    </div>
    <div class="detail-item">
        <label>Environment</label>
        <span><a href="environment/show/<?= $service->environment_id ?>" style="color:#6366f1;text-decoration:none"><?= htmlspecialchars($service->env_name) ?></a></span>
    </div>
    <?php if (!empty($latest_health)): ?>
    <div class="detail-item">
        <label>Last health check</label>
        <span>
            <span class="badge badge-<?= $hs === 'healthy' ? 'active' : ($hs === 'unhealthy' ? 'failed' : 'pending') ?>"><?= $hs ?></span>
            <?php if ($latest_health->response_time_ms !== null): ?>
                <span style="font-size:.78rem;color:#94a3b8;margin-left:.5rem"><?= $latest_health->response_time_ms ?>ms</span>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Health History</span>
        <?= form_open('server-health/check/service/' . $service->id, ['style' => 'display:inline;margin:0']) ?>
        <button type="submit" class="btn btn-secondary btn-sm">&#10003; Run Check Now</button>
        <?= form_close() ?>
    </div>

    <?php if (empty($history)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#10003;</div>
            <div class="empty-title">No health checks yet</div>
            <div class="empty-desc">Click "Check Health" to probe this service's port.</div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr><th>Status</th><th>Message</th><th>Response time</th><th>Checked at</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><span class="badge badge-<?= $h->status === 'healthy' ? 'active' : ($h->status === 'unhealthy' ? 'failed' : 'pending') ?>"><?= htmlspecialchars($h->status) ?></span></td>
                            <td style="font-size:.82rem;color:#64748b"><?= htmlspecialchars($h->message ?? '—') ?></td>
                            <td><?= $h->response_time_ms !== null ? $h->response_time_ms . 'ms' : '—' ?></td>
                            <td style="font-size:.8rem;color:#94a3b8"><?= date('M j H:i:s', strtotime($h->checked_at)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
