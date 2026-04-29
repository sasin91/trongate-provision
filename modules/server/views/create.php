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
    <div class="alert alert-danger">You need to create an <a href="environment/create" style="color:#b91c1c;font-weight:600">environment</a> first.</div>
<?php else: ?>

<!-- Tab bar -->
<div id="server-form-root" style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:1.5rem">
    <?php if ($has_hetzner): ?>
    <button class="tab-btn active" data-tab="hetzner" onclick="switchTab('hetzner')" style="padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;border:none;background:none;cursor:pointer;color:#6366f1;border-bottom:2px solid #6366f1;margin-bottom:-2px">
        &#9729; Hetzner Cloud
    </button>
    <button class="tab-btn" data-tab="import" onclick="switchTab('import')" style="padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;border:none;background:none;cursor:pointer;color:#64748b">
        &#8645; Import Existing
    </button>
    <?php endif; ?>
    <button class="tab-btn <?= !$has_hetzner ? 'active' : '' ?>" data-tab="manual" onclick="switchTab('manual')" style="padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;border:none;background:none;cursor:pointer;color:<?= !$has_hetzner ? '#6366f1' : '#64748b' ?>;<?= !$has_hetzner ? 'border-bottom:2px solid #6366f1;margin-bottom:-2px' : '' ?>">
        &#9646; Manual / Existing Server
    </button>
</div>

<!-- Shared: environment + name -->
<div class="form-wrap" style="max-width:600px">
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
                <div id="type-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem;margin-top:.25rem">
                    <?php foreach ($hetzner_types as $t): ?>
                        <label class="type-card" style="border:1.5px solid #e2e8f0;border-radius:.5rem;padding:.875rem;cursor:pointer;transition:border-color .15s">
                            <input type="radio" name="server_type" value="<?= htmlspecialchars($t['id']) ?>" style="display:none" onchange="markSelected(this)">
                            <div style="font-weight:600;font-size:.875rem;color:#0f172a"><?= htmlspecialchars($t['name']) ?></div>
                            <div style="font-size:.78rem;color:#64748b;margin-top:.2rem">
                                <?= $t['vcpus'] ?> vCPU &middot; <?= round($t['memory'] / 1024, 0) ?> GB RAM &middot; <?= $t['disk'] ?> GB disk
                            </div>
                            <div style="font-size:.85rem;font-weight:600;color:#6366f1;margin-top:.4rem">
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
<div id="tab-import" class="card" style="display:none">
    <div class="card-body">
        <form method="post" action="<?= $form_location ?>" id="form-import">
            <input type="hidden" name="provider" value="hetzner_import">
            <input type="hidden" name="environment_id" id="imp-env">
            <input type="hidden" name="name"           id="imp-name">

            <?php if (empty($hetzner_servers)): ?>
                <p style="color:#64748b;font-size:.875rem">No untracked servers found in your Hetzner account.</p>
            <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Select an existing Hetzner server</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.75rem;margin-top:.25rem">
                        <?php foreach ($hetzner_servers as $s): ?>
                            <label class="import-card" style="border:1.5px solid #e2e8f0;border-radius:.5rem;padding:.875rem;cursor:pointer;transition:border-color .15s">
                                <input type="radio" name="hetzner_id" value="<?= htmlspecialchars($s['id']) ?>"
                                       data-name="<?= htmlspecialchars($s['name']) ?>"
                                       style="display:none" onchange="markImportSelected(this)">
                                <div style="font-weight:600;font-size:.875rem;color:#0f172a"><?= htmlspecialchars($s['name']) ?></div>
                                <div style="font-size:.78rem;color:#64748b;margin-top:.2rem">
                                    <?= htmlspecialchars($s['type']) ?> &middot; <?= htmlspecialchars($s['region']) ?>
                                </div>
                                <div style="font-size:.8rem;color:#0f172a;margin-top:.25rem">
                                    <code style="font-size:.8rem"><?= htmlspecialchars($s['ip']) ?></code>
                                </div>
                                <div style="margin-top:.4rem">
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
<div id="tab-manual" class="card" style="<?= $has_hetzner ? 'display:none' : '' ?>">
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

<script src="js/server-form.js"></script>
