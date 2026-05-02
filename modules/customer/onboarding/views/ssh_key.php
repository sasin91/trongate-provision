
    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?= form_open('customer-onboarding/ssh_key') ?>

    <div class="form-group">
        <label class="form-label">SSH Public Key</label>
        <textarea name="public_key" class="form-input ssh-key-input" rows="5"
            data-ssh-public-key
            spellcheck="false"
            autocapitalize="off"
            autocomplete="off"
            placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI… user@host"
            required><?= htmlspecialchars(post('public_key') ?: '') ?></textarea>
        <small class="key-validation" data-ssh-key-feedback></small>
        <small class="key-help">
            Run <code class="inline-code" data-ssh-key-copy-command>cat ~/.ssh/id_ed25519.pub | pbcopy</code>
            to copy it. No key yet?
            <code class="inline-code">ssh-keygen -t ed25519</code>
        </small>
    </div>

    <?= wizard_step_dots(wizard_step_classes(8, 1)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Save Key &amp; Continue
    </button>
    <?= form_close() ?>
