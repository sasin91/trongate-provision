<?php
$active_tab  = post('provider', true) === 'hetzner'        ? 'hetzner'
             : (post('provider', true) === 'hetzner_import' ? 'hetzner_import'
             : 'manual');
?>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="error-message"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="info-box">
        <strong>Environment:</strong> <?= htmlspecialchars($env->name) ?>
        &middot; PHP <?= htmlspecialchars($env->php_version) ?>
    </div>

    <div class="tab-nav">
        <button type="button" class="tab-btn <?= $active_tab === 'manual' ? 'active' : '' ?>" data-register-tab="manual">&#9646; Manual</button>
        <?php if ($has_hetzner): ?>
        <button type="button" class="tab-btn <?= in_array($active_tab, ['hetzner', 'hetzner_import']) ? 'active' : '' ?>" data-register-tab="hetzner">&#9729; Hetzner</button>
        <?php endif; ?>
    </div>

    <!-- Manual tab -->
    <div id="tab-manual" class="<?= $active_tab !== 'manual' ? 'is-hidden' : '' ?>">
        <?= form_open('deployment-onboarding/server') ?>
        <input type="hidden" name="provider" value="manual">

        <div class="form-group">
            <label class="form-label" for="name-manual">Server Name</label>
            <input type="text" name="name" id="name-manual" class="form-input"
                placeholder="e.g. web-01"
                value="<?= htmlspecialchars($active_tab === 'manual' ? (post('name', true) ?: '') : '') ?>"
                <?= $active_tab === 'manual' ? 'autofocus' : '' ?>>
        </div>

        <div class="form-group">
            <label class="form-label" for="ip_address">IP Address</label>
            <input type="text" name="ip_address" id="ip_address" class="form-input"
                placeholder="203.0.113.42"
                value="<?= htmlspecialchars($active_tab === 'manual' ? (post('ip_address', true) ?: '') : '') ?>"
                required>
            <span class="form-hint">The IPv4 address of your VPS or bare-metal server.</span>
        </div>

        <?= wizard_step_dots(wizard_step_classes(5, 2)) ?>

        <button type="submit" class="btn-primary">
            <div class="spinner"></div>
            Add Server &amp; Continue &#10148;
        </button>
        <?= form_close() ?>
    </div>

    <?php if ($has_hetzner): ?>
    <!-- Hetzner tab -->
    <div id="tab-hetzner" class="<?= !in_array($active_tab, ['hetzner', 'hetzner_import']) ? 'is-hidden' : '' ?>">
        <div class="tab-nav" style="margin-bottom:1rem">
            <button type="button" class="tab-btn <?= $active_tab === 'hetzner' ? 'active' : '' ?>" data-register-tab="hetzner-new">&#9729; New Server</button>
            <?php if (!empty($hetzner_servers)): ?>
            <button type="button" class="tab-btn <?= $active_tab === 'hetzner_import' ? 'active' : '' ?>" data-register-tab="hetzner-import">&#8645; Import Existing</button>
            <?php endif; ?>
        </div>

        <!-- Hetzner: Create new -->
        <div id="tab-hetzner-new" class="<?= $active_tab === 'hetzner_import' ? 'is-hidden' : '' ?>">
            <?= form_open('deployment-onboarding/server') ?>
            <input type="hidden" name="provider" value="hetzner">

            <div class="form-group">
                <label class="form-label" for="new-name">Server name</label>
                <input type="text" name="name" id="new-name" class="form-input"
                    placeholder="web-01"
                    value="<?= htmlspecialchars($active_tab === 'hetzner' ? (post('name', true) ?: '') : '') ?>"
                    <?= $active_tab === 'hetzner' ? 'autofocus' : '' ?>>
            </div>

            <div class="form-group">
                <label class="form-label" for="new-region">Region</label>
                <select name="region" id="new-region" class="form-input" required
                        mx-get="server/server_types_options?location=${this.value}"
                        mx-trigger="change"
                        mx-target="#type-grid"
                        mx-swap="innerHTML">
                    <option value="">— select region —</option>
                    <?php foreach ($hetzner_regions as $r): ?>
                    <option value="<?= htmlspecialchars($r['id']) ?>"
                        <?= ($active_tab === 'hetzner' && post('region', true) === $r['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['country']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Server plan</label>
                <div id="type-grid" class="type-grid">
                    <?php if (!empty($hetzner_types)): ?>
                    <?php foreach ($hetzner_types as $t): ?>
                    <label class="type-card <?= ($active_tab === 'hetzner' && post('server_type', true) === $t['id']) ? 'selected' : '' ?>">
                        <input type="radio" name="server_type" value="<?= htmlspecialchars($t['id']) ?>"
                               <?= ($active_tab === 'hetzner' && post('server_type', true) === $t['id']) ? 'checked' : '' ?>>
                        <div class="type-name"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="type-spec">
                            <?= (int) $t['vcpus'] ?> vCPU
                            &middot; <?= round($t['memory'] / 1024) ?> GB RAM
                            &middot; <?= (int) $t['disk'] ?> GB disk
                        </div>
                        <div class="type-price">&euro;<?= number_format((float) $t['price_monthly'], 2) ?>/mo</div>
                    </label>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <p class="types-hint">Select a region to see available plans.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?= wizard_step_dots(wizard_step_classes(5, 2)) ?>

            <button type="submit" class="btn-primary">
                <div class="spinner"></div>
                Create Server &amp; Continue &#10148;
            </button>
            <?= form_close() ?>
        </div>

        <?php if (!empty($hetzner_servers)): ?>
        <!-- Hetzner: Import existing -->
        <div id="tab-hetzner-import" class="<?= $active_tab !== 'hetzner_import' ? 'is-hidden' : '' ?>">
            <?= form_open('deployment-onboarding/server') ?>
            <input type="hidden" name="provider" value="hetzner_import">

            <div class="form-group">
                <label class="form-label" for="imp-name">Server name</label>
                <input type="text" name="name" id="imp-name" class="form-input"
                    placeholder="web-01"
                    value="<?= htmlspecialchars($active_tab === 'hetzner_import' ? (post('name', true) ?: '') : '') ?>"
                    <?= $active_tab === 'hetzner_import' ? 'autofocus' : '' ?>>
            </div>

            <div class="form-group">
                <label class="form-label">Select a server</label>
                <div class="import-grid">
                    <?php foreach ($hetzner_servers as $s): ?>
                    <label class="import-card <?= ($active_tab === 'hetzner_import' && post('hetzner_id', true) === (string) $s['id']) ? 'selected' : '' ?>">
                        <input type="radio" name="hetzner_id" value="<?= htmlspecialchars($s['id']) ?>"
                               <?= ($active_tab === 'hetzner_import' && post('hetzner_id', true) === (string) $s['id']) ? 'checked' : '' ?>>
                        <div class="s-name"><?= htmlspecialchars($s['name']) ?></div>
                        <div class="s-meta"><?= htmlspecialchars($s['type']) ?> &middot; <?= htmlspecialchars($s['region']) ?></div>
                        <code><?= htmlspecialchars($s['ip']) ?></code>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?= wizard_step_dots(wizard_step_classes(5, 2)) ?>

            <button type="submit" class="btn-primary">
                <div class="spinner"></div>
                Import Server &amp; Continue &#10148;
            </button>
            <?= form_close() ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<script src="js/trongate-mx.js"></script>
