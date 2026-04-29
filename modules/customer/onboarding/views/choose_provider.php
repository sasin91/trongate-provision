<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Provider — Provision Setup</title>
    <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
</head>
<body>

<div class="onboarding-card onboarding-card--standard">
    <div class="onboarding-header">
        <h1>How will you provision servers?</h1>
        <p>Choose how you want to add your first server. You can use both methods later from the dashboard.</p>
    </div>

    <?= form_open('customer-onboarding/submit_choose_provider') ?>

    <div class="provider-grid">

        <label class="provider-card" id="card-manual">
            <input type="radio" name="provider" value="manual" required>
            <span class="provider-icon">&#9646;</span>
            <span class="badge badge-easy">Any server</span>
            <h3>Manual</h3>
            <p>I have a VPS or bare-metal server. I'll enter its IP address and run the setup script myself.</p>
        </label>

        <label class="provider-card" id="card-hetzner">
            <input type="radio" name="provider" value="hetzner" required>
            <span class="provider-icon">&#9729;</span>
            <span class="badge badge-instant">Instant</span>
            <h3>Hetzner Cloud</h3>
            <p>Provision a new cloud server directly from Provision using your Hetzner API token.</p>
        </label>

    </div>

    <div class="steps">
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
    </div>

    <button type="submit" class="btn-primary" id="continue-btn" disabled>
        <div class="spinner"></div>
        Continue
    </button>
    <?= form_close() ?>

    <p class="onboarding-footer-note">
        Step 4 of 5 &mdash; <a href="customer-onboarding/environment">&#8592; Back</a>
    </p>
</div>

<script src="customer-onboarding_module/js/onboarding.js"></script>
</body>
</html>
