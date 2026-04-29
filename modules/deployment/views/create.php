<div class="page-header">
  <div class="page-header-left">
    <div class="breadcrumb"><a href="deployment">Deployments</a> <span class="breadcrumb-sep">/</span> New</div>
    <div class="page-title">New Deployment</div>
  </div>
</div>

<?php if (!empty($_SESSION['form_submission_errors'])): ?>
  <div class="alert alert-danger">
    <?php foreach ($_SESSION['form_submission_errors'] as $errs): ?>
      <?php foreach ((array) $errs as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    <?php endforeach;
    unset($_SESSION['form_submission_errors']); ?>
  </div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (empty($servers)): ?>
  <div class="alert alert-danger">You need to <a href="server/create" style="color:#b91c1c;font-weight:600">add a server</a> first.</div>
<?php elseif (empty($environments)): ?>
  <div class="alert alert-danger">You need to <a href="environment/create" style="color:#b91c1c;font-weight:600">create an environment</a> first.</div>
<?php else: ?>
  <div class="card">
    <div class="card-body">
      <div class="form-wrap">
        <form method="post" action="<?= $form_location ?>" enctype="multipart/form-data">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Environment</label>
              <select name="environment_id" id="env-select" class="form-control" required onchange="applyEnvLock(this)">
                <option value="">— select environment —</option>
                <?php foreach ($environments as $e): ?>
                  <option value="<?= $e->id ?>"
                    data-locked-server="<?= (int)($e->locked_server_id ?? 0) ?>"
                    data-locked-name="<?= htmlspecialchars($e->locked_server_name ?? '') ?>"
                    <?= ((int)post('environment_id') === (int)$e->id || $preselected_env === (int)$e->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e->name) ?> (PHP <?= htmlspecialchars($e->php_version) ?>)
                    <?php if (!empty($e->locked_server_name)): ?>— locked to <?= htmlspecialchars($e->locked_server_name) ?><?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="form-hint"><a href="environment/create" style="color:#6366f1">Create a new environment →</a></span>
            </div>
            <div class="form-group">
              <label class="form-label">Server</label>
              <select name="server_id" id="server-select" class="form-control" required>
                <option value="">— select server —</option>
                <?php foreach ($servers as $s): ?>
                  <option value="<?= $s->id ?>" <?= ((int)post('server_id') === (int)$s->id || $preselected_server === (int)$s->id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s->name) ?> (<?= htmlspecialchars($s->ip_address) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <span id="server-lock-hint" style="display:none;font-size:.78rem;color:#6366f1;margin-top:.25rem"></span>
            </div>
          </div>

          <!-- App source -->
          <div class="form-group">
            <label class="form-label">App source</label>
            <div style="display:flex;gap:1.5rem;margin-top:.25rem">
              <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.875rem">
                <input type="radio" name="source_type" value="git" id="src-git"
                  <?= (post('source_type', true) ?: 'git') === 'git' ? 'checked' : '' ?>
                  onchange="toggleSource('git')">
                Git repository
              </label>
              <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.875rem">
                <input type="radio" name="source_type" value="zip" id="src-zip"
                  <?= post('source_type', true) === 'zip' ? 'checked' : '' ?>
                  onchange="toggleSource('zip')">
                Zip upload
              </label>
            </div>
          </div>

          <div id="src-git-fields">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Git repository URL</label>
                <input type="url" name="repo_url" class="form-control"
                  value="<?= htmlspecialchars(post('repo_url', true) ?: '') ?>"
                  placeholder="https://github.com/you/myapp.git">
              </div>
              <div class="form-group">
                <label class="form-label">Branch</label>
                <input type="text" name="branch" class="form-control"
                  value="<?= htmlspecialchars(post('branch', true) ?: 'main') ?>">
              </div>
            </div>
          </div>

          <div id="src-zip-fields" style="display:none">
            <div class="form-group">
              <label class="form-label">App zip file</label>
              <input type="file" name="zip_file" accept=".zip" class="form-control">
              <span class="form-hint">Upload a .zip of your application root</span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Custom deploy script <span style="color:#94a3b8;font-weight:400">(optional)</span></label>
            <select name="script_id" class="form-control">
              <option value="">— use default generated script —</option>
              <?php foreach ($deploy_scripts as $sc): ?>
                <option value="<?= $sc->id ?>" <?= (int)post('script_id') === (int)$sc->id ? 'selected' : '' ?>><?= htmlspecialchars($sc->name) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-hint">Leave blank to use Provision's default script. <a href="script/create?type=deploy" style="color:#6366f1">Create a custom script →</a></span>
          </div>

          <div class="form-group" style="border-top:1px solid #e2e8f0;padding-top:1rem;margin-top:.5rem">
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.875rem;font-weight:500;margin-bottom:.35rem">
              <input type="checkbox" name="is_canary" value="1" id="canary-check" <?= ($_SERVER['REQUEST_METHOD'] !== 'POST' || post('is_canary')) ? 'checked' : '' ?> onchange="toggleCanary(this.checked)">
              Canary deployment
            </label>
            <span class="form-hint">Deploy to this server first; monitor health before promoting to full traffic.</span>
          </div>
          <div class="form-group" id="canary-weight-group" style="<?= ($_SERVER['REQUEST_METHOD'] === 'POST' && !post('is_canary')) ? 'display:none' : '' ?>">
            <label class="form-label">Initial traffic weight (%)</label>
            <input type="number" name="canary_weight" class="form-control" min="1" max="99"
              value="<?= htmlspecialchars(post('canary_weight') ?: '10') ?>" style="max-width:120px">
            <span class="form-hint">Percentage of traffic routed to this deployment (1–99)</span>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Deployment</button>
            <a href="deployment" class="btn btn-secondary">Cancel</a>
          </div>
          <?= form_close() ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  function toggleCanary(on) {
    document.getElementById('canary-weight-group').style.display = on ? '' : 'none';
  }

  function applyEnvLock(sel) {
    var opt = sel.options[sel.selectedIndex];
    var locked = parseInt(opt.dataset.lockedServer || '0', 10);
    var name = opt.dataset.lockedName || '';
    var ss = document.getElementById('server-select');
    var hint = document.getElementById('server-lock-hint');

    if (locked) {
      for (var i = 0; i < ss.options.length; i++) {
        if (parseInt(ss.options[i].value, 10) === locked) {
          ss.selectedIndex = i;
          break;
        }
      }
      ss.onchange = (event) => {
        event.preventDefault();
      }
      hint.textContent = 'Locked to "' + name + '"';
      hint.style.display = '';
    } else {
      ss.onchange = undefined;
      hint.style.display = 'none';
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    var es = document.getElementById('env-select');
    if (es && es.value) applyEnvLock(es);
  });
</script>
<script src="js/env-form.js"></script>
