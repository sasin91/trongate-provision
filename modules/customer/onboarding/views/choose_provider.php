<?php
$wizard_title      = 'Choose Provider — Provision Setup';
$wizard_css        = 'customer-onboarding_module/css/onboarding.css';
$wizard_heading    = 'How will you provision servers?';
$wizard_subheading = 'Choose how you want to add your first server. You can use both methods later from the dashboard.';
$wizard_card_class = 'onboarding-card--standard';
include APPPATH . 'modules/wizard/views/open.php';
?>

    <?= form_open('customer-onboarding/choose_provider') ?>

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

    <?= wizard_step_dots(wizard_step_classes(8, 3)) ?>

    <button type="submit" class="btn-primary" id="continue-btn" disabled>
        <div class="spinner"></div>
        Continue
    </button>
    <?= form_close() ?>

<?php
$wizard_step_num   = 3;
$wizard_step_total = 8;
$wizard_back_url   = 'customer-onboarding/environment';
$wizard_back_text  = '← Back';
$wizard_js         = 'customer-onboarding_module/js/onboarding.js';
include APPPATH . 'modules/wizard/views/close.php';
