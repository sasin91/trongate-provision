<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Scripts</div>
        <div style="font-size:.85rem;color:#64748b;margin-top:.25rem">Custom LAMP setup and deployment scripts with template variable support</div>
    </div>
    <div class="actions-row">
        <a href="script/create?type=lamp" class="btn btn-secondary">+ LAMP Script</a>
        <a href="script/create?type=deploy" class="btn btn-primary">+ Deploy Script</a>
    </div>
</div>

<?php if (empty($scripts)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">&#128196;</div>
            <div class="empty-title">No custom scripts yet</div>
            <div class="empty-desc">
                Create a custom <strong>deploy</strong> or <strong>LAMP setup</strong> script.<br>
                Assign it to a deployment to override the default generated script.
            </div>
            <div style="margin-top:1rem;display:flex;gap:.75rem;justify-content:center">
                <a href="script/create?type=lamp" class="btn btn-secondary">New LAMP Script</a>
                <a href="script/create?type=deploy" class="btn btn-primary">New Deploy Script</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php
    $by_type = ['lamp' => [], 'deploy' => []];
    foreach ($scripts as $s) { $by_type[$s->type][] = $s; }
    $labels = ['lamp' => 'LAMP Setup Scripts', 'deploy' => 'Deployment Scripts'];
    ?>
    <?php foreach (['lamp', 'deploy'] as $type): ?>
        <?php if (!empty($by_type[$type])): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><?= $labels[$type] ?></span>
                <a href="script/create?type=<?= $type ?>" class="btn btn-secondary btn-sm">+ New</a>
            </div>
            <div class="table-wrap">
                <table class="ptable">
                    <thead>
                        <tr><th>Name</th><th>Description</th><th>Updated</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($by_type[$type] as $s): ?>
                            <tr>
                                <td><a href="script/show/<?= $s->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none"><?= htmlspecialchars($s->name) ?></a></td>
                                <td style="color:#64748b;font-size:.82rem"><?= htmlspecialchars($s->description ?: '—') ?></td>
                                <td style="font-size:.8rem;color:#94a3b8"><?= date('M j, Y', strtotime($s->updated_at)) ?></td>
                                <td>
                                    <div class="actions-row">
                                        <a href="script/edit/<?= $s->id ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <a href="script/delete/<?= $s->id ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this script? Deployments using it will fall back to the default.')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
