<?php
/**
 * Wizard layout template — standalone full-page wizard shell.
 *
 * $data keys consumed here (pass via $this->templates->wizard($data)):
 *   string $wizard_title       — <title> text
 *   string $wizard_css         — URL to primary CSS (relative to BASE_URL)
 *   string $wizard_heading     — <h1> content, output RAW — must be a static literal, never user data
 *   string $wizard_subheading  — <p> text, htmlspecialchars'd
 *   string $wizard_card_class  — extra class(es) for .onboarding-card (e.g. 'onboarding-card--standard')
 *   int    $wizard_step_num    — current step number
 *   int    $wizard_step_total  — total steps
 *   string $wizard_back_url   — href for back/cancel link
 *   string $wizard_back_text  — link label
 *   string $wizard_js         — JS file URL (relative to BASE_URL)
 *
 * Optional:
 *   string $wizard_css2        — second CSS link
 *
 * Inner content rendered via display($data) using $data['view_module'] + $data['view_file'].
 * The inner view can call wizard_step_dots() and wizard_step_classes() defined below.
 */
if (!function_exists('wizard_step_dots')) {
    function wizard_step_dots(array $classes, string $container_class = 'steps'): string {
        $html = '<div class="' . htmlspecialchars($container_class) . '">';
        foreach ($classes as $c) {
            $attr = $c ? ' ' . htmlspecialchars((string) $c) : '';
            $html .= '<div class="step-dot' . $attr . '"></div>';
        }
        return $html . '</div>';
    }
    function wizard_step_classes(int $total, int $current): array {
        $out = [];
        for ($i = 1; $i <= $total; $i++) {
            $out[] = $i < $current ? 'completed' : ($i === $current ? 'active' : '');
        }
        return $out;
    }
}
$_wcc = trim($wizard_card_class ?? '');
?><!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($wizard_title ?? '') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($wizard_css ?? '') ?>">
    <?php if (!empty($wizard_css2)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($wizard_css2) ?>">
    <?php endif; ?>
</head>
<body>
<div class="onboarding-card<?= $_wcc ? ' ' . $_wcc : '' ?>">
    <div class="onboarding-header">
        <h1><?= $wizard_heading ?? '' ?></h1>
        <p><?= htmlspecialchars($wizard_subheading ?? '') ?></p>
    </div>
    <?= display($data) ?>
    <p class="onboarding-footer-note">
        Step <?= (int) ($wizard_step_num ?? 1) ?> of <?= (int) ($wizard_step_total ?? 1) ?>
        &mdash; <a href="<?= htmlspecialchars($wizard_back_url ?? '') ?>"><?= htmlspecialchars($wizard_back_text ?? '') ?></a>
    </p>
</div>
<script src="<?= htmlspecialchars($wizard_js ?? '') ?>"></script>
</body>
</html>
