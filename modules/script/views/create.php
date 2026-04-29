<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="script">Scripts</a> <span class="breadcrumb-sep">/</span> New</div>
        <div class="page-title">New Script</div>
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
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars(post('name', true) ?: '') ?>" placeholder="My Deploy Script" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" id="script-type" class="form-control" onchange="updateVarRef(this.value)">
                            <option value="deploy" <?= ($default_type === 'deploy') ? 'selected' : '' ?>>Deploy Script</option>
                            <option value="lamp"   <?= ($default_type === 'lamp')   ? 'selected' : '' ?>>LAMP Setup Script</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description <span style="color:#94a3b8;font-weight:400">(optional)</span></label>
                    <input type="text" name="description" class="form-control" value="<?= htmlspecialchars(post('description', true) ?: '') ?>" placeholder="What does this script do?">
                </div>

                <div class="form-group">
                    <label class="form-label">Script body</label>
                    <span class="form-hint" style="margin-bottom:.4rem;display:block">Use <code>{{VARIABLE}}</code> placeholders — see the reference panel →</span>
                    <textarea name="body" class="form-control" rows="22" required style="font-family:monospace;font-size:.82rem;resize:vertical"><?= htmlspecialchars(post('body') ?: '') ?></textarea>
                </div>

                <div class="form-actions">
                    <?= form_close() ?>
                    <button type="submit" form="script-form" class="btn btn-primary">Save Script</button>
                    <a href="script" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Variable reference panel -->
    <div>
        <div class="card" style="position:sticky;top:4rem">
            <div class="card-header"><span class="card-title">Available Variables</span></div>
            <div class="card-body" style="padding:.75rem">
                <div id="deploy-vars">
                    <?php foreach ($deploy_vars as $var => $desc): ?>
                        <div style="margin-bottom:.6rem">
                            <code style="font-size:.75rem;color:#6366f1;cursor:pointer;display:block" onclick="insertVar('<?= htmlspecialchars($var) ?>')"><?= htmlspecialchars($var) ?></code>
                            <span style="font-size:.72rem;color:#64748b"><?= htmlspecialchars($desc) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="lamp-vars" style="display:none">
                    <?php foreach ($lamp_vars as $var => $desc): ?>
                        <div style="margin-bottom:.6rem">
                            <code style="font-size:.75rem;color:#6366f1;cursor:pointer;display:block" onclick="insertVar('<?= htmlspecialchars($var) ?>')"><?= htmlspecialchars($var) ?></code>
                            <span style="font-size:.72rem;color:#64748b"><?= htmlspecialchars($desc) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:.72rem;color:#94a3b8;margin-top:.75rem">Click a variable to insert it at the cursor.</p>
            </div>
        </div>
    </div>
</div>

<script src="js/script-editor.js"></script>
