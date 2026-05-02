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
// Provider-fetched zip: SCP'd by stream(), treat as zip on remote.
if ($source === "git" && !empty($d->zip_path)) {
  $source = "zip";
}
$repo = $d->repo_url ?? "";
$branch = $d->branch ?? "main";
$zip_url = ""; // zip lives in /tmp, transferred via SCP — not a public URL
$webroot = $d->web_root ?: "/var/www/html";
$domain = $d->domain ?: "";
$db = $d->db_name ?: "";
$date = date("Y-m-d H:i:s");
$server = $d->server_name . " (" . $d->ip_address . ")";
$env_vars = $env_vars ?? [];

$source_note =
  $source === "zip"
    ? "# App source: zip (transferred via SCP)"
    : "# Repository: {$repo} [{$branch}]";
$release_suffix = date('YmdHis');
$release_path = "/var/www/releases/deployment_" . (int) $d->id . "_" . $release_suffix;
?>
#!/bin/bash
# ────────────────────────────────────────────────────────────────
# Provision — Deployment Script
# Server    : <?= $server ?>

<?= $source_note ?>

# Release   : <?= $release_path ?>
# Web root  : <?= $webroot ?> (updated on Promote)

# Generated : <?= $date ?>

# ────────────────────────────────────────────────────────────────
# Run as root on your server after LAMP setup is complete.
# ────────────────────────────────────────────────────────────────

set -euo pipefail
umask 022

run_sudo() {
    if [ "$(id -u)" -eq 0 ]; then
        "$@"
        return
    fi
    sudo -n "$@"
}

if [ "$(id -u)" -ne 0 ]; then
    if ! command -v sudo >/dev/null 2>&1; then
        echo "ERROR: sudo is required when deploying as $(whoami)." >&2
        exit 1
    fi
    if ! sudo -n -l >/dev/null 2>&1; then
        echo "ERROR: passwordless sudo is required for $(whoami). Re-run provisioning as root or add the Provision sudoers rules." >&2
        exit 1
    fi
fi

# ── Environment Variables ─────────────────────────────────────────
<?php include __DIR__ . "/_env_vars.php"; ?>


WEB_ROOT="<?= $webroot ?>"
RELEASE_PATH="<?= $release_path ?>"
DB_NAME="<?= $db ?>"

if [ -e "$RELEASE_PATH" ]; then
    echo "ERROR: release path already exists: $RELEASE_PATH" >&2
    exit 1
fi
RELEASE_PARENT=$(dirname "$RELEASE_PATH")
run_sudo mkdir -p "$RELEASE_PARENT"
run_sudo install -d -m 0755 -o "$(id -un)" -g "$(id -gn)" "$RELEASE_PATH"

<?php if ($source === "zip") {
  include __DIR__ . "/_zip_fetch.php";
} else {
  include __DIR__ . "/_git_clone.php";
} ?>


<?php if ($domain):
  include __DIR__ . "/_vhost.php";
else:
   ?>
# (no domain configured)
<?php
endif; ?>


<?php if ($domain): ?>
if [ -f "$RELEASE_PATH/config/config.php" ]; then
    sed -i "s|define('BASE_URL', '[^']*');|define('BASE_URL', 'https://<?= $domain ?>/');|" "$RELEASE_PATH/config/config.php"
    echo "==> BASE_URL patched to https://<?= $domain ?>/"
fi
<?php else: ?>
# (no domain configured — BASE_URL not patched)
<?php endif; ?>


<?php include __DIR__ . "/_config_patch.php"; ?>

echo "==> Setting deployed file group to www-data..."
run_sudo chgrp -R www-data "$RELEASE_PATH"

DEPLOYED_SHA=$(git -C "$RELEASE_PATH" rev-parse HEAD 2>/dev/null || echo "n/a")
echo ""
echo "Release staged!"
echo "  Release path: $RELEASE_PATH"
echo "  Web root    : $WEB_ROOT"
echo "  SHA         : $DEPLOYED_SHA"
echo "RELEASE_PATH: $RELEASE_PATH"
echo "SHA: $DEPLOYED_SHA"
echo "Update the database manually, then promote this release in Provision."
