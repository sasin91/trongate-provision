<?php
/**
 * View: scripts/delete_release_sql
 *
 * Renders a bash script that deletes selected SQL files from a staged release.
 *
 * @var object $deployment
 */

$d = $deployment;
$release_path = $d->release_path ?? "";
$sql_paths = is_array($d->sql_paths ?? null) ? $d->sql_paths : [];
$encoded_paths = array_map(static fn(string $path): string => base64_encode($path), $sql_paths);
$path_lines = implode("\n", $encoded_paths);
$sh = static fn(string $value): string => "'" . str_replace("'", "'\\''", $value) . "'";
?>
#!/bin/bash
set -euo pipefail

RELEASE_PATH=<?= $sh($release_path) ?>;
DELETED=0

if [ -z "$RELEASE_PATH" ] || [ ! -d "$RELEASE_PATH" ]; then
    echo "ERROR: release path does not exist: $RELEASE_PATH" >&2
    exit 1
fi

while IFS= read -r encoded_path; do
    [ -n "$encoded_path" ] || continue
    rel=$(printf '%s' "$encoded_path" | base64 -d)

    case "$rel" in
        /*|../*|*/../*|*/..|..) echo "ERROR: refusing unsafe path: $rel" >&2; exit 1 ;;
    esac
    case "$rel" in
        *.sql) ;;
        *) echo "ERROR: refusing non-SQL path: $rel" >&2; exit 1 ;;
    esac

    target="$RELEASE_PATH/$rel"
    if [ -f "$target" ]; then
        rm -f -- "$target"
        DELETED=$((DELETED + 1))
    fi
done <<'SQL_PATHS'
<?= $path_lines ?>
SQL_PATHS

echo "DELETED_SQL_COUNT: $DELETED"
