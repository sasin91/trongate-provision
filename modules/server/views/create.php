<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="server">Servers</a> <span class="breadcrumb-sep">/</span> New</div>
        <div class="page-title">Add Server</div>
    </div>
    <?php if (!$has_hetzner): ?>
        <a href="provider/connect" class="btn btn-secondary btn-sm">Connect Hetzner →</a>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['form_submission_errors'])): ?>
    <div class="alert alert-danger">
        <?php foreach ($_SESSION['form_submission_errors'] as $errs): ?>
            <?php foreach ((array) $errs as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
        <?php endforeach; unset($_SESSION['form_submission_errors']); ?>
    </div>
<?php endif; ?>

<?php if (empty($environments)): ?>
    <div class="alert alert-danger">You need to create an <a href="environment/create" class="danger-link">environment</a> first.</div>
<?php else: ?>

<!-- Tab bar -->
<div id="server-form-root" class="server-form-tabs">
    <?php if ($has_hetzner): ?>
    <button class="tab-btn active" data-tab="hetzner" onclick="switchTab('hetzner')">
        &#9729; Hetzner Cloud
    </button>
    <button class="tab-btn" data-tab="import" onclick="switchTab('import')">
        &#8645; Import Existing
    </button>
    <?php endif; ?>
    <button class="tab-btn <?= !$has_hetzner ? 'active' : '' ?>" data-tab="manual" onclick="switchTab('manual')">
        &#9646; Manual / Existing Server
    </button>
</div>

<!-- Shared: environment + name -->
<div class="form-wrap form-wrap-wide">
<div class="form-group" id="shared-fields">
    <label class="form-label">Environment</label>
    <select id="shared-env" class="form-control">
        <option value="">— select environment —</option>
        <?php foreach ($environments as $env): ?>
            <option value="<?= $env->id ?>" <?= ((int)post('environment_id') === (int)$env->id || $preselected_env === (int)$env->id) ? 'selected' : '' ?>>
                <?= htmlspecialchars($env->name) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="form-group">
    <label class="form-label">Server name</label>
    <input type="text" id="shared-name" class="form-control" value="<?= htmlspecialchars(post('name', true) ?: '') ?>" placeholder="e.g. web-01" autofocus>
</div>
</div>

<?php if ($has_hetzner): ?>
<!-- ── Hetzner tab ──────────────────────────────────────────── -->
<div id="tab-hetzner" class="card">
    <div class="card-body">
        <form method="post" action="<?= $form_location ?>" id="form-hetzner">
            <input type="hidden" name="provider" value="hetzner">
            <input type="hidden" name="environment_id" id="hz-env">
            <input type="hidden" name="name"           id="hz-name">

            <div class="form-group">
                <label class="form-label">Region</label>
                <select name="region" id="hz-region" class="form-control" required
                        mx-get="server/server_types_options?location=${this.value}"
                        mx-trigger="change"
                        mx-target="#type-grid"
                        mx-swap="innerHTML">
                    <option value="">— select region —</option>
                    <?php foreach ($hetzner_regions as $r): ?>
                        <option value="<?= htmlspecialchars($r['id']) ?>">
                            <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['country']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Server plan</label>
                <div id="type-grid" class="server-card-grid server-card-grid-sm">
                    <?php foreach ($hetzner_types as $t): ?>
                        <label class="type-card">
                            <input type="radio" name="server_type" value="<?= htmlspecialchars($t['id']) ?>" onchange="markSelected(this)">
                            <div class="type-name"><?= htmlspecialchars($t['name']) ?></div>
                            <div class="type-spec">
                                <?= $t['vcpus'] ?> vCPU &middot; <?= round($t['memory'] / 1024, 0) ?> GB RAM &middot; <?= $t['disk'] ?> GB disk
                            </div>
                            <div class="type-price">
                                &euro;<?= number_format($t['price_monthly'], 2) ?>/mo
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-actions">
                <?= form_close() ?>
                <button type="submit" form="form-hetzner" class="btn btn-primary">Provision on Hetzner</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Import tab ──────────────────────────────────────────── -->
<div id="tab-import" class="card is-hidden">
    <div class="card-body">
        <form method="post" action="<?= $form_location ?>" id="form-import">
            <input type="hidden" name="provider" value="hetzner_import">
            <input type="hidden" name="environment_id" id="imp-env">
            <input type="hidden" name="name"           id="imp-name">

            <?php if (empty($hetzner_servers)): ?>
                <p class="empty-muted">No untracked servers found in your Hetzner account.</p>
            <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Select an existing Hetzner server</label>
                    <div class="server-card-grid server-card-grid-md">
                        <?php foreach ($hetzner_servers as $s): ?>
                            <label class="import-card">
                                <input type="radio" name="hetzner_id" value="<?= htmlspecialchars($s['id']) ?>"
                                       data-name="<?= htmlspecialchars($s['name']) ?>"
                                       onchange="markImportSelected(this)">
                                <div class="s-name"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="s-meta">
                                    <?= htmlspecialchars($s['type']) ?> &middot; <?= htmlspecialchars($s['region']) ?>
                                </div>
                                <div class="server-ip">
                                    <code><?= htmlspecialchars($s['ip']) ?></code>
                                </div>
                                <div class="card-badge-row">
                                    <span class="badge badge-<?= $s['status'] === 'active' ? 'active' : 'pending' ?>"><?= htmlspecialchars($s['status']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <?= form_close() ?>
                    <button type="submit" form="form-import" class="btn btn-primary">Import Server</button>
                    <a href="server" class="btn btn-secondary">Cancel</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Manual tab ──────────────────────────────────────────── -->
<div id="tab-manual" class="card <?= $has_hetzner ? 'is-hidden' : '' ?>">
    <div class="card-body">
        <div class="form-wrap">
            <form method="post" action="<?= $form_location ?>" id="form-manual">
                <input type="hidden" name="provider" value="manual">
                <input type="hidden" name="environment_id" id="m-env">
                <input type="hidden" name="name"           id="m-name">

                <div class="form-group">
                    <label class="form-label">IP address</label>
                    <input type="text" name="ip_address" class="form-control" value="<?= htmlspecialchars(post('ip_address', true) ?: '') ?>" placeholder="203.0.113.42" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SSH user</label>
                        <input type="text" name="ssh_user" class="form-control" value="<?= htmlspecialchars(post('ssh_user', true) ?: 'root') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">SSH port</label>
                        <input type="number" name="ssh_port" class="form-control" value="<?= htmlspecialchars(post('ssh_port', true) ?: '22') ?>" required>
                    </div>
                </div>
                <div class="form-actions">
                    <?= form_close() ?>
                    <button type="submit" form="form-manual" class="btn btn-primary">Add Server</button>
                    <a href="server" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="server_module/js/form.js"></script>
