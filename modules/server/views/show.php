<div class="page-header">
  <div class="page-header-left">
    <div class="breadcrumb">
      <a href="server">Servers</a>
      <span class="breadcrumb-sep">/</span>
      <a href="environment/show/<?= $server->environment_id ?>"><?= htmlspecialchars(
  $server->env_name,
) ?></a>
      <span class="breadcrumb-sep">/</span>
      <?= htmlspecialchars($server->name) ?>
    </div>
    <div class="page-title"><?= htmlspecialchars($server->name) ?></div>
  </div>
  <div class="actions-row">
    <?php if ($server->status !== "active"): ?>
      <button id="provision-btn" class="btn btn-primary" onclick="startProvision(<?= $server->id ?>)">&#9654; Provision</button>
      <?= form_open("server/mark_active/" . $server->id, [
        "style" => "display:inline;margin:0",
      ]) ?>
      <button type="submit" class="btn btn-secondary">Mark as Active</button>
      <?= form_close() ?>
    <?php endif; ?>
    <?php if ($server->provider === "hetzner"): ?>
      <?= form_open("server/reboot/" . $server->id, [
        "style" => "display:inline;margin:0",
      ]) ?>
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Reboot this Hetzner server?')">&#8635; Reboot</button>
      <?= form_close() ?>
    <?php endif; ?>
    <?php if ($server->status === "active" && !empty($server->domain)): ?>
      <?= form_open("server/enable_ssl/" . $server->id, [
        "style" => "display:inline;margin:0",
      ]) ?>
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Run certbot for <?= htmlspecialchars(
        $server->domain,
      ) ?>? DNS must already point at this server.')">Enable SSL</button>
      <?= form_close() ?>
    <?php endif; ?>
    <a href="deployment/create?server=<?= $server->id ?>" class="btn btn-secondary">+ New Deployment</a>
    <?= form_open("server/delete/" . $server->id, [
      "style" => "display:inline;margin:0",
    ]) ?>
    <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this server and all its deployments?')">Delete</button>
    <?= form_close() ?>
  </div>
</div>

<div class="detail-grid" id="server-detail">
  <div class="detail-item">
    <label>Environment</label>
    <span><a href="environment/show/<?= $server->environment_id ?>" style="color:#6366f1;text-decoration:none"><?= htmlspecialchars(
  $server->env_name,
) ?></a></span>
  </div>
  <div class="detail-item">
    <label>Status</label>
    <span><span class="badge badge-<?= $server->status ?>"><?= htmlspecialchars(
  $server->status,
) ?></span></span>
  </div>
  <div class="detail-item">
    <label>IP Address</label>
    <span><code><?= htmlspecialchars($server->ip_address) ?></code></span>
  </div>
  <div class="detail-item">
    <label>PHP Version</label>
    <span>PHP <?= htmlspecialchars(
      $server->php_version,
    ) ?> <span style="font-size:.78rem;color:#94a3b8">(from environment)</span></span>
  </div>
  <div class="detail-item">
    <label>SSH User</label>
    <span><?= htmlspecialchars($server->ssh_user) ?></span>
  </div>
  <div class="detail-item">
    <label>SSH Port</label>
    <span><?= (int) $server->ssh_port ?></span>
  </div>
  <?php if ($server->provider !== "manual"): ?>
    <div class="detail-item">
      <label>Cloud Provider</label>
      <span><?= ucfirst(htmlspecialchars($server->provider)) ?>
        <?php if (!empty($server->region)): ?>
          <span style="color:#94a3b8;font-size:.8rem">&middot; <?= htmlspecialchars(
            $server->region,
          ) ?></span>
        <?php endif; ?>
      </span>
    </div>
    <div class="detail-item">
      <label>Plan</label>
      <span><?= htmlspecialchars($server->server_type ?? "—") ?></span>
    </div>
  <?php endif; ?>
</div>

<!-- LAMP Script Editor -->
<form method="post" action="server/save_lamp_script/<?= $server->id ?>">
  <div style="display:grid;grid-template-columns:1fr 220px;gap:1.25rem;align-items:start">
    <div class="card">
      <div class="card-header">
        <span class="card-title">LAMP Provisioning Script</span>
        <div style="display:flex;gap:.5rem">
          <button type="button" onclick="copyScript('lamp-script')" class="btn btn-secondary btn-sm">Copy</button>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </div>
      <div style="padding:0">
        <textarea name="body" id="lamp-script" rows="30"
          style="width:100%;background:#0f172a;color:#7dd3fc;font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;font-size:.8rem;line-height:1.65;padding:1.5rem;border:none;outline:none;resize:vertical;display:block;box-sizing:border-box"><?= htmlspecialchars(
            $lamp_script,
          ) ?></textarea>
      </div>
      <div style="padding:.75rem 1.25rem;border-top:1px solid #e2e8f0;font-size:.8rem;color:#64748b">
        Run as root on your server: <code>bash lamp-setup.sh</code> &mdash; then click <strong>Mark as Active</strong> above.
      </div>
    </div>

    <div style="position:sticky;top:4rem;display:flex;flex-direction:column;gap:1rem">
      <div class="card">
        <div class="card-header"><span class="card-title">Variables</span></div>
        <div class="card-body" style="padding:.75rem">
          <?php foreach ($lamp_vars as $var => $desc): ?>
            <div style="margin-bottom:.6rem">
              <code style="font-size:.75rem;color:#6366f1;cursor:pointer;display:block" onclick="insertVar('<?= htmlspecialchars(
                $var,
              ) ?>')"><?= htmlspecialchars($var) ?></code>
              <span style="font-size:.72rem;color:#64748b"><?= htmlspecialchars(
                $desc,
              ) ?></span>
            </div>
          <?php endforeach; ?>
          <p style="font-size:.72rem;color:#94a3b8;margin-top:.5rem">Click a variable to insert at cursor.</p>
        </div>
      </div>

      <?php if (!empty($lamp_scripts)): ?>
        <div class="card">
          <div class="card-header"><span class="card-title">History</span></div>
          <div style="font-size:.8rem">
            <?php foreach ($lamp_scripts as $s): ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem .875rem;border-bottom:1px solid #f1f5f9">
                <div>
                  <div style="font-weight:500;color:#0f172a"><?= htmlspecialchars(
                    $s->name,
                  ) ?></div>
                  <div style="font-size:.72rem;color:#94a3b8"><?= date(
                    "M j, Y H:i",
                    strtotime($s->created_at),
                  ) ?></div>
                </div>
                <a href="script/show/<?= $s->id ?>" class="btn btn-secondary btn-sm" style="white-space:nowrap">View</a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<!-- Deployments & Health -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Deployments</span>
    <div style="display:flex;gap:.5rem;align-items:center">
      <?php if (!empty($deployments)): ?>
        <?= form_open("server-health/check_server/" . $server->id, [
          "style" => "display:inline;margin:0",
        ]) ?>
        <button type="submit" class="btn btn-secondary btn-sm">&#10003; Check All</button>
        <?= form_close() ?>
      <?php endif; ?>
      <a href="deployment/create?server=<?= $server->id ?>" class="btn btn-secondary btn-sm">+ New</a>
    </div>
  </div>

  <?php if (empty($deployments)): ?>
    <div class="empty-state">
      <div class="empty-icon">&#10148;</div>
      <div class="empty-title">No deployments yet</div>
      <div class="empty-desc">Create a deployment to deploy your application to this server.</div>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="ptable">
        <thead>
          <tr>
            <th>Environment</th>
            <th>Branch</th>
            <th>Domain</th>
            <th>Status</th>
            <th>Health</th>
            <th>Response</th>
            <th>Checked</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($deployments as $d):
            $hs = $d->health_status ?? "unknown"; ?>
            <tr>
              <td><?= htmlspecialchars($d->env_name ?? "—") ?></td>
              <td><code style="font-size:.8rem;color:#6366f1"><?= htmlspecialchars(
                $d->branch ?? "—",
              ) ?></code></td>
              <td><?= htmlspecialchars($d->domain ?: "—") ?></td>
              <td><span class="badge badge-<?= htmlspecialchars(
                str_replace("_", "-", $d->status),
              ) ?>"><?= htmlspecialchars($d->status) ?></span></td>
              <td>
                <span class="badge badge-<?= $hs === "healthy"
                  ? "active"
                  : ($hs === "unhealthy"
                    ? "failed"
                    : "pending") ?>">
                  <?= htmlspecialchars($hs) ?>
                </span>
              </td>
              <td style="font-size:.8rem;color:#64748b">
                <?= $d->response_time_ms !== null
                  ? $d->response_time_ms . "ms"
                  : "—" ?>
                <?= $d->http_status
                  ? ' <code style="font-size:.75rem">' .
                    (int) $d->http_status .
                    "</code>"
                  : "" ?>
              </td>
              <td style="font-size:.78rem;color:#94a3b8">
                <?= $d->health_checked_at
                  ? date("M j H:i", strtotime($d->health_checked_at))
                  : "Never" ?>
              </td>
              <td style="display:flex;gap:.35rem">
                <?= form_open("server-health/check/deployment/" . $d->id, [
                  "style" => "display:inline;margin:0",
                ]) ?>
                <button type="submit" class="btn btn-secondary btn-sm" title="Check now">&#10003;</button>
                <?= form_close() ?>
                <a href="deployment/show/<?= $d->id ?>" class="btn btn-secondary btn-sm">View</a>
              </td>
            </tr>
          <?php
          endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div id="provision-log-panel" style="display:none;margin-top:1.25rem">
  <div class="card">
    <div class="card-header">
      <span class="card-title" id="provision-log-title">Provisioning…</span>
      <span id="provision-status-badge"></span>
    </div>
    <pre id="provision-log" style="margin:0;padding:1rem 1.25rem;font-size:.78rem;line-height:1.6;overflow-x:auto;overflow-y:auto;max-height:480px;background:#0f172a;color:#e2e8f0;border-radius:0 0 .5rem .5rem"></pre>
  </div>
</div>

<script>
function startProvision(id) {
    var btn   = document.getElementById('provision-btn');
    var panel = document.getElementById('provision-log-panel');
    var log   = document.getElementById('provision-log');
    var title = document.getElementById('provision-log-title');
    var badge = document.getElementById('provision-status-badge');

    btn.disabled = true;
    btn.textContent = '⏳ Provisioning…';
    log.textContent = '';
    panel.style.display = '';
    panel.scrollIntoView({behavior: 'smooth', block: 'start'});

    var es = new EventSource('<?= BASE_URL ?>server/stream/' + id);

    es.onmessage = function(e) {
        log.textContent += e.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('done', function(e) {
        es.close();
        var result = JSON.parse(e.data);
        var ok = result.status === 'active';

        title.textContent = ok ? 'Provisioning complete' : 'Provisioning failed';
        badge.innerHTML   = ok
            ? '<span class="badge badge-active">active</span>'
            : '<span class="badge badge-failed">failed</span>';

        var statusBadge = document.querySelector('.detail-item .badge');
        if (statusBadge) {
            statusBadge.className   = 'badge badge-' + (ok ? 'active' : 'failed');
            statusBadge.textContent = result.status;
        }

        btn.textContent = '↺ Re-provision';
        btn.disabled    = false;
    });

    es.onerror = function() {
        if (es.readyState === EventSource.CLOSED) return;
        es.close();
        log.textContent += '\n[connection closed]\n';
        btn.disabled    = false;
        btn.textContent = '↺ Re-provision';
    };
}
</script>

<script src="js/script-editor.js"></script>
