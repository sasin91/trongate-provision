<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Server — Provision Setup</title>
    <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
</head>
<body>

<div class="onboarding-card onboarding-card--standard">
    <div class="onboarding-header">
        <h1>&#9646; Your Server</h1>
        <p>Enter your server's details. Provision will generate a LAMP setup script you can run via SSH.</p>
    </div>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <div class="info-box">
        <strong>Environment:</strong> <?= htmlspecialchars($env->name) ?>
        &middot; PHP <?= htmlspecialchars($env->php_version) ?>
    </div>

    <?= form_open('customer-onboarding/submit_server_manual') ?>

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

    <div class="steps">
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
    </div>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Add Server &amp; Continue &#10148;
    </button>
    <?= form_close() ?>

    <p class="onboarding-footer-note">
        Step 5 of 6 &mdash;
        <a href="customer-onboarding/choose_provider">&#8592; Back</a>
    </p>
</div>

<script src="customer-onboarding_module/js/onboarding.js"></script>
</body>
</html>
