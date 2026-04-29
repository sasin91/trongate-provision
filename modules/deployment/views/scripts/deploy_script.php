<?php
/**
 * View: scripts/deploy_script
 *
 * Renders the bash deployment script for a given deployment row.
 * Sub-blocks live in their own sibling views and are pulled in with
 * `include __DIR__ . '/_xxx.php'` so they share this view's scope.
 *
 * @var object $deployment The deployment row joined with server / environment / script data.
 * @var array  $env_vars   Already-decrypted KEY => value map (empty when none).
 */

$d = $deployment;

$source = $d->source_type ?? "git";
$repo = $d->repo_url ?? "";
$branch = $d->branch ?? "main";
$zip_url = ""; // zip lives in /tmp, transferred via SCP — not a public URL
$webroot = $d->web_root ?: "/var/www/html";
$domain = $d->domain ?: "";
$db = $d->db_name ?: "";
$date = date("Y-m-d H:i:s");
$server = $d->server_name . " (" . $d->ip_address . ")";
$env_vars = $env_vars ?? [];

// Use custom script if assigned
if (!empty($d->script_id) && !empty($d->script_body)) {

  // Capture the env-vars partial as a string so it can be substituted into
  // the user's template via strtr(). A nested ob_start/ob_get_clean is fine
  // here — it's independent of any buffer Trongate::view() may have opened.
  ob_start();
  include __DIR__ . "/_env_vars.php";
  $env_vars_block = ob_get_clean();

  $tokens = [
    "{{PHP_VERSION}}" => $d->php_version ?? "",
    "{{REPO_URL}}" => $repo,
    "{{BRANCH}}" => $branch,
    "{{WEB_ROOT}}" => $webroot,
    "{{DOMAIN}}" => $domain,
    "{{DB_NAME}}" => $db,
    "{{SERVER_IP}}" => $d->ip_address ?? "",
    "{{SERVER_NAME}}" => $d->server_name ?? "",
    "{{ENV_NAME}}" => $d->env_name ?? "",
    "{{ENV_VARS}}" => $env_vars_block,
  ];
  ?>
#!/bin/bash
# ────────────────────────────────────────────────────────────────
# Custom script: <?= $d->script_name ?>

# Deployment #<?= $d->id ?> — <?= $server ?>

# Generated: <?= $date ?>

# ────────────────────────────────────────────────────────────────

<?= strtr($d->script_body, $tokens) ?>
<?php return;
}
$source_note =
  $source === "zip"
    ? "# App source: zip (transferred via SCP)"
    : "# Repository: {$repo} [{$branch}]";
$is_canary = (int) ($d->is_canary ?? 0) === 1;
?>
#!/bin/bash
# ────────────────────────────────────────────────────────────────
# Provision — Deployment Script
# Server    : <?= $server ?>

<?= $source_note ?>

# Web root  : <?= $webroot ?>

# Generated : <?= $date ?>

# ────────────────────────────────────────────────────────────────
# Run as root on your server after LAMP setup is complete.
# ────────────────────────────────────────────────────────────────

set -euo pipefail
umask 022

<?php if ($is_canary): ?>
# ── Canary: routing <?= (int) $d->canary_weight ?>% of traffic to this server ────────
<?php endif; ?>

# ── Environment Variables ─────────────────────────────────────────
<?php include __DIR__ . "/_env_vars.php"; ?>


WEB_ROOT="<?= $webroot ?>"
DB_NAME="<?= $db ?>"

<?php if ($source === "zip") {
  include __DIR__ . "/_zip_fetch.php";
} else {
  include __DIR__ . "/_git_clone.php";
} ?>


<?php if ($db): ?>
mysql -e "CREATE DATABASE IF NOT EXISTS \`<?= $db ?>\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
echo "==> Database '<?= $db ?>' ready."
<?php else: ?>
# (no database configured)
<?php endif; ?>


<?php if ($domain):
  include __DIR__ . "/_vhost.php";
else:
   ?>
# (no domain configured)
<?php
endif; ?>


<?php if ($domain): ?>
if [ -f "<?= $webroot ?>/config/config.php" ]; then
    sed -i "s|define('BASE_URL', '[^']*');|define('BASE_URL', 'https://<?= $domain ?>/');|" "<?= $webroot ?>/config/config.php"
    echo "==> BASE_URL patched to https://<?= $domain ?>/"
fi
<?php else: ?>
# (no domain configured — BASE_URL not patched)
<?php endif; ?>


<?php include __DIR__ . "/_config_patch.php"; ?>


echo "==> Restarting Apache..."
sudo systemctl restart apache2

DEPLOYED_SHA=$(git -C "$WEB_ROOT" rev-parse HEAD 2>/dev/null || echo "n/a")
echo ""
echo "Deployment complete!"
echo "  Web root: $WEB_ROOT"
echo "  SHA     : $DEPLOYED_SHA"
echo "  → Record this SHA in Provision when marking the deployment successful."
