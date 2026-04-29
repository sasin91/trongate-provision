<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="environment-services">Services</a> <span class="breadcrumb-sep">/</span> New</div>
        <div class="page-title">New Service</div>
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

<?php if (empty($environments)): ?>
    <div class="alert alert-danger">You need to <a href="environment/create" style="color:#b91c1c;font-weight:600">create an environment</a> first.</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="form-wrap">
            <form method="post" action="<?= $form_location ?>" id="svc-form">
                <div class="form-group">
                    <label class="form-label">Environment</label>
                    <select name="environment_id" class="form-control" required>
                        <option value="">— select environment —</option>
                        <?php foreach ($environments as $e): ?>
                            <option value="<?= $e->id ?>" <?= ((int)post('environment_id') === (int)$e->id || $preselected === (int)$e->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($e->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Service name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars(post('name', true) ?: '') ?>" placeholder="e.g. Primary DB" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control" id="svc-type" required onchange="applyDefaults(this.value)"
                                data-ports='<?= htmlspecialchars(json_encode(array_map(fn($v) => $v['port'], $type_defaults))) ?>'>
                            <?php foreach ($type_defaults as $t => $info): ?>
                                <option value="<?= $t ?>" <?= (post('type', true) === $t || (!post('type', true) && $t === 'mariadb')) ? 'selected' : '' ?>><?= htmlspecialchars($info['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Host</label>
                        <input type="text" name="host" id="svc-host" class="form-control" value="<?= htmlspecialchars(post('host', true) ?: '127.0.0.1') ?>" required>
                        <span class="form-hint">IP or hostname of the service</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Port</label>
                        <input type="number" name="port" id="svc-port" class="form-control" value="<?= htmlspecialchars(post('port', true) ?: '3306') ?>" required>
                    </div>
                </div>

                <div class="form-actions">
                    <?= form_close() ?>
                    <button type="submit" form="svc-form" class="btn btn-primary">Add Service</button>
                    <a href="environment-services" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/service-form.js"></script>
<?php endif; ?>
