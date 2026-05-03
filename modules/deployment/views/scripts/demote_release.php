<?php
/**
 * View: scripts/demote_release
 *
 * Renders a bash script that atomically points the live web root symlink back
 * at the previous release directory.
 *
 * @var object $deployment
 */

$d = $deployment;
$webroot = $d->web_root ?: "/var/www/html";
$previous_release_path = $d->previous_release_path ?? "";
$sh = static fn(string $value): string => "'" . str_replace("'", "'\\''", $value) . "'";
?>
#!/bin/bash
set -eo pipefail
set +u
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
        echo "ERROR: sudo is required when demoting as $(whoami)." >&2
        exit 1
    fi
    if ! sudo -n -l >/dev/null 2>&1; then
        echo "ERROR: passwordless sudo is required for $(whoami). Re-run provisioning as root or add the Provision sudoers rules." >&2
        exit 1
    fi
fi

LIVE_WEB_ROOT=<?= $sh($webroot) ?>;
ROLLBACK_RELEASE=<?= $sh($previous_release_path) ?>;

if [ -z "$ROLLBACK_RELEASE" ] || [ ! -d "$ROLLBACK_RELEASE" ]; then
    echo "ERROR: previous release path does not exist: $ROLLBACK_RELEASE" >&2
    exit 1
fi

SHARED_ENV_FILE="/var/www/shared/.env"
if [ -f "$SHARED_ENV_FILE" ]; then
    ln -sfn "$SHARED_ENV_FILE" "$ROLLBACK_RELEASE/.env"
    echo "==> Linked rollback .env to $SHARED_ENV_FILE"
fi

WEB_PARENT=$(dirname "$LIVE_WEB_ROOT")
run_sudo mkdir -p "$WEB_PARENT"

TMP_LINK="/tmp/provision_demote_<?= (int) $d->id ?>.$$"
rm -f "$TMP_LINK"
ln -s "$ROLLBACK_RELEASE" "$TMP_LINK"
run_sudo mv -Tf "$TMP_LINK" "$LIVE_WEB_ROOT"

if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet apache2; then
    run_sudo systemctl reload apache2
fi

echo "Release demoted."
echo "WEB_ROOT: $LIVE_WEB_ROOT"
echo "RELEASE_PATH: $ROLLBACK_RELEASE"
