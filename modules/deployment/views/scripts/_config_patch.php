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
 *   @var object $deployment   Deployment row, used to scope shared files.
 *   @var string $release_path Staged release path on the target server.
 *   @var string $db       Fallback database name (when DB_NAME is not in $env_vars).
 *   @var array  $env_vars Already-decrypted KEY => value map (may be empty).
 */
$db_name = (string) ($env_vars["DB_NAME"] ?? $db);
$db_user = (string) ($env_vars["DB_USER"] ?? "");
$db_password = (string) ($env_vars["DB_PASSWORD"] ?? "");
$database_config = "<?php\n\n" . '$databases = ' . var_export([
  "default" => [
    "host" => "127.0.0.1",
    "port" => "3306",
    "user" => $db_user,
    "password" => $db_password,
    "database" => $db_name,
  ],
], true) . ";\n";
$database_config_b64 = base64_encode($database_config);

$env_file_lines = [];
foreach ($env_vars as $key => $value) {
  $safe_key = preg_replace("/[^A-Z0-9_]/", "_", strtoupper((string) $key));
  if ($safe_key === "") {
    continue;
  }
  $escaped_value = str_replace(["\\", "\r", "\n", '"'], ["\\\\", "", "\\n", '\\"'], (string) $value);
  $env_file_lines[] = $safe_key . '="' . $escaped_value . '"';
}
$env_file_b64 = base64_encode(implode("\n", $env_file_lines) . (empty($env_file_lines) ? "" : "\n"));
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

<?php if ($db_name !== ""): ?>
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
if [ ! -w "$SHARED_ENV_DIR" ]; then
    if run_sudo install -d -m 2770 -o "$(id -un)" -g www-data "$SHARED_ENV_DIR"; then
        echo "==> Prepared $SHARED_ENV_DIR"
    else
        echo "ERROR: cannot prepare $SHARED_ENV_DIR for shared .env. Run server provisioning again or run: sudo mkdir -p /var/www/shared && sudo chown $(id -un):www-data /var/www/shared && sudo chmod 2770 /var/www/shared" >&2
        exit 1
    fi
fi
if [ ! -w "$SHARED_ENV_DIR" ]; then
    echo "ERROR: $SHARED_ENV_DIR is not writable by $(id -un). Run: sudo chown $(id -un):www-data /var/www/shared && sudo chmod 2770 /var/www/shared" >&2
    ls -ld "$SHARED_ENV_DIR" >&2 || true
    exit 1
fi
if [ -e "$SHARED_ENV_FILE" ] && [ ! -w "$SHARED_ENV_FILE" ]; then
    echo "ERROR: $SHARED_ENV_FILE exists but is not writable by $(id -un). Run: sudo chown $(id -un):www-data /var/www/shared/.env && sudo chmod 0640 /var/www/shared/.env" >&2
    ls -l "$SHARED_ENV_FILE" >&2 || true
    exit 1
fi
printf '%s' '<?= $env_file_b64 ?>' | base64 --decode > "$SHARED_ENV_FILE"
chmod 0640 "$SHARED_ENV_FILE"
if run_sudo chgrp www-data "$SHARED_ENV_FILE"; then
    echo "==> Shared .env group set to www-data"
fi
ln -sfn "$SHARED_ENV_FILE" "$RELEASE_PATH/.env"
echo "==> Linked release .env to $SHARED_ENV_FILE"
<?php else: ?>
# (no environment variables configured — shared .env not written)
<?php endif; ?>
