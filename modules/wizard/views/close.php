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
 * Optional:
 *   string $wizard_js_extra   — additional JS file loaded before $wizard_js
 *   string $wizard_js_inline  — raw JS injected in a <script> tag (used instead of $wizard_js when set)
 */
?>
    <p class="onboarding-footer-note">
        Step <?= (int) $wizard_step_num ?> of <?= (int) $wizard_step_total ?>
        &mdash; <a href="<?= htmlspecialchars($wizard_back_url) ?>"><?= htmlspecialchars($wizard_back_text) ?></a>
    </p>
</div>
<?php if (!empty($wizard_js_extra)): ?>
<script src="<?= htmlspecialchars($wizard_js_extra) ?>"></script>
<?php endif; ?>
<?php if (!empty($wizard_js_inline)): ?>
<script><?= $wizard_js_inline ?></script>
<?php elseif (!empty($wizard_js)): ?>
<script src="<?= htmlspecialchars($wizard_js) ?>"></script>
<?php endif; ?>
</body>
</html>
