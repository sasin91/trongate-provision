<?php
$wizard_title      = 'SSH Key — Provision Setup';
$wizard_css        = 'customer-onboarding_module/css/onboarding.css';
$wizard_heading    = '&#128273; Add Your SSH Key';
$wizard_subheading = "Your public key will be embedded into every LAMP setup script, so you can SSH into your servers the moment they're provisioned.";
$wizard_card_class = '';
include APPPATH . 'modules/wizard/views/open.php';
?>

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
            Run <code class="inline-code">cat ~/.ssh/id_ed25519.pub</code>
            to get it. No key yet?
            <code class="inline-code">ssh-keygen -t ed25519</code>
        </small>
    </div>

    <?= wizard_step_dots(wizard_step_classes(8, 1)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Save Key &amp; Continue
    </button>
    <?= form_close() ?>

<?php
$wizard_step_num   = 1;
$wizard_step_total = 8;
$wizard_back_url   = 'customer/logout';
$wizard_back_text  = 'Sign out';
$wizard_js         = 'customer-onboarding_module/js/onboarding.js';
include APPPATH . 'modules/wizard/views/close.php';
