<?php
include __DIR__ . '/server.php';

$release = $releases_path . '/' . date('YmdHis');
$source  = !empty($zip_path) ? 'zip' : 'git';

$env_block    = (static function () use ($env_vars): string {
    ob_start(); include __DIR__ . '/_env_vars.php'; return ob_get_clean();
})();
$vhost_block  = $domain
    ? (static function () use ($domain, $webroot, $php_version, $release): string {
        ob_start(); include __DIR__ . '/_vhost.php'; return ob_get_clean();
    })()
    : '# (no domain configured — vhost skipped)';
$config_block = (static function () use ($db_name, $db_user, $db_pass, $env_vars): string {
    ob_start(); include __DIR__ . '/_config_patch.php'; return ob_get_clean();
})();
?>
#!/bin/bash
set -euo pipefail

<?= $env_block ?>

RELEASE_PATH="<?= $release ?>"
SOURCE_TYPE="<?= $source ?>"
REPO_URL="<?= $repo ?>"
BRANCH="<?= $branch ?>"
WEB_ROOT="<?= $webroot ?>"

run_sudo() { if [ "$(id -u)" = "0" ]; then "$@"; else sudo "$@"; fi; }

echo "==> Creating release directory: $RELEASE_PATH"
mkdir -p "$RELEASE_PATH"

if [ "$SOURCE_TYPE" = "zip" ]; then
    echo "==> Extracting zip"
    EXTRACT_DIR=$(mktemp -d)
    unzip -q "$DEPLOY_ZIP" -d "$EXTRACT_DIR"
    INNER=$(find "$EXTRACT_DIR" -mindepth 1 -maxdepth 1 -type d | head -1)
    cp -a "${INNER:-$EXTRACT_DIR}/." "$RELEASE_PATH/"
    rm -rf "$EXTRACT_DIR"
    echo "==> Zip extracted"
else
    echo "==> Cloning $REPO_URL ($BRANCH)"
    git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$RELEASE_PATH"
    echo "==> Clone complete"
fi

<?= $vhost_block ?>

<?= $config_block ?>

echo "==> Setting permissions"
chgrp -R www-data "$RELEASE_PATH" 2>/dev/null || true

DEPLOYED_SHA=$(git -C "$RELEASE_PATH" rev-parse HEAD 2>/dev/null || echo "")
echo "RELEASE_PATH: $RELEASE_PATH"
[ -n "$DEPLOYED_SHA" ] && echo "SHA: $DEPLOYED_SHA"
echo "==> Done."
