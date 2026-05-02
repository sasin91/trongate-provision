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
$sh = static fn(string $value): string => "'" . str_replace("'", "'\\''", $value) . "'";
$array_lines = implode("\n", array_map(static fn(string $path): string => "    " . $sh($path), $encoded_paths));
if ($array_lines !== "") {
    $array_lines .= "\n";
}
?>
#!/bin/bash
set -euo pipefail

RELEASE_PATH=<?= $sh($release_path) ?>;
DELETED=0
SQL_PATHS=(
<?= $array_lines ?>)

if [ -z "$RELEASE_PATH" ] || [ ! -d "$RELEASE_PATH" ]; then
    echo "ERROR: release path does not exist: $RELEASE_PATH" >&2
    exit 1
fi

for encoded_path in "${SQL_PATHS[@]}"; do
    [ -n "$encoded_path" ] || continue
    if ! rel=$(printf '%s' "$encoded_path" | base64 -d); then
        echo "ERROR: failed to decode SQL path." >&2
        exit 1
    fi

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
done

echo "DELETED_SQL_COUNT: $DELETED"
