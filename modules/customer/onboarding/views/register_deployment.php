<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Deployment — Provision Setup</title>
    <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
</head>
<body>

<?php
$is_hetzner  = ($provider === 'hetzner');
$active_tab  = (post('provider', true) === 'hetzner_import') ? 'import' : 'new';
$back_url    = $is_hetzner ? 'customer-onboarding/server_hetzner' : 'customer-onboarding/server_manual';
$_src_partial = __DIR__ . '/_source_fields.php';
?>

<div class="onboarding-card onboarding-card--<?= $is_hetzner ? 'wide' : 'standard' ?>">
    <div class="onboarding-header">
        <h1>&#9654; Create Deployment</h1>
        <p><?= $is_hetzner
            ? 'Select or provision a Hetzner server. Provision will link it to your environment and generate a deployment script.'
            : 'Confirm your server and environment. Provision will generate a deployment script to run on your server.'
        ?></p>
    </div>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?php if (!$is_hetzner): ?>
    <!-- ── Manual: summary + one-click confirm ───────────────── -->
    <div class="summary-box">
        <div class="summary-row">
            <span>Server</span>
            <strong><?= htmlspecialchars($server->name) ?> &mdash; <?= htmlspecialchars($server->ip_address) ?></strong>
        </div>
        <div class="summary-row">
            <span>Environment</span>
            <strong><?= htmlspecialchars($env->name) ?> &middot; PHP <?= htmlspecialchars($env->php_version) ?></strong>
        </div>
    </div>

    <?= form_open('customer-onboarding/submit_register_deployment', ['enctype' => 'multipart/form-data']) ?>
    <input type="hidden" name="provider" value="manual">

    <?php include $_src_partial ?>

    <div class="steps">
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot active"></div>
    </div>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Create Deployment &amp; Finish &#10148;
    </button>
    <?= form_close() ?>

    <?php else: ?>
    <!-- ── Hetzner: environment info + server selection ──────── -->
    <div class="summary-box">
        <div class="summary-row">
            <span>Environment</span>
            <strong><?= htmlspecialchars($env->name) ?> &middot; PHP <?= htmlspecialchars($env->php_version) ?></strong>
        </div>
    </div>

    <div class="tab-nav">
        <button type="button" class="tab-btn <?= $active_tab === 'new' ? 'active' : '' ?>" data-register-tab="new">&#9729; New Server</button>
        <?php if (!empty($importable)): ?>
        <button type="button" class="tab-btn <?= $active_tab === 'import' ? 'active' : '' ?>" data-register-tab="import">&#8645; Import Existing</button>
        <?php endif; ?>
    </div>

    <!-- Tab: New server -->
    <div id="tab-new" class="<?= $active_tab !== 'new' ? 'is-hidden' : '' ?>">
        <?= form_open('customer-onboarding/submit_register_deployment', ['enctype' => 'multipart/form-data']) ?>
        <input type="hidden" name="provider" value="hetzner">
        <input type="hidden" name="environment_id" value="<?= (int) $env->id ?>">

        <?php include $_src_partial ?>

        <div class="form-group">
            <label class="form-label" for="new-name">Server name</label>
            <input type="text" name="name" id="new-name" class="form-input"
                placeholder="web-01"
                value="<?= htmlspecialchars($active_tab === 'new' ? (post('name', true) ?: '') : '') ?>"
                <?= $active_tab === 'new' ? 'autofocus' : '' ?>>
        </div>

        <div class="form-group">
            <label class="form-label" for="new-region">Region</label>
            <select name="region" id="new-region" class="form-input" required
                    mx-get="server/server_types_options?location=${this.value}"
                    mx-trigger="change"
                    mx-target="#type-grid"
                    mx-swap="innerHTML">
                <option value="">— select region —</option>
                <?php foreach ($regions as $r): ?>
                <option value="<?= htmlspecialchars($r['id']) ?>"
                    <?= (post('region', true) === $r['id'] && $active_tab === 'new') ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['country']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Server plan</label>
            <div id="type-grid" class="type-grid">
                <?php if (!empty($server_types)): ?>
                <?php foreach ($server_types as $t): ?>
                <label class="type-card <?= (post('server_type', true) === $t['id'] && $active_tab === 'new') ? 'selected' : '' ?>">
                    <input type="radio" name="server_type" value="<?= htmlspecialchars($t['id']) ?>"
                           <?= (post('server_type', true) === $t['id'] && $active_tab === 'new') ? 'checked' : '' ?>>
                    <div class="type-name"><?= htmlspecialchars($t['name']) ?></div>
                    <div class="type-spec">
                        <?= (int)$t['vcpus'] ?> vCPU
                        &middot; <?= round($t['memory'] / 1024) ?> GB RAM
                        &middot; <?= (int)$t['disk'] ?> GB disk
                    </div>
                    <div class="type-price">&euro;<?= number_format((float)$t['price_monthly'], 2) ?>/mo</div>
                </label>
                <?php endforeach; ?>
                <?php else: ?>
                <p class="types-hint">Select a region to see available plans.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="steps">
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <button type="submit" class="btn-primary">
            <div class="spinner"></div>
            Provision Server &amp; Create Deployment &#10148;
        </button>
        <?= form_close() ?>
    </div>

    <!-- Tab: Import existing -->
    <?php if (!empty($importable)): ?>
    <div id="tab-import" class="<?= $active_tab !== 'import' ? 'is-hidden' : '' ?>">
        <?= form_open('customer-onboarding/submit_register_deployment', ['enctype' => 'multipart/form-data']) ?>
        <input type="hidden" name="provider" value="hetzner_import">
        <input type="hidden" name="environment_id" value="<?= (int) $env->id ?>">

        <?php include $_src_partial ?>

        <div class="form-group">
            <label class="form-label" for="imp-name">Server name</label>
            <input type="text" name="name" id="imp-name" class="form-input"
                placeholder="web-01"
                value="<?= htmlspecialchars($active_tab === 'import' ? (post('name', true) ?: '') : '') ?>"
                data-track-manual-edit="1"
                <?= $active_tab === 'import' ? 'autofocus' : '' ?>>
        </div>

        <div class="form-group">
            <label class="form-label">Select a server</label>
            <div class="import-grid">
                <?php foreach ($importable as $s): ?>
                <label class="import-card <?= (post('hetzner_id', true) === (string)$s['id'] && $active_tab === 'import') ? 'selected' : '' ?>">
                    <input type="radio" name="hetzner_id" value="<?= htmlspecialchars($s['id']) ?>"
                           <?= (post('hetzner_id', true) === (string)$s['id'] && $active_tab === 'import') ? 'checked' : '' ?>>
                    <div class="s-name"><?= htmlspecialchars($s['name']) ?></div>
                    <div class="s-meta"><?= htmlspecialchars($s['type']) ?> &middot; <?= htmlspecialchars($s['region']) ?></div>
                    <code><?= htmlspecialchars($s['ip']) ?></code>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="steps">
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>

        <button type="submit" class="btn-primary">
            <div class="spinner"></div>
            Import Server &amp; Create Deployment &#10148;
        </button>
        <?= form_close() ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <p class="onboarding-footer-note">
        Step 6 of 8 &mdash;
        <a href="<?= $back_url ?>">&#8592; Back</a>
    </p>
</div>

<script src="js/trongate-mx.js"></script>
<script src="customer-onboarding_module/js/onboarding.js"></script>
</body>
</html>
