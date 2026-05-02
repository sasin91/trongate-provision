<?php
/**
 * Shared environment setup fields for standard creation and onboarding.
 *
 * Optional vars:
 *   @var string $control_class
 *   @var string $hint_color
 *   @var string $text_color
 */

$control_class = $control_class ?? 'form-control';
$hint_color = $hint_color ?? '#94a3b8';
$text_color = $text_color ?? '#0f172a';
?>
<div class="form-group">
    <label class="form-label">Services</label>
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem;margin-top:.35rem">
        <label style="display:flex;align-items:center;gap:.45rem;font-size:.875rem;cursor:pointer;padding:.45rem .6rem;border:1px solid #e5e7eb;border-radius:.375rem;user-select:none">
            <input type="checkbox" name="services[]" value="apache2" checked style="margin:0">
            Apache2 <span style="font-size:.72rem;color:<?= htmlspecialchars($hint_color) ?>">(HTTP :80)</span>
        </label>
        <label style="display:flex;align-items:center;gap:.45rem;font-size:.875rem;cursor:pointer;padding:.45rem .6rem;border:1px solid #e5e7eb;border-radius:.375rem;user-select:none">
            <input type="checkbox" name="services[]" value="mariadb" checked style="margin:0">
            MariaDB <span style="font-size:.72rem;color:<?= htmlspecialchars($hint_color) ?>">(:3306)</span>
        </label>
        <label style="display:flex;align-items:center;gap:.45rem;font-size:.875rem;cursor:pointer;padding:.45rem .6rem;border:1px solid #e5e7eb;border-radius:.375rem;user-select:none">
            <input type="checkbox" name="services[]" value="redis" style="margin:0">
            Redis <span style="font-size:.72rem;color:<?= htmlspecialchars($hint_color) ?>">(:6379)</span>
        </label>
        <label style="display:flex;align-items:center;gap:.45rem;font-size:.875rem;cursor:pointer;padding:.45rem .6rem;border:1px solid #e5e7eb;border-radius:.375rem;user-select:none">
            <input type="checkbox" name="services[]" value="postgresql" style="margin:0">
            PostgreSQL <span style="font-size:.72rem;color:<?= htmlspecialchars($hint_color) ?>">(:5432)</span>
        </label>
    </div>
    <span class="form-hint">Selected services will be tracked for health checks after provisioning.</span>
</div>

<details style="margin-bottom:1.5rem" open>
    <summary style="font-size:.875rem;font-weight:600;cursor:pointer;color:<?= htmlspecialchars($text_color) ?>;padding:.375rem 0">
        Config file patches <span style="font-weight:400;color:<?= htmlspecialchars($hint_color) ?>">(optional)</span>
    </summary>
    <div style="margin-top:.875rem;display:flex;flex-direction:column;gap:.75rem">
        <p style="font-size:.78rem;color:<?= htmlspecialchars($hint_color) ?>;margin:0">
            Values entered here will be written into <code>config/config.php</code> and <code>config/site_owner.php</code>
            on the server during deployment. Leave blank to skip.
        </p>
        <div class="form-row">
            <div>
                <label class="form-label" style="font-size:.8rem">ENV <span style="color:<?= htmlspecialchars($hint_color) ?>">(config.php)</span></label>
                <select name="cfg_env" class="<?= htmlspecialchars($control_class) ?>" style="font-size:.85rem">
                    <option value="dev" <?= post('cfg_env') === 'dev' ? 'selected' : '' ?>>development</option>
                    <option value="prod" <?= post('cfg_env') === 'prod' ? 'selected' : '' ?>>production</option>
                    <option value="staging" <?= post('cfg_env') === 'staging' ? 'selected' : '' ?>>staging</option>
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:.8rem">WEBSITE_NAME <span style="color:<?= htmlspecialchars($hint_color) ?>">(site_owner)</span></label>
                <input type="text" name="cfg_website_name" class="<?= htmlspecialchars($control_class) ?>" style="font-size:.85rem"
                       placeholder="My App"
                       value="<?= htmlspecialchars(post('cfg_website_name') ?: '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label" style="font-size:.8rem">OUR_NAME</label>
                <input type="text" name="cfg_our_name" class="<?= htmlspecialchars($control_class) ?>" style="font-size:.85rem"
                       placeholder="Company Name"
                       value="<?= htmlspecialchars(post('cfg_our_name') ?: '') ?>">
            </div>
            <div>
                <label class="form-label" style="font-size:.8rem">OUR_EMAIL_ADDRESS</label>
                <input type="email" name="cfg_our_email" class="<?= htmlspecialchars($control_class) ?>" style="font-size:.85rem"
                       placeholder="hello@example.com"
                       value="<?= htmlspecialchars(post('cfg_our_email') ?: '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label class="form-label" style="font-size:.8rem">OUR_TELNUM</label>
                <input type="text" name="cfg_our_telnum" class="<?= htmlspecialchars($control_class) ?>" style="font-size:.85rem"
                       placeholder="+1 555 000 0000"
                       value="<?= htmlspecialchars(post('cfg_our_telnum') ?: '') ?>">
            </div>
            <div>
                <label class="form-label" style="font-size:.8rem">OUR_ADDRESS</label>
                <input type="text" name="cfg_our_address" class="<?= htmlspecialchars($control_class) ?>" style="font-size:.85rem"
                       placeholder="123 Main St"
                       value="<?= htmlspecialchars(post('cfg_our_address') ?: '') ?>">
            </div>
        </div>
    </div>
</details>
