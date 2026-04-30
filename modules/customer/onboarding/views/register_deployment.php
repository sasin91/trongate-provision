<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Deployment — Provision Setup</title>
    <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
</head>
<body>

<?php $_src_partial = __DIR__ . '/_source_fields.php'; ?>

<div class="onboarding-card onboarding-card--standard">
    <div class="onboarding-header">
        <h1>&#9654; Create Deployment</h1>
        <p>Confirm your provisioned server and app source. Provision will run the deployment script next.</p>
    </div>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="success-message"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <div class="summary-box">
        <div class="summary-row">
            <span>Server</span>
            <strong><?= htmlspecialchars($server->name) ?> &mdash; <?= htmlspecialchars($server->ip_address) ?></strong>
        </div>
        <div class="summary-row">
            <span>Provider</span>
            <strong><?= htmlspecialchars(ucfirst((string) ($provider ?: 'manual'))) ?></strong>
        </div>
        <div class="summary-row">
            <span>Environment</span>
            <strong><?= htmlspecialchars($env->name) ?> &middot; PHP <?= htmlspecialchars($env->php_version) ?></strong>
        </div>
    </div>

    <?= form_open('customer-onboarding/submit_register_deployment', ['enctype' => 'multipart/form-data']) ?>

    <?php include $_src_partial ?>

    <div class="steps">
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
    </div>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Create Deployment &amp; Deploy &#10148;
    </button>
    <?= form_close() ?>

    <p class="onboarding-footer-note">
        Step 7 of 8 &mdash;
        <a href="customer-onboarding/dns_ssl">&#8592; Back</a>
    </p>
</div>

<script src="customer-onboarding_module/js/onboarding.js"></script>
</body>
</html>
