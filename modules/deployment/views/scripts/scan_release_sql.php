<?php
/**
 * View: scripts/scan_release_sql
 *
 * Renders a bash script that scans a staged release for SQL files and emits
 * machine-readable base64 lines for the wizard UI.
 *
 * @var object $deployment
 */

$d = $deployment;
$release_path = $d->release_path ?? "";
$sh = static fn(string $value): string => "'" . str_replace("'", "'\\''", $value) . "'";
?>
#!/bin/bash
set -euo pipefail

RELEASE_PATH=<?= $sh($release_path) ?>;

if [ -z "$RELEASE_PATH" ] || [ ! -d "$RELEASE_PATH" ]; then
    echo "ERROR: release path does not exist: $RELEASE_PATH" >&2
    exit 1
fi

find "$RELEASE_PATH" -type f -name '*.sql' -print0 | sort -z | while IFS= read -r -d '' file; do
    rel="${file#"$RELEASE_PATH"/}"
    rel64=$(printf '%s' "$rel" | base64 -w 0)
    body64=$(base64 -w 0 "$file")
    printf 'SQL_FILE\t%s\t%s\n' "$rel64" "$body64"
done
