<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Deployments</div>
        <div style="font-size:.85rem;color:#64748b;margin-top:.25rem">Deploy code as a staged release, then promote it when the database is ready</div>
    </div>
    <a href="deployment/create" class="btn btn-primary">+ New Deployment</a>
</div>

<?php if (empty($deployments)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">&#10148;</div>
            <div class="empty-title">No deployments yet</div>
            <div class="empty-desc">Create a deployment to stage a release under /var/www/releases before switching live traffic.</div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr>
                        <th>Server</th>
                        <th>Environment</th>
                        <th>Repository</th>
                        <th>Branch</th>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deployments as $d): ?>
                        <tr>
                            <td>
                                <a href="server/show/<?= $d->server_id ?? '' ?>" style="color:#6366f1;font-weight:600;text-decoration:none">
                                    <?= htmlspecialchars($d->server_name) ?>
                                </a>
                                <div style="font-size:.75rem;color:#94a3b8"><?= htmlspecialchars($d->ip_address) ?></div>
                            </td>
                            <td><span class="badge badge-development"><?= htmlspecialchars($d->env_name) ?></span></td>
                            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($d->repo_url ?: '—') ?>
                            </td>
                            <td><code style="font-size:.8rem;color:#6366f1"><?= htmlspecialchars($d->branch) ?></code></td>
                            <td><?= htmlspecialchars($d->domain ?: '—') ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars(str_replace('_', '-', $d->status)) ?>"><?= htmlspecialchars($d->status) ?></span></td>
                            <td style="font-size:.8rem;color:#94a3b8"><?= date('M j, Y', strtotime($d->created_at)) ?></td>
                            <td><a href="deployment/show/<?= $d->id ?>" class="btn btn-secondary btn-sm">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
