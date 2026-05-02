<?php
/**
 * View: scripts/_config_patch
 *
 * Emits the bash block that patches `config/*.php` files on the target
 * server (defines + database.php) using values from the deployment's
 * environment variables.
 *
 * Included from `deploy_script.php` — inherits its scope.
 *
 * Required vars from parent scope:
 *   @var string $release_path Staged release path on the target server.
 *   @var string $db       Fallback database name (when DB_NAME is not in $env_vars).
 *   @var array  $env_vars Already-decrypted KEY => value map (may be empty).
 */

$db_name = $env_vars["DB_NAME"] ?? $db;
$db_user = $env_vars["DB_USER"] ?? "";
$db_password = $env_vars["DB_PASSWORD"] ?? "";
?>
# ── Config file patches ───────────────────────────────────────────
_patch_define() {
    local file="$1" const="$2" val="$3"
    [ -z "$val" ] && return
    [ -f "$file" ] || return
    local esc="${val//&/\\&}"
    sed -i "s|define('${const}', '[^']*');|define('${const}', '${esc}');|" "$file"
    echo "==> Patched ${const}"
}
_patch_array_val() {
    local file="$1" key="$2" val="$3"
    [ -z "$val" ] && return
    [ -f "$file" ] || return
    local esc="${val//&/\\&}"
    sed -i "s|'${key}' => '[^']*'|'${key}' => '${esc}'|" "$file"
    echo "==> Patched ${key} in $(basename "$file")"
}
_patch_define "$RELEASE_PATH/config/config.php"      'ENV'               "${PROVISION_ENV:-}"
_patch_define "$RELEASE_PATH/config/site_owner.php"  'WEBSITE_NAME'      "${PROVISION_WEBSITE_NAME:-}"
_patch_define "$RELEASE_PATH/config/site_owner.php"  'OUR_NAME'          "${PROVISION_OUR_NAME:-}"
_patch_define "$RELEASE_PATH/config/site_owner.php"  'OUR_TELNUM'        "${PROVISION_OUR_TELNUM:-}"
_patch_define "$RELEASE_PATH/config/site_owner.php"  'OUR_ADDRESS'       "${PROVISION_OUR_ADDRESS:-}"
_patch_define "$RELEASE_PATH/config/site_owner.php"  'OUR_EMAIL_ADDRESS' "${PROVISION_OUR_EMAIL:-}"

<?php if ($db): ?>
cat <<'EOF' > "$RELEASE_PATH/config/database.php"
<?= "<" . "?php" ?>

$databases = [
    'default' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'user'     => '<?= $db_user ?>',
        'password' => '<?= $db_password ?>',
        'database' => '<?= $db_name ?>',
    ],
];
EOF
<?php else: ?>
# (no db_name configured — database.php not patched)
<?php endif; ?>
