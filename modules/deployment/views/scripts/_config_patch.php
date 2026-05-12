<?php
/**
 * Partial: _config_patch
 *
 * Emits bash that patches config/*.php and writes a shared .env.
 * Included by deploy_script.php — inherits $db_name, $db_user, $db_pass, $env_vars.
 */
$database_config = "<?php\n\n" . '$databases = ' . var_export([
    'default' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'user'     => $db_user,
        'password' => $db_pass,
        'database' => $db_name,
    ],
], true) . ";\n";
$database_config_b64 = base64_encode($database_config);

$env_file_lines = [];
foreach ($env_vars as $key => $value) {
    $safe_key = preg_replace('/[^A-Z0-9_]/', '_', strtoupper((string) $key));
    if ($safe_key === '') { continue; }
    $escaped = str_replace(['\\', "\r", "\n", '"'], ['\\\\', '', '\\n', '\\"'], (string) $value);
    $env_file_lines[] = $safe_key . '="' . $escaped . '"';
}
$env_file_b64 = base64_encode(implode("\n", $env_file_lines) . (empty($env_file_lines) ? '' : "\n"));
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

<?php if ($db_name !== ''): ?>
echo "==> Writing database config"
mkdir -p "$RELEASE_PATH/config"
printf '%s' '<?= $database_config_b64 ?>' | base64 --decode > "$RELEASE_PATH/config/database.php"
echo "==> Patched database.php"
<?php else: ?>
# (no db_name configured — database.php not patched)
<?php endif; ?>

<?php if (!empty($env_file_lines)): ?>
echo "==> Writing shared .env"
SHARED_ENV_DIR="/var/www/shared"
SHARED_ENV_FILE="$SHARED_ENV_DIR/.env"
[ ! -d "$SHARED_ENV_DIR" ] && run_sudo install -d -m 2770 -o "$(id -un)" -g www-data "$SHARED_ENV_DIR"
printf '%s' '<?= $env_file_b64 ?>' | base64 --decode > "$SHARED_ENV_FILE"
chmod 0640 "$SHARED_ENV_FILE"
run_sudo chgrp www-data "$SHARED_ENV_FILE" 2>/dev/null || true
ln -sfn "$SHARED_ENV_FILE" "$RELEASE_PATH/.env"
echo "==> Linked release .env to $SHARED_ENV_FILE"
<?php else: ?>
# (no env_vars configured — shared .env not written)
<?php endif; ?>
