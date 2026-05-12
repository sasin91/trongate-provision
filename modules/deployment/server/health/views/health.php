<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Health</div>
        <div style="font-size:.85rem;color:#64748b;margin-top:.25rem">Real-time TCP and HTTP probes for deployments and services</div>
    </div>
    <?= form_open('server-health/check_all', ['style' => 'display:inline;margin:0']) ?>
    <button type="submit" class="btn btn-primary">&#10003; Check All</button>
    <?= form_close() ?>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="stat-card">
        <div class="stat-label">Checks (24h)</div>
        <div class="stat-value"><?= (int) $stats->total ?></div>
    </div>
    <div class="stat-card" style="border-left:3px solid #22c55e">
        <div class="stat-label">Healthy</div>
        <div class="stat-value" style="color:#15803d"><?= (int) $stats->healthy ?></div>
    </div>
    <div class="stat-card" style="border-left:3px solid #ef4444">
        <div class="stat-label">Unhealthy</div>
        <div class="stat-value" style="color:#b91c1c"><?= (int) $stats->unhealthy ?></div>
    </div>
</div>

<!-- Deployments -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Deployments</span>
        <span style="font-size:.78rem;color:#94a3b8">HTTP probe on domain or server IP</span>
    </div>

    <?php if (empty($overview->deployments)): ?>
        <div class="empty-state">
            <div class="empty-desc">No deployments to monitor.</div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr>
                        <th>Deployment</th>
                        <th>Environment</th>
                        <th>Target</th>
                        <th>Health</th>
                        <th>Response</th>
                        <th>HTTP</th>
                        <th>Last checked</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overview->deployments as $d): ?>
                    <?php $hs = $d->health_status ?? 'unknown'; ?>
                        <tr>
                            <td>
                                <a href="deployment/show/<?= $d->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none">
                                    <?= htmlspecialchars($d->domain ?: '#'.$d->id) ?>
                                </a>
                                <div style="font-size:.75rem;color:#94a3b8"><?= htmlspecialchars($d->server_name) ?></div>
                            </td>
                            <td><span class="badge badge-development"><?= htmlspecialchars($d->env_name) ?></span></td>
                            <td><code style="font-size:.8rem"><?= htmlspecialchars($d->domain ?: $d->ip_address) ?></code></td>
                            <td>
                                <span class="badge badge-<?= $hs === 'healthy' ? 'active' : ($hs === 'unhealthy' ? 'failed' : 'pending') ?>">
                                    <?= htmlspecialchars($hs) ?>
                                </span>
                            </td>
                            <td><?= $d->response_time_ms !== null ? $d->response_time_ms.'ms' : '—' ?></td>
                            <td><?= $d->http_status ? '<code style="font-size:.8rem">'.(int)$d->http_status.'</code>' : '—' ?></td>
                            <td style="font-size:.78rem;color:#94a3b8">
                                <?= $d->checked_at ? date('M j H:i', strtotime($d->checked_at)) : 'Never' ?>
                            </td>
                            <td>
                                <?= form_open('server-health/check/deployment/' . $d->id, ['style' => 'display:inline;margin:0']) ?>
                                <button type="submit" class="btn btn-secondary btn-sm" title="Run check">&#10003;</button>
                                <?= form_close() ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Services -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Services</span>
        <span style="font-size:.78rem;color:#94a3b8">TCP port probe</span>
    </div>

    <?php if (empty($overview->services)): ?>
        <div class="empty-state">
            <div class="empty-desc">No services to monitor. <a href="environment-services/create" style="color:#6366f1">Add a service</a>.</div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Type</th>
                        <th>Host : Port</th>
                        <th>Health</th>
                        <th>Response</th>
                        <th>Last message</th>
                        <th>Last checked</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overview->services as $s): ?>
                    <?php $hs = $s->health_status ?? 'unknown'; ?>
                        <tr>
                            <td>
                                <a href="environment-services/show/<?= $s->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none">
                                    <?= htmlspecialchars($s->name) ?>
                                </a>
                                <div style="font-size:.75rem;color:#94a3b8"><?= htmlspecialchars($s->env_name) ?></div>
                            </td>
                            <td><span class="badge badge-development"><?= htmlspecialchars(ucfirst($s->type)) ?></span></td>
                            <td><code style="font-size:.82rem"><?= htmlspecialchars($s->host) ?>:<?= (int) $s->port ?></code></td>
                            <td>
                                <span class="badge badge-<?= $hs === 'healthy' ? 'active' : ($hs === 'unhealthy' ? 'failed' : 'pending') ?>">
                                    <?= htmlspecialchars($hs) ?>
                                </span>
                            </td>
                            <td><?= $s->response_time_ms !== null ? $s->response_time_ms.'ms' : '—' ?></td>
                            <td style="font-size:.8rem;color:#64748b;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($s->message ?? '') ?>">
                                <?= htmlspecialchars($s->message ?? '—') ?>
                            </td>
                            <td style="font-size:.78rem;color:#94a3b8">
                                <?= $s->checked_at ? date('M j H:i', strtotime($s->checked_at)) : 'Never' ?>
                            </td>
                            <td>
                                <?= form_open('server-health/check/service/' . $s->id, ['style' => 'display:inline;margin:0']) ?>
                                <button type="submit" class="btn btn-secondary btn-sm" title="Run check">&#10003;</button>
                                <?= form_close() ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
