<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="environment">Environments</a> <span class="breadcrumb-sep">/</span> New</div>
        <div class="page-title">New Environment</div>
    </div>
</div>

<?php if (!empty($_SESSION['form_submission_errors'])): ?>
    <div class="alert alert-danger">
        <?php foreach ($_SESSION['form_submission_errors'] as $errs): ?>
            <?php foreach ((array) $errs as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        <?php endforeach; unset($_SESSION['form_submission_errors']); ?>
    </div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="form-wrap">
            <form method="post" action="<?= $form_location ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="env-name" class="form-control"
                               value="<?= htmlspecialchars(post('name', true) ?: '') ?>"
                               placeholder="e.g. Production, Staging" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">PHP Version</label>
                        <select name="php_version" class="form-control" required>
                            <?php foreach ($php_versions as $v): ?>
                                <option value="<?= $v ?>" <?= (post('php_version', true) ?: '8.4') === $v ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Domain <span style="color:#94a3b8;font-weight:400">(optional)</span></label>
                    <input type="text" name="domain" class="form-control"
                           value="<?= htmlspecialchars(post('domain', true) ?: '') ?>"
                           placeholder="example.com">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Environment</button>
                    <a href="environment" class="btn btn-secondary">Cancel</a>
                </div>
                <?= form_close() ?>
        </div>
    </div>
</div>
