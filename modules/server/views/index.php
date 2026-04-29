<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Servers</div>
        <div style="font-size:.85rem;color:#64748b;margin-top:.25rem">All registered servers across your environments</div>
    </div>
    <a href="server/create" class="btn btn-primary">+ Add Server</a>
</div>

<?php if (empty($servers)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">&#9646;</div>
            <div class="empty-title">No servers yet</div>
            <div class="empty-desc">Add a server to generate a LAMP provisioning script.</div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Environment</th>
                        <th>IP Address</th>
                        <th>PHP</th>
                        <th>Status</th>
                        <th>Deployments</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $s): ?>
                        <tr>
                            <td><a href="server/show/<?= $s->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none"><?= htmlspecialchars($s->name) ?></a></td>
                            <td><?= htmlspecialchars($s->env_name) ?></td>
                            <td><code style="font-size:.85rem"><?= htmlspecialchars($s->ip_address) ?></code></td>
                            <td>PHP <?= htmlspecialchars($s->php_version) ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars($s->status) ?>"><?= htmlspecialchars($s->status) ?></span></td>
                            <td><?= (int) $s->deploy_count ?></td>
                            <td>
                                <div class="actions-row">
                                    <a href="server/show/<?= $s->id ?>" class="btn btn-secondary btn-sm">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
