<?php
/**
 * View: scripts/_git_clone
 *
 * Emits the bash block that clones / updates the application repository.
 * Included from `deploy_script.php` — inherits its scope.
 *
 * Required vars from parent scope:
 *   @var string $repo   Git repository URL.
 *   @var string $branch Branch name.
 */
?>
REPO_URL="<?= $repo ?>"
BRANCH="<?= $branch ?>"

echo "==> Cloning repository into $RELEASE_PATH..."
git clone --branch "$BRANCH" "$REPO_URL" "$RELEASE_PATH"
