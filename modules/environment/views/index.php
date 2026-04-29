<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Environments</div>
        <div style="font-size:.85rem;color:#64748b;margin-top:.25rem">Your app definitions — repo, runtime, and config</div>
    </div>
    <a href="environment/create" class="btn btn-primary">+ New Environment</a>
</div>

<?php if (empty($environments)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">&#9670;</div>
            <div class="empty-title">No environments yet</div>
            <div class="empty-desc">Create your first environment to get started.</div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="ptable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Servers</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($environments as $env): ?>
                        <tr>
                            <td><a href="environment/show/<?= $env->id ?>" style="color:#6366f1;font-weight:600;text-decoration:none"><?= htmlspecialchars($env->name) ?></a></td>
                            <td><?= (int) $env->server_count ?></td>
                            <td style="color:#94a3b8;font-size:.8rem"><?= date('M j, Y', strtotime($env->created_at)) ?></td>
                            <td>
                                <div class="actions-row">
                                    <a href="environment/show/<?= $env->id ?>" class="btn btn-secondary btn-sm">View</a>
                                    <?= form_open('environment/delete/' . $env->id, ['style' => 'display:inline;margin:0']) ?>
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this environment and all its servers?')">Delete</button>
                                    <?= form_close() ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
