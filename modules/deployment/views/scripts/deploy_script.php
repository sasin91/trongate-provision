<?php
/**
 * View: scripts/deploy_script
 *
 * Renders the bash deployment script for a given deployment row using the
 * same editable template shown in the deployment-create UI.
 *
 * @var object $deployment The deployment row joined with server / environment / script data.
 * @var array  $env_vars   Already-decrypted KEY => value map (empty when none).
 */

$d = $deployment;

$source = $d->source_type ?? "git";
// Provider-fetched zip: SCP'd by stream(), treat as zip on remote.
if ($source === "git" && !empty($d->zip_path)) {
  $source = "zip";
}

$repo = $d->repo_url ?? "";
$branch = $d->branch ?? "main";
$zip_url = "";
$webroot = $d->web_root ?: "/var/www/html";
$domain = $d->domain ?: "";
$db = $d->db_name ?: "";
$env_vars = $env_vars ?? [];
$release_path = "/var/www/releases/deployment_" . (int) $d->id . "_" . date("YmdHis");

$render_block = static function (string $file) use (
  $repo,
  $branch,
  $zip_url,
  $webroot,
  $domain,
  $db,
  $env_vars,
  $release_path,
): string {
  ob_start();
  include __DIR__ . "/" . $file;
  return trim((string) ob_get_clean());
};

$template_path = dirname(__DIR__, 2) . "/assets/deploy_script_template.txt";
$template = is_file($template_path) ? (string) file_get_contents($template_path) : "";

$vhost_block = $domain ? $render_block("_vhost.php") : "# (no domain configured)";
$config_patch_block = $render_block("_config_patch.php");

echo strtr($template, [
  "{{SERVER_NAME}}" => (string) ($d->server_name ?? ""),
  "{{SERVER_IP}}" => (string) ($d->ip_address ?? ""),
  "{{SOURCE_TYPE}}" => $source,
  "{{REPO_URL}}" => (string) $repo,
  "{{BRANCH}}" => (string) $branch,
  "{{WEB_ROOT}}" => (string) $webroot,
  "{{RELEASE_PATH}}" => $release_path,
  "{{DOMAIN}}" => (string) $domain,
  "{{DB_NAME}}" => (string) $db,
  "{{ENV_VARS}}" => $render_block("_env_vars.php"),
  "{{VHOST_BLOCK}}" => $vhost_block,
  "{{CONFIG_PATCH_BLOCK}}" => $config_patch_block,
]);
