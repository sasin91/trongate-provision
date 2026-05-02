
    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <div class="info-box">
        <strong>Environment:</strong> <?= htmlspecialchars($env->name) ?>
        &middot; PHP <?= htmlspecialchars($env->php_version) ?>
    </div>

    <?= form_open('customer-onboarding/server_manual') ?>

    <div class="form-group">
        <label class="form-label" for="name">Server Name</label>
        <input type="text" name="name" id="name" class="form-input"
            placeholder="e.g. web-01"
            value="<?= htmlspecialchars(post('name') ?: '') ?>"
            required autofocus>
    </div>

    <div class="form-group">
        <label class="form-label" for="ip_address">IP Address</label>
        <input type="text" name="ip_address" id="ip_address" class="form-input"
            placeholder="203.0.113.42"
            value="<?= htmlspecialchars(post('ip_address') ?: '') ?>"
            required>
        <span class="form-hint">The IPv4 address of your VPS or bare-metal server.</span>
    </div>

    <?= wizard_step_dots(wizard_step_classes(8, 4)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Add Server &amp; Continue &#10148;
    </button>
    <?= form_close() ?>
