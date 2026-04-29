<?php
/**
 * View: scripts/_zip_fetch
 *
 * Emits the bash block that retrieves the application zip (either from a
 * pre-uploaded local file via $DEPLOY_ZIP or a public URL) and extracts it
 * into $WEB_ROOT, flattening a single top-level directory if needed.
 *
 * Included from `deploy_script.php` — inherits its scope.
 *
 * Required vars from parent scope:
 *   @var string $zip_url Optional zip URL (may be empty when source is SCP).
 */
?>
ZIP_URL="<?= $zip_url ?>"
ZIP_TMP=$(mktemp /tmp/provision_app_XXXXXX.zip)

echo "==> Getting application zip..."
if [ -n "${DEPLOY_ZIP:-}" ] && [ -f "$DEPLOY_ZIP" ]; then
    cp "$DEPLOY_ZIP" "$ZIP_TMP"
elif [ -n "$ZIP_URL" ]; then
    curl -fsSL "$ZIP_URL" -o "$ZIP_TMP"
else
    echo "ERROR: no zip source — set DEPLOY_ZIP or configure a zip URL." >&2
    exit 1
fi

echo "==> Extracting to $WEB_ROOT..."
mkdir -p "$WEB_ROOT"
unzip -o "$ZIP_TMP" -x "*.git/*" -d "$WEB_ROOT"
rm -f "$ZIP_TMP"

# If the zip extracted a single top-level folder, flatten it
shopt -s nullglob
dirs=("$WEB_ROOT"/*/); files=("$WEB_ROOT"/*)
if [ ${#dirs[@]} -eq 1 ] && [ ${#files[@]} -eq 1 ]; then
    tmp_dir="$(mktemp -d /tmp/provision_flat_XXXXXX)"
    mv "${dirs[0]}"* "$tmp_dir/" 2>/dev/null || true
    mv "${dirs[0]}".[!.]* "$tmp_dir/" 2>/dev/null || true
    rmdir "${dirs[0]}"
    mv "$tmp_dir"/* "$WEB_ROOT/" 2>/dev/null || true
    mv "$tmp_dir"/.[!.]* "$WEB_ROOT/" 2>/dev/null || true
    rmdir "$tmp_dir" 2>/dev/null || true
fi
shopt -u nullglob
