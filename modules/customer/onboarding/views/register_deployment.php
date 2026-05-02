<?php
$wizard_title      = 'Create Deployment — Provision Setup';
$wizard_css        = 'customer-onboarding_module/css/onboarding.css';
$wizard_heading    = '&#9654; Create Deployment';
$wizard_subheading = 'Confirm your provisioned server and app source. Provision will run the deployment script next.';
$wizard_card_class = 'onboarding-card--standard';
include APPPATH . 'modules/wizard/views/open.php';

$_src_partial = __DIR__ . '/_source_fields.php';
?>

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

    <?= form_open('customer-onboarding/register_deployment', ['enctype' => 'multipart/form-data']) ?>

    <?php include $_src_partial ?>

    <?= wizard_step_dots(wizard_step_classes(8, 7)) ?>

    <button type="submit" class="btn-primary">
        <div class="spinner"></div>
        Create Deployment &amp; Deploy &#10148;
    </button>
    <?= form_close() ?>

<?php
$wizard_step_num   = 7;
$wizard_step_total = 8;
$wizard_back_url   = 'customer-onboarding/dns_ssl';
$wizard_back_text  = '← Back';
$wizard_js         = 'customer-onboarding_module/js/onboarding.js';
include APPPATH . 'modules/wizard/views/close.php';
