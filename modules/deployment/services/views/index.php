<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Services</div>
        <div style="font-size:.85rem;color:#64748b;margin-top:.25rem">Databases, caches, and other services attached to your environments</div>
    </div>
    <a href="environment-services/create" class="btn btn-primary">+ New Service</a>
</div>

<?php if (empty($services)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">&#9889;</div>
            <div class="empty-title">No services yet</div>
            <div class="empty-desc">Attach a MySQL, Redis, or other service to an environment to track and health-check it.</div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Environment</th>
                        <th>Host : Port</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr>
                            <td><a href="environment-services/show/<?= $s->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none"><?= htmlspecialchars($s->name) ?></a></td>
                            <td><span class="badge badge-development"><?= htmlspecialchars($type_defaults[$s->type]['label'] ?? ucfirst($s->type)) ?></span></td>
                            <td><span style="font-size:.85rem"><?= htmlspecialchars($s->env_name) ?></span></td>
                            <td><code style="font-size:.82rem"><?= htmlspecialchars($s->host) ?>:<?= (int) $s->port ?></code></td>
                            <td><span class="badge badge-<?= htmlspecialchars($s->status) ?>"><?= htmlspecialchars($s->status) ?></span></td>
                            <td style="font-size:.8rem;color:#94a3b8"><?= date('M j, Y', strtotime($s->created_at)) ?></td>
                            <td><a href="environment-services/show/<?= $s->id ?>" class="btn btn-secondary btn-sm">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
