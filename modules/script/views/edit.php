<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="script">Scripts</a>
            <span class="breadcrumb-sep">/</span>
            <a href="script/show/<?= $script->id ?>"><?= htmlspecialchars($script->name) ?></a>
            <span class="breadcrumb-sep">/</span> Edit
        </div>
        <div class="page-title">Edit Script</div>
    </div>
</div>

<?php if (!empty($_SESSION['form_submission_errors'])): ?>
    <div class="alert alert-danger">
        <?php foreach ($_SESSION['form_submission_errors'] as $errs): ?>
            <?php foreach ((array) $errs as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
        <?php endforeach; unset($_SESSION['form_submission_errors']); ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start">
    <div class="card">
        <div class="card-body">
            <form method="post" action="<?= $form_location ?>" id="script-form">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Script name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars(post('name', true) ?: $script->name) ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($script->type)) ?>" disabled style="background:#f8fafc;color:#64748b">
                        <span class="form-hint">Type cannot be changed after creation</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="<?= htmlspecialchars(post('description', true) ?: ($script->description ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Script body</label>
                    <textarea name="body" class="form-control" rows="24" required style="font-family:monospace;font-size:.82rem;resize:vertical"><?= htmlspecialchars(post('body') ?: $script->body) ?></textarea>
                </div>

                <div class="form-actions">
                    <?= form_close() ?>
                    <button type="submit" form="script-form" class="btn btn-primary">Save Changes</button>
                    <a href="script/show/<?= $script->id ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="card" style="position:sticky;top:4rem">
            <div class="card-header"><span class="card-title">Available Variables</span></div>
            <div class="card-body" style="padding:.75rem">
                <?php $vars = $script->type === 'lamp' ? $lamp_vars : $deploy_vars; ?>
                <?php foreach ($vars as $var => $desc): ?>
                    <div style="margin-bottom:.6rem">
                        <code style="font-size:.75rem;color:#6366f1;cursor:pointer;display:block" onclick="insertVar('<?= htmlspecialchars($var) ?>')"><?= htmlspecialchars($var) ?></code>
                        <span style="font-size:.72rem;color:#64748b"><?= htmlspecialchars($desc) ?></span>
                    </div>
                <?php endforeach; ?>
                <p style="font-size:.72rem;color:#94a3b8;margin-top:.75rem">Click a variable to insert it at the cursor.</p>
            </div>
        </div>
    </div>
</div>

<script src="js/script-editor.js"></script>
