<?php
/**
 * Wizard layout — close half.
 *
 * Set these vars before including:
 *   int    $wizard_step_num    — current step (e.g. 1)
 *   int    $wizard_step_total  — total steps (e.g. 8)
 *   string $wizard_back_url   — href for back link
 *   string $wizard_back_text  — link label (e.g. '← Back', 'Sign out')
 *   string $wizard_js         — relative URL to JS file
 */
?>
    <p class="onboarding-footer-note">
        Step <?= (int) $wizard_step_num ?> of <?= (int) $wizard_step_total ?>
        &mdash; <a href="<?= htmlspecialchars($wizard_back_url) ?>"><?= htmlspecialchars($wizard_back_text) ?></a>
    </p>
</div>
<script src="<?= htmlspecialchars($wizard_js) ?>"></script>
</body>
</html>
