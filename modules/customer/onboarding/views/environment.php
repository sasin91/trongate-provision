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

    <?php
    $control_class = 'form-input';
    $hint_color = '#9ca3af';
    $text_color = 'var(--text-main)';
    include __DIR__ . '/../../environment/views/_setup_fields.php';
    ?>

    <?= wizard_step_dots(wizard_step_classes(8, 2)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner" style="display:none"></div>
        Create &amp; Continue
    </button>
    <?= form_close() ?>
