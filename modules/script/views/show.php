<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="script">Scripts</a> <span class="breadcrumb-sep">/</span> <?= htmlspecialchars($script->name) ?></div>
        <div class="page-title"><?= htmlspecialchars($script->name) ?></div>
    </div>
    <div class="actions-row">
        <?php if ($script->type === 'deploy'): ?>
            <a href="deployment/create?script=<?= (int) $script->id ?>" class="btn btn-primary">Create Deployment</a>
        <?php endif; ?>
        <a href="script/edit/<?= $script->id ?>" class="btn btn-primary">Edit</a>
        <a href="script/delete/<?= $script->id ?>" class="btn btn-danger" onclick="return confirm('Delete this script? Deployments using it will fall back to the default.')">Delete</a>
    </div>
</div>

<div class="detail-grid" style="margin-bottom:1.5rem">
    <div class="detail-item">
        <label>Type</label>
        <span><span class="badge badge-development"><?= htmlspecialchars(ucfirst($script->type)) ?> Script</span></span>
    </div>
    <div class="detail-item">
        <label>Description</label>
        <span><?= htmlspecialchars($script->description ?: '—') ?></span>
    </div>
    <div class="detail-item">
        <label>Created</label>
        <span><?= date('M j, Y H:i', strtotime($script->created_at)) ?></span>
    </div>
    <div class="detail-item">
        <label>Updated</label>
        <span><?= date('M j, Y H:i', strtotime($script->updated_at)) ?></span>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 260px;gap:1.25rem;align-items:start">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Script Body</span>
            <button onclick="copyScript('script-body')" class="btn btn-secondary btn-sm">Copy</button>
        </div>
        <div style="padding:0">
            <div class="code-block" id="script-body"><?= htmlspecialchars($script->body) ?></div>
        </div>
        <div style="padding:.75rem 1.25rem;border-top:1px solid #e2e8f0;font-size:.78rem;color:#64748b">
            Variables like <code>{{PHP_VERSION}}</code> are substituted at script generation time when this script is used by a deployment.
        </div>
    </div>

    <div>
        <div class="card" style="position:sticky;top:4rem">
            <div class="card-header"><span class="card-title">Variable Reference</span></div>
            <div class="card-body" style="padding:.75rem">
                <?php foreach ($available_vars as $var => $desc): ?>
                    <div style="margin-bottom:.6rem">
                        <code style="font-size:.75rem;color:#6366f1;display:block"><?= htmlspecialchars($var) ?></code>
                        <span style="font-size:.72rem;color:#64748b"><?= htmlspecialchars($desc) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($script->type === 'deploy'): ?>
        <div class="card" style="margin-top:1rem">
            <div class="card-header"><span class="card-title">Use for Deployment</span></div>
            <div class="card-body" style="font-size:.82rem;color:#64748b">
                Create a staged deployment with this script preselected, or assign it to an existing deployment.
                <br><br>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <a href="deployment/create?script=<?= (int) $script->id ?>" class="btn btn-primary btn-sm" style="display:inline-flex">Create Deployment</a>
                    <a href="deployment" class="btn btn-secondary btn-sm" style="display:inline-flex">View Deployments</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

