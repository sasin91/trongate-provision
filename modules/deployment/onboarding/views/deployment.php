<style>
    .source-choice-row { display: flex; gap: 1rem; margin-bottom: .75rem; }
    .source-option     { display: flex; align-items: center; gap: .4rem; font-size: .9rem; cursor: pointer; }
    .is-hidden         { display: none; }
</style>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?php if (!empty($_SESSION['form_submission_errors'])): ?>
        <?php foreach ($_SESSION['form_submission_errors'] as $err): ?>
            <div class="error-message"><?= htmlspecialchars(implode(' ', (array) $err)) ?></div>
        <?php endforeach; ?>
        <?php unset($_SESSION['form_submission_errors']); ?>
    <?php endif; ?>

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

    <?= form_open('deployment-onboarding/deployment', ['enctype' => 'multipart/form-data']) ?>

    <?php
    $src_type = post('source_type', true) ?: 'git';
    ?>
    <div class="form-group source-group">
        <label class="form-label">App source</label>
        <div class="source-choice-row">
            <label class="source-option">
                <input type="radio" name="source_type" value="git"
                       id="src-git" <?= $src_type === 'git' ? 'checked' : '' ?>> Git repository
            </label>
            <label class="source-option">
                <input type="radio" name="source_type" value="zip"
                       id="src-zip" <?= $src_type === 'zip' ? 'checked' : '' ?>> Zip upload
            </label>
        </div>
    </div>

    <div class="src-git-fields <?= $src_type === 'zip' ? 'is-hidden' : '' ?>">
        <div class="form-group">
            <label class="form-label">Git repository URL</label>
            <input type="url" name="repo_url" class="form-input"
                   value="<?= htmlspecialchars(post('repo_url', true) ?: '') ?>"
                   placeholder="https://github.com/you/myapp.git">
        </div>
        <div class="form-group">
            <label class="form-label">Branch</label>
            <input type="text" name="branch" class="form-input"
                   value="<?= htmlspecialchars(post('branch', true) ?: 'main') ?>">
        </div>
    </div>

    <div class="src-zip-fields <?= $src_type !== 'zip' ? 'is-hidden' : '' ?>">
        <div class="form-group">
            <label class="form-label">App zip file</label>
            <input type="file" name="zip_file" accept=".zip" class="form-input">
            <span class="form-hint">Upload a .zip of your application root.</span>
        </div>
    </div>

    <?= wizard_step_dots(wizard_step_classes(5, 4)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Create Deployment &amp; Deploy &#10148;
    </button>
    <?= form_close() ?>

<script>
(function () {
    var gitBtn = document.getElementById('src-git');
    var zipBtn = document.getElementById('src-zip');
    var gitFields = document.querySelector('.src-git-fields');
    var zipFields = document.querySelector('.src-zip-fields');

    function toggle() {
        var isZip = zipBtn.checked;
        gitFields.classList.toggle('is-hidden', isZip);
        zipFields.classList.toggle('is-hidden', !isZip);
    }

    gitBtn.addEventListener('change', toggle);
    zipBtn.addEventListener('change', toggle);
})();
</script>
