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
        echo "ERROR: sudo is required when promoting as $(whoami)." >&2; exit 1
    fi
    if ! sudo -n -l >/dev/null 2>&1; then
        echo "ERROR: passwordless sudo is required for $(whoami)." >&2; exit 1
    fi
fi

RELEASES_DIR=<?= $sh($releases_path) ?>
LIVE_WEB_ROOT=<?= $sh($webroot) ?>

# Newest release = lexicographically last (timestamps sort naturally)
TARGET=$(ls -1 "$RELEASES_DIR" 2>/dev/null | sort | tail -1)
[ -z "$TARGET" ] && { echo "ERROR: no releases found in $RELEASES_DIR" >&2; exit 1; }
TARGET_RELEASE="$RELEASES_DIR/$TARGET"
[ ! -d "$TARGET_RELEASE" ] && { echo "ERROR: release directory not found: $TARGET_RELEASE" >&2; exit 1; }

SHARED_ENV_FILE="/var/www/shared/.env"
[ -f "$SHARED_ENV_FILE" ] && ln -sfn "$SHARED_ENV_FILE" "$TARGET_RELEASE/.env" && echo "==> Linked release .env"

run_sudo mkdir -p "$(dirname "$LIVE_WEB_ROOT")"

PREVIOUS_RELEASE_PATH=""
if [ -L "$LIVE_WEB_ROOT" ]; then
    PREVIOUS_RELEASE_PATH=$(readlink -f "$LIVE_WEB_ROOT" || true)
elif [ -e "$LIVE_WEB_ROOT" ]; then
    echo "ERROR: $LIVE_WEB_ROOT exists but is not a symlink." >&2; exit 1
fi

TMP_LINK=$(mktemp -u "$LIVE_WEB_ROOT.XXXXXX")
ln -s "$TARGET_RELEASE" "$TMP_LINK"
run_sudo mv -Tf "$TMP_LINK" "$LIVE_WEB_ROOT"

command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet apache2 && run_sudo systemctl reload apache2

echo "Release promoted."
echo "WEB_ROOT: $LIVE_WEB_ROOT"
echo "RELEASE_PATH: $TARGET_RELEASE"
[ -n "$PREVIOUS_RELEASE_PATH" ] && echo "PREVIOUS_RELEASE_PATH: $PREVIOUS_RELEASE_PATH"
