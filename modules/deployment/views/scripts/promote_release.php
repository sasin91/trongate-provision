<?php
/**
 * View: scripts/promote_release
 *
 * Renders a bash script that atomically points the live web root symlink at a
 * staged release directory.
 *
 * @var object $deployment
 */

$d = $deployment;
$webroot = $d->web_root ?: "/var/www/html";
$release_path = $d->release_path ?? "";
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
        echo "ERROR: sudo is required when promoting as $(whoami)." >&2
        exit 1
    fi
    if ! sudo -n -l >/dev/null 2>&1; then
        echo "ERROR: passwordless sudo is required for $(whoami). Re-run provisioning as root or add the Provision sudoers rules." >&2
        exit 1
    fi
fi

LIVE_WEB_ROOT=<?= $sh($webroot) ?>;
TARGET_RELEASE=<?= $sh($release_path) ?>;

if [ -z "$TARGET_RELEASE" ] || [ ! -d "$TARGET_RELEASE" ]; then
    echo "ERROR: release path does not exist: $TARGET_RELEASE" >&2
    exit 1
fi

WEB_PARENT=$(dirname "$LIVE_WEB_ROOT")
run_sudo mkdir -p "$WEB_PARENT"

PREVIOUS_RELEASE_PATH=""
if [ -L "$LIVE_WEB_ROOT" ]; then
    CURRENT_RELEASE=$(readlink -f "$LIVE_WEB_ROOT" || true)
    case "$CURRENT_RELEASE" in
        /var/www/releases/deployment_*)
            PREVIOUS_RELEASE_PATH="$CURRENT_RELEASE"
            ;;
    esac
elif [ -e "$LIVE_WEB_ROOT" ]; then
    echo "ERROR: $LIVE_WEB_ROOT exists but is not a symlink. Re-run server provisioning or move it aside once before promoting staged releases." >&2
    exit 1
fi

TMP_LINK="/tmp/provision_promote_<?= (int) $d->id ?>.$$"
rm -f "$TMP_LINK"
ln -s "$TARGET_RELEASE" "$TMP_LINK"
run_sudo mv -Tf "$TMP_LINK" "$LIVE_WEB_ROOT"

if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet apache2; then
    run_sudo systemctl reload apache2
fi

echo "Release promoted."
echo "WEB_ROOT: $LIVE_WEB_ROOT"
echo "RELEASE_PATH: $TARGET_RELEASE"
echo "PREVIOUS_RELEASE_PATH: $PREVIOUS_RELEASE_PATH"
