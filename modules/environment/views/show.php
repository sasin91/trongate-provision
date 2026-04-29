<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="environment">Environments</a> <span class="breadcrumb-sep">/</span> <?= htmlspecialchars($env->name) ?></div>
        <div class="page-title"><?= htmlspecialchars($env->name) ?></div>
    </div>
    <div class="actions-row">
        <a href="deployment/create?env=<?= $env->id ?>" class="btn btn-primary">+ Deploy</a>
        <a href="environment/variables/<?= $env->id ?>" class="btn btn-secondary">&#128274; Variables</a>
        <?= form_open('environment/delete/' . $env->id, ['style' => 'display:inline;margin:0']) ?>
        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this environment and all its data?')">Delete</button>
        <?= form_close() ?>
    </div>
</div>

<div class="detail-grid" style="margin-bottom:1.5rem">
    <div class="detail-item">
        <label>Web root</label>
        <span><code><?= htmlspecialchars($env->web_root) ?></code></span>
    </div>
    <div class="detail-item">
        <label>PHP Version</label>
        <span>PHP <?= htmlspecialchars($env->php_version) ?></span>
    </div>
    <?php if ($env->domain): ?>
    <div class="detail-item">
        <label>Domain</label>
        <span><?= htmlspecialchars($env->domain) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($env->db_name): ?>
    <div class="detail-item">
        <label>Database</label>
        <span><?= htmlspecialchars($env->db_name) ?></span>
    </div>
    <?php endif; ?>
    <div class="detail-item">
        <label>Created</label>
        <span><?= date('M j, Y H:i', strtotime($env->created_at)) ?></span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Servers</span>
        <a href="server/create?env=<?= $env->id ?>" class="btn btn-secondary btn-sm">+ Add Server</a>
    </div>

    <?php if (empty($servers)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#9646;</div>
            <div class="empty-title">No servers yet</div>
            <div class="empty-desc">Add a server to use as a deployment target for this environment.</div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $s): ?>
                        <tr>
                            <td><a href="server/show/<?= $s->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none"><?= htmlspecialchars($s->name) ?></a></td>
                            <td><code style="font-size:.85rem"><?= htmlspecialchars($s->ip_address) ?></code></td>
                            <td><span class="badge badge-<?= htmlspecialchars($s->status) ?>"><?= htmlspecialchars($s->status) ?></span></td>
                            <td>
                                <div class="actions-row">
                                    <a href="server/show/<?= $s->id ?>" class="btn btn-secondary btn-sm">View</a>
                                    <a href="deployment/create?env=<?= $env->id ?>&server=<?= $s->id ?>" class="btn btn-primary btn-sm">Deploy Here</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Services</span>
        <a href="environment-services/create?environment=<?= $env->id ?>" class="btn btn-secondary btn-sm">+ Add Service</a>
    </div>

    <?php if (empty($services)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#9889;</div>
            <div class="empty-title">No services yet</div>
            <div class="empty-desc">Attach MySQL, Redis, or other services to track and health-check them.</div>
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
                            <td><span class="badge badge-development"><?= htmlspecialchars($type_defaults[$s->type]['label'] ?? ucfirst($s->type)) ?></span></td>
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
