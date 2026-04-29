<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH Key — Provision Setup</title>
    <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
</head>
<body>

<div class="onboarding-card">
    <div class="onboarding-header">
        <h1>&#128273; Add Your SSH Key</h1>
        <p>Your public key will be embedded into every LAMP setup script, so you can SSH into your servers the moment they're provisioned.</p>
    </div>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?= form_open('customer-onboarding/submit_ssh_key') ?>

    <div class="form-group">
        <label class="form-label">SSH Public Key</label>
        <textarea name="public_key" class="form-input ssh-key-input" rows="5"
            placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI… user@host"
            required><?= htmlspecialchars(post('public_key') ?: '') ?></textarea>
        <small class="key-help">
            Run <code class="inline-code">cat ~/.ssh/id_ed25519.pub</code>
            to get it. No key yet?
            <code class="inline-code">ssh-keygen -t ed25519</code>
        </small>
    </div>

    <div class="steps">
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
        <div class="step-dot"></div>
        <div class="step-dot"></div>
        <div class="step-dot"></div>
    </div>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Save Key &amp; Continue
    </button>
    <?= form_close() ?>

    <p class="onboarding-footer-note">
        Step 1 of 5 &mdash; <a href="customer/logout">Sign out</a>
    </p>
</div>

<script src="customer-onboarding_module/js/onboarding.js"></script>
</body>
</html>
