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
EXTRACT_DIR=$(mktemp -d /tmp/provision_extract_XXXXXX)

cleanup_zip_deploy() {
    rm -f "$ZIP_TMP"
    rm -rf "$EXTRACT_DIR"
}
trap cleanup_zip_deploy EXIT

echo "==> Getting application zip..."
if [ -n "${DEPLOY_ZIP:-}" ] && [ -f "$DEPLOY_ZIP" ]; then
    cp "$DEPLOY_ZIP" "$ZIP_TMP"
elif [ -n "$ZIP_URL" ]; then
    curl -fsSL "$ZIP_URL" -o "$ZIP_TMP"
else
    echo "ERROR: no zip source — set DEPLOY_ZIP or configure a zip URL." >&2
    exit 1
fi

if ! command -v unzip >/dev/null 2>&1; then
    echo "ERROR: unzip is not installed on the server." >&2
    exit 1
fi

ZIP_SIZE=$(wc -c < "$ZIP_TMP" | tr -d ' ')
echo "==> Zip ready (${ZIP_SIZE} bytes)."
echo "==> Extracting archive..."
timeout 300 unzip -oq "$ZIP_TMP" -x "*.git/*" -d "$EXTRACT_DIR"

# Windows-created zips often contain one wrapper directory; deploy its contents.
top_entries=()
while IFS= read -r -d '' entry; do
    top_entries+=("$entry")
done < <(find "$EXTRACT_DIR" -mindepth 1 -maxdepth 1 -print0)

if [ ${#top_entries[@]} -eq 1 ] && [ -d "${top_entries[0]}" ]; then
    echo "==> Flattening top-level folder $(basename "${top_entries[0]}")..."
    APP_DIR="${top_entries[0]}"
else
    APP_DIR="$EXTRACT_DIR"
fi

echo "==> Publishing files to $WEB_ROOT..."
mkdir -p "$WEB_ROOT"
find "$WEB_ROOT" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
cp -a "$APP_DIR"/. "$WEB_ROOT"/
echo "==> Zip extracted to $WEB_ROOT."
