<?php
include __DIR__ . '/server.php';
$sh = static fn(string $v): string => "'" . str_replace("'", "'\\''", $v) . "'";
?>
#!/bin/bash
set -eo pipefail
set +u
umask 022

run_sudo() {
    if [ "$(id -u)" -eq 0 ]; then "$@"; return; fi
    sudo -n "$@"
}

if [ "$(id -u)" -ne 0 ]; then
    if ! command -v sudo >/dev/null 2>&1; then
        echo "ERROR: sudo is required when demoting as $(whoami)." >&2; exit 1
    fi
    if ! sudo -n -l >/dev/null 2>&1; then
        echo "ERROR: passwordless sudo is required for $(whoami)." >&2; exit 1
    fi
fi

RELEASES_DIR=<?= $sh($releases_path) ?>
LIVE_WEB_ROOT=<?= $sh($webroot) ?>

# Find current live release
[ ! -L "$LIVE_WEB_ROOT" ] && { echo "ERROR: $LIVE_WEB_ROOT is not a symlink — nothing to roll back." >&2; exit 1; }
CURRENT=$(readlink -f "$LIVE_WEB_ROOT")
CURRENT_NAME=$(basename "$CURRENT")

# Previous = second-newest release (excluding current)
PREVIOUS=$(ls -1 "$RELEASES_DIR" 2>/dev/null | sort | grep -v "^${CURRENT_NAME}$" | tail -1)
[ -z "$PREVIOUS" ] && { echo "ERROR: no previous release to roll back to." >&2; exit 1; }
ROLLBACK_RELEASE="$RELEASES_DIR/$PREVIOUS"
[ ! -d "$ROLLBACK_RELEASE" ] && { echo "ERROR: previous release not found: $ROLLBACK_RELEASE" >&2; exit 1; }

SHARED_ENV_FILE="/var/www/shared/.env"
[ -f "$SHARED_ENV_FILE" ] && ln -sfn "$SHARED_ENV_FILE" "$ROLLBACK_RELEASE/.env" && echo "==> Linked rollback .env"

TMP_LINK=$(mktemp -u "$LIVE_WEB_ROOT.XXXXXX")
ln -s "$ROLLBACK_RELEASE" "$TMP_LINK"
run_sudo mv -Tf "$TMP_LINK" "$LIVE_WEB_ROOT"

command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet apache2 && run_sudo systemctl reload apache2

echo "Release demoted."
echo "WEB_ROOT: $LIVE_WEB_ROOT"
echo "RELEASE_PATH: $ROLLBACK_RELEASE"
