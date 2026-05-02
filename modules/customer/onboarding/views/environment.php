<style>
    .form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .form-hint  { font-size: 0.78rem; color: #9ca3af; margin-top: 0.25rem; display: block; }
    .svc-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; margin-top: .35rem; }
    .svc-label  { display: flex; align-items: center; gap: .45rem; font-size: .875rem;
                  cursor: pointer; padding: .45rem .6rem; border: 1px solid #e5e7eb;
                  border-radius: .375rem; user-select: none; }
    .svc-label input { margin: 0; }
    .svc-label:has(input:checked) { border-color: #6366f1; background: #f5f3ff; }
</style>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?= form_open('customer-onboarding/environment') ?>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="name">Environment Name</label>
            <input type="text" name="name" id="name" class="form-input"
                placeholder="e.g. Production"
                value="<?= htmlspecialchars(post('name') ?: '') ?>"
                required autofocus>
        </div>
        <div class="form-group">
            <label class="form-label" for="php_version">PHP Version</label>
            <select name="php_version" id="php_version" class="form-select" required>
                <?php foreach ($php_versions as $v): ?>
                    <option value="<?= $v ?>" <?= (post('php_version') ?: '8.4') === $v ? 'selected' : '' ?>>
                        PHP <?= $v ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="domain">Domain <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
        <input type="text" name="domain" id="domain" class="form-input"
            placeholder="example.com"
            value="<?= htmlspecialchars(post('domain') ?: '') ?>">
    </div>

    <div class="form-group">
        <label class="form-label">Services</label>
        <div class="svc-grid">
            <label class="svc-label">
                <input type="checkbox" name="services[]" value="apache2" checked>
                Apache2 <span style="font-size:.72rem;color:#9ca3af">(HTTP :80)</span>
            </label>
            <label class="svc-label">
                <input type="checkbox" name="services[]" value="mariadb" checked>
                MariaDB <span style="font-size:.72rem;color:#9ca3af">(:3306)</span>
            </label>
            <label class="svc-label">
                <input type="checkbox" name="services[]" value="redis">
                Redis <span style="font-size:.72rem;color:#9ca3af">(:6379)</span>
            </label>
            <label class="svc-label">
                <input type="checkbox" name="services[]" value="postgresql">
                PostgreSQL <span style="font-size:.72rem;color:#9ca3af">(:5432)</span>
            </label>
        </div>
        <span class="form-hint">Selected services will be tracked for health checks after provisioning.</span>
    </div>

    <details style="margin-bottom:1.5rem" open>
        <summary style="font-size:.875rem;font-weight:500;cursor:pointer;color:var(--text-main);padding:.375rem 0">
            Config file patches <span style="font-weight:400;color:#9ca3af">(optional)</span>
        </summary>
        <div style="margin-top:.875rem;display:flex;flex-direction:column;gap:.75rem">
            <p style="font-size:.78rem;color:#9ca3af;margin:0">
                Values entered here will be written into <code>config/config.php</code> and <code>config/site_owner.php</code>
                on the server during deployment. Leave blank to skip.
            </p>
            <div class="form-row">
                <div>
                    <label class="form-label" style="font-size:.8rem">ENV <span style="color:#9ca3af">(config.php)</span></label>
                    <select name="cfg_env" class="form-select" style="font-size:.85rem">
                        <option value="dev" <?= post('cfg_env') === 'dev' ? 'selected' : '' ?>>development</option>
                        <option value="prod" <?= post('cfg_env') === 'prod' ? 'selected' : '' ?>>production</option>
                        <option value="staging" <?= post('cfg_env') === 'staging' ? 'selected' : '' ?>>staging</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size:.8rem">WEBSITE_NAME <span style="color:#9ca3af">(site_owner)</span></label>
                    <input type="text" name="cfg_website_name" class="form-input" style="font-size:.85rem"
                        placeholder="My App"
                        value="<?= htmlspecialchars(post('cfg_website_name') ?: '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label class="form-label" style="font-size:.8rem">OUR_NAME</label>
                    <input type="text" name="cfg_our_name" class="form-input" style="font-size:.85rem"
                        placeholder="Company Name"
                        value="<?= htmlspecialchars(post('cfg_our_name') ?: '') ?>">
                </div>
                <div>
                    <label class="form-label" style="font-size:.8rem">OUR_EMAIL_ADDRESS</label>
                    <input type="email" name="cfg_our_email" class="form-input" style="font-size:.85rem"
                        placeholder="hello@example.com"
                        value="<?= htmlspecialchars(post('cfg_our_email') ?: '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label class="form-label" style="font-size:.8rem">OUR_TELNUM</label>
                    <input type="text" name="cfg_our_telnum" class="form-input" style="font-size:.85rem"
                        placeholder="+1 555 000 0000"
                        value="<?= htmlspecialchars(post('cfg_our_telnum') ?: '') ?>">
                </div>
                <div>
                    <label class="form-label" style="font-size:.8rem">OUR_ADDRESS</label>
                    <input type="text" name="cfg_our_address" class="form-input" style="font-size:.85rem"
                        placeholder="123 Main St"
                        value="<?= htmlspecialchars(post('cfg_our_address') ?: '') ?>">
                </div>
            </div>
        </div>
    </details>

    <?= wizard_step_dots(wizard_step_classes(8, 2)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner" style="display:none"></div>
        Create &amp; Continue
    </button>
    <?= form_close() ?>
