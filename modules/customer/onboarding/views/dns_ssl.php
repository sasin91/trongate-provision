<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS &amp; SSL — Provision Setup</title>
    <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
</head>
<body>

<div class="onboarding-card onboarding-card--large">
    <div class="onboarding-header">
        <h1>&#128274; DNS &amp; SSL</h1>
        <p>Point your domain at the provisioned server, then optionally run Let's Encrypt before deploying the app.</p>
    </div>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="error-message"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if ($domain === ''): ?>
        <div class="info-box">
            <strong>No domain configured.</strong><br>
            SSL can be enabled later after adding a domain to this environment.
        </div>
    <?php else: ?>
        <div class="summary-box">
            <div class="summary-row">
                <span>Domain</span>
                <strong><?= htmlspecialchars($domain) ?></strong>
            </div>
            <div class="summary-row">
                <span>Server IP</span>
                <strong><code><?= htmlspecialchars($server->ip_address) ?></code></strong>
            </div>
        </div>

        <div class="dns-record">
            <div class="dns-record-row">
                <span>Type</span>
                <strong>A</strong>
            </div>
            <div class="dns-record-row">
                <span>Name</span>
                <strong><?= htmlspecialchars($domain) ?></strong>
            </div>
            <div class="dns-record-row">
                <span>Value</span>
                <strong><?= htmlspecialchars($server->ip_address) ?></strong>
            </div>
        </div>

        <div class="guidance">
            <h4>Before running SSL</h4>
            <ol>
                <li>Create or update the A record above at your DNS provider.</li>
                <li>Wait until the record resolves to <code><?= htmlspecialchars($server->ip_address) ?></code>.</li>
                <li>Leave ports 80 and 443 open so Let's Encrypt can validate the domain.</li>
            </ol>
        </div>

        <?php if (!$can_enable_ssl): ?>
            <div class="error-message"><?= htmlspecialchars($ssl_error) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="steps steps--compact">
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot completed"></div>
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
    </div>

    <?php if ($domain !== ''): ?>
        <?= form_open('customer-onboarding/submit_dns_ssl') ?>
        <input type="hidden" name="action" value="enable_ssl">
        <button type="submit" class="btn-primary" <?= $can_enable_ssl ? '' : 'disabled' ?>>
            <div class="spinner"></div>
            Enable SSL &amp; Continue
        </button>
        <?= form_close() ?>
    <?php endif; ?>

    <?= form_open('customer-onboarding/submit_dns_ssl', ['class' => 'secondary-action-form']) ?>
    <input type="hidden" name="action" value="skip">
    <button type="submit" class="btn-secondary-onboarding">
        <?= $domain === '' ? 'Continue to Deploy' : 'Skip SSL for Now' ?> &#10148;
    </button>
    <?= form_close() ?>

    <p class="onboarding-footer-note">
        Step 8 of 9 &mdash;
        <a href="customer-onboarding/provision_server">&#8592; Back</a>
    </p>
</div>

<script src="customer-onboarding_module/js/onboarding.js"></script>
</body>
</html>
