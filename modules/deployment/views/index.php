<?php
$success = $flash['success'] ?? '';
$error   = $flash['error']   ?? '';
?>
<div class="page-header">
    <div class="page-title">Deploy</div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem">
    <h3 style="margin:0 0 1rem">Actions</h3>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
        <button id="btn-deploy" class="btn btn-primary" onclick="startDeploy()">&#9654; Deploy</button>
        <form method="post" action="deployment/promote" style="margin:0">
            <button type="submit" class="btn btn-success">&#8593; Promote</button>
        </form>
        <form method="post" action="deployment/demote" style="margin:0">
            <button type="submit" class="btn btn-warning">&#8595; Rollback</button>
        </form>
    </div>
</div>

<div class="card">
    <h3 style="margin:0 0 1rem">Output</h3>
    <pre id="deploy-log" style="background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:.5rem;min-height:10rem;overflow-x:auto;font-size:.8rem;white-space:pre-wrap"></pre>
</div>

<script>
function startDeploy() {
    const log  = document.getElementById('deploy-log');
    const btn  = document.getElementById('btn-deploy');
    log.textContent = '';
    btn.disabled = true;
    btn.textContent = '… Deploying';

    const es = new EventSource('deployment/stream');
    es.onmessage = function(e) {
        const d = JSON.parse(e.data);
        if (d.line !== undefined) {
            log.textContent += d.line + '\n';
            log.scrollTop = log.scrollHeight;
        }
        if (d.done) {
            es.close();
            btn.disabled = false;
            btn.textContent = '▶ Deploy';
            log.textContent += '\n— exit ' + d.exit + ' —';
        }
    };
    es.onerror = function() {
        es.close();
        btn.disabled = false;
        btn.textContent = '▶ Deploy';
        log.textContent += '\n[connection closed]';
    };
}
</script>
