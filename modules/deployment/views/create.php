<?php
$has_deployment = !empty($deployment) && $deployment !== false;
$status = $has_deployment ? (string) $deployment->status : "new";
$deployment_id = $has_deployment ? (int) $deployment->id : 0;
$wizard_urls = $has_deployment ? [
  "stream" => BASE_URL . "deployment/stream/" . $deployment_id,
  "scan_sql" => BASE_URL . "deployment/scan_release_sql/" . $deployment_id,
  "delete_sql" => BASE_URL . "deployment/delete_release_sql/" . $deployment_id,
  "promote" => BASE_URL . "deployment/promote_release_wizard/" . $deployment_id,
] : [];
$step_classes = [
  "completed",
  in_array($status, ["running", "staged", "success", "failed"], true) ? "completed" : ($has_deployment ? "active" : ""),
  in_array($status, ["staged", "success"], true) ? "active" : "",
  $status === "success" ? "completed" : "",
];
if ($status === "success") {
  $step_classes = ["completed", "completed", "completed", "active"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <base href="<?= BASE_URL ?>">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deployment Wizard - Provision</title>
  <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
  <link rel="stylesheet" href="deployment_module/css/create.css">
</head>
<body>

<div class="onboarding-card onboarding-card--large deployment-wizard-card"
  <?php if ($has_deployment): ?>
    data-deployment-id="<?= $deployment_id ?>"
    data-status="<?= htmlspecialchars($status) ?>"
    data-stream-url="<?= htmlspecialchars($wizard_urls["stream"]) ?>"
    data-scan-sql-url="<?= htmlspecialchars($wizard_urls["scan_sql"]) ?>"
    data-delete-sql-url="<?= htmlspecialchars($wizard_urls["delete_sql"]) ?>"
    data-promote-url="<?= htmlspecialchars($wizard_urls["promote"]) ?>"
  <?php endif; ?>>
  <div class="onboarding-header">
    <h1><?= $has_deployment ? "Release Wizard" : "New Staged Release" ?></h1>
    <p><?= $has_deployment ? "Stage, review SQL files, and promote deployment #" . (int) $deployment->id . "." : "Create a staged release before switching the live web root." ?></p>
  </div>

  <div class="steps steps--compact">
    <?php foreach ($step_classes as $class): ?>
      <div class="step-dot <?= htmlspecialchars($class) ?>"></div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($_SESSION['form_submission_errors'])): ?>
    <div class="error-message">
      <?php foreach ($_SESSION['form_submission_errors'] as $errs): ?>
        <?php foreach ((array) $errs as $err): ?>
          <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      <?php endforeach; unset($_SESSION['form_submission_errors']); ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="error-message"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="success-message"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
  <?php endif; ?>

  <?php if (!$has_deployment): ?>
    <?php if (empty($servers)): ?>
      <div class="error-message">You need to <a href="server/create">add a server</a> first.</div>
    <?php elseif (empty($environments)): ?>
      <div class="error-message">You need to <a href="environment/create">create an environment</a> first.</div>
    <?php else: ?>
      <form method="post" action="<?= htmlspecialchars($form_location) ?>" enctype="multipart/form-data">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Environment</label>
            <select name="environment_id" id="env-select" class="form-select" required>
              <option value="">select environment</option>
              <?php foreach ($environments as $e): ?>
                <option value="<?= (int) $e->id ?>"
                  data-locked-server="<?= (int) ($e->locked_server_id ?? 0) ?>"
                  data-locked-name="<?= htmlspecialchars($e->locked_server_name ?? '') ?>"
                  <?= ((int) post('environment_id') === (int) $e->id || $preselected_env === (int) $e->id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($e->name) ?> (PHP <?= htmlspecialchars($e->php_version) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint"><a href="environment/create">Create a new environment</a></span>
          </div>
          <div class="form-group">
            <label class="form-label">Server</label>
            <select name="server_id" id="server-select" class="form-select" required>
              <option value="">select server</option>
              <?php foreach ($servers as $s): ?>
                <option value="<?= (int) $s->id ?>" <?= ((int) post('server_id') === (int) $s->id || $preselected_server === (int) $s->id) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s->name) ?> (<?= htmlspecialchars($s->ip_address) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <span id="server-lock-hint" class="server-lock-hint"></span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">App source</label>
          <div class="source-options">
            <label class="source-option">
              <input type="radio" name="source_type" value="git" id="src-git" <?= (post('source_type', true) ?: 'git') === 'git' ? 'checked' : '' ?>>
              Git repository
            </label>
            <label class="source-option">
              <input type="radio" name="source_type" value="zip" id="src-zip" <?= post('source_type', true) === 'zip' ? 'checked' : '' ?>>
              Zip upload
            </label>
          </div>
        </div>

        <div id="src-git-fields">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Git repository URL</label>
              <input type="url" name="repo_url" class="form-input" value="<?= htmlspecialchars(post('repo_url', true) ?: '') ?>" placeholder="https://github.com/you/myapp.git">
            </div>
            <div class="form-group">
              <label class="form-label">Branch</label>
              <input type="text" name="branch" class="form-input" value="<?= htmlspecialchars(post('branch', true) ?: 'main') ?>">
            </div>
          </div>
        </div>

        <div id="src-zip-fields" class="is-hidden">
          <div class="form-group">
            <label class="form-label">App zip file</label>
            <input type="file" name="zip_file" accept=".zip" class="form-input">
            <span class="form-hint">Upload a .zip of your application root.</span>
          </div>
        </div>

        <div class="info-box">Provision stages files into <code>/var/www/releases</code>, lists SQL files for manual review, deletes those SQL files from the staged release, then promotes the symlink.</div>

        <div class="form-actions">
          <button type="submit" class="btn-primary"><span class="spinner"></span>Create staged deployment</button>
          <a href="deployment" class="btn-secondary-onboarding">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <div class="summary-box">
      <div class="summary-row"><span>Environment</span><strong><?= htmlspecialchars($deployment->env_name) ?></strong></div>
      <div class="summary-row"><span>Server</span><strong><?= htmlspecialchars($deployment->server_name) ?> - <?= htmlspecialchars($deployment->ip_address) ?></strong></div>
      <div class="summary-row"><span>Status</span><strong id="wizard-status"><?= htmlspecialchars($status) ?></strong></div>
      <?php if (!empty($deployment->release_path)): ?>
        <div class="summary-row"><span>Release</span><strong><?= htmlspecialchars($deployment->release_path) ?></strong></div>
      <?php endif; ?>
    </div>

    <div class="wizard-section">
      <section id="deploy-panel" class="wizard-panel">
        <h2>Deploy staged release</h2>
        <pre id="wizard-log" class="log-pre"><?= $status === "script_ready" ? "Waiting to start..." : htmlspecialchars((string) ($deployment->run_log ?? "No deployment output yet.")) ?></pre>
        <div id="deploy-msg" class="status-msg"></div>
      </section>

      <section id="sql-panel" class="wizard-panel <?= in_array($status, ["staged", "success"], true) ? "" : "is-muted" ?>">
        <h2>Review SQL files</h2>
        <p id="sql-msg">SQL scan starts after the release is staged.</p>
        <div id="sql-list" class="sql-list"></div>
        <button type="button" id="delete-sql-btn" class="btn-secondary-onboarding is-hidden">Delete SQL files from staged release</button>
      </section>

      <section id="promote-panel" class="wizard-panel <?= $status === "success" ? "" : "is-muted" ?>">
        <h2>Release staged deployment</h2>
        <p id="promote-msg"><?= $status === "success" ? "Release is live." : "Promotion unlocks after SQL files have been reviewed and removed." ?></p>
        <button type="button" id="promote-btn" class="btn-primary" <?= $status === "staged" ? "" : "disabled" ?>>Promote release</button>
      </section>
    </div>

    <p class="panel-link"><a href="deployment/show/<?= (int) $deployment->id ?>">View deployment details</a></p>
  <?php endif; ?>
</div>

<script src="deployment_module/js/create.js"></script>
</body>
</html>
