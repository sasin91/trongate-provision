<?php
/**
 * View: scripts/_env_vars
 *
 * Emits the bash block that exports the deployment's environment variables.
 * Included from `deploy_script.php` — inherits its scope.
 *
 * Required vars from parent scope:
 *   @var array $env_vars Already-decrypted KEY => value map (may be empty).
 */

if (empty($env_vars)) {
  echo "# (no environment variables configured)\n";
  return;
}

echo "# Injected from environment variables (decrypted at script generation time)\n";
foreach ($env_vars as $k => $v) {
  $safe_k = preg_replace("/[^A-Z0-9_]/", "_", strtoupper((string) $k));
  $safe_v = str_replace("'", "'\\''", (string) $v);
  echo "export {$safe_k}='{$safe_v}'\n";
}
echo 'echo "==> ' .
  count($env_vars) .
  ' environment variable(s) loaded."' .
  "\n";
