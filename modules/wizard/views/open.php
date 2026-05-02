<?php
/**
 * Wizard layout — open half.
 *
 * Set these vars before including:
 *   string $wizard_title       — <title> text
 *   string $wizard_css         — relative URL to primary CSS file
 *   string $wizard_heading     — <h1> HTML content (emoji safe)
 *   string $wizard_subheading  — <p> text (will be htmlspecialchars'd); omit or set '' to skip <p>
 *   string $wizard_card_class  — extra class(es) for .onboarding-card (default '')
 * Optional:
 *   string $wizard_css2        — second CSS link
 */
require_once APPPATH . 'modules/wizard/views/helpers.php';
$_wcc = trim($wizard_card_class ?? '');
?><!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($wizard_title ?? '') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($wizard_css) ?>">
    <?php if (!empty($wizard_css2)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($wizard_css2) ?>">
    <?php endif; ?>
</head>
<body>
<div class="onboarding-card<?= $_wcc ? ' ' . $_wcc : '' ?>">
    <div class="onboarding-header">
        <h1><?= $wizard_heading ?></h1>
        <?php if (($wizard_subheading ?? '') !== ''): ?>
        <p><?= htmlspecialchars($wizard_subheading) ?></p>
        <?php endif; ?>
    </div>
