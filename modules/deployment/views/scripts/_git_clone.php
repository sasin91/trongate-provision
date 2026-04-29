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

echo "==> Cloning/updating repository..."
if [ -d "$WEB_ROOT/.git" ]; then
    git -C "$WEB_ROOT" fetch origin
    git -C "$WEB_ROOT" checkout $BRANCH
    git -C "$WEB_ROOT" pull origin $BRANCH
else
    mkdir -p "$WEB_ROOT"
    git clone --branch $BRANCH "$REPO_URL" "$WEB_ROOT"
fi
