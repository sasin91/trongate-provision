<?php $src_type = post('source_type', true) ?: 'git'; ?>
<div class="form-group source-group">
    <label class="form-label">App source</label>
    <div class="source-choice-row">
        <label class="source-option">
            <input type="radio" name="source_type" value="git"
                   <?= $src_type === 'git' ? 'checked' : '' ?>> Git repository
        </label>
        <label class="source-option">
            <input type="radio" name="source_type" value="zip"
                   <?= $src_type === 'zip' ? 'checked' : '' ?>> Zip upload
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
        <span class="field-help">Upload a .zip of your application root</span>
    </div>
</div>
