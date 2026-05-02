<style>
    #log-pre {
        background: #0f172a;
        color: #e2e8f0;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: .775rem;
        line-height: 1.6;
        padding: 1rem 1.125rem;
        border-radius: 8px;
        max-height: 300px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
        margin: 0 0 1rem;
    }
    #status-msg {
        font-size: .825rem;
        text-align: center;
        color: var(--text-muted);
        min-height: 1.25rem;
        margin-bottom: .75rem;
    }
</style>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="success-message"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <pre id="log-pre">Connecting…</pre>
    <div id="status-msg"></div>

    <div id="finish-panel" style="display:none">
        <?= wizard_step_dots(wizard_step_classes(8, 8)) ?>
        <a href="customer" class="btn-primary">Go to Dashboard &#10148;</a>
        <p style="text-align:center;margin-top:.75rem;margin-bottom:0">
            <a href="deployment/show/<?= (int) $deployment->id ?>"
               style="font-size:.8rem;color:#9ca3af">View deployment details</a>
        </p>
    </div>

<script>
(function () {
    var log    = document.getElementById('log-pre');
    var msg    = document.getElementById('status-msg');
    var finish = document.getElementById('finish-panel');

    var es = new EventSource('<?= htmlspecialchars($stream_url) ?>');

    es.onmessage = function (e) {
        log.textContent += e.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('state', function (e) {
        var state = JSON.parse(e.data);
        if (state.status === 'running') {
            log.textContent += state.message + '\n';
            log.scrollTop = log.scrollHeight;
            msg.textContent = 'Deployment is still running. Checking again shortly...';
        }
    });

    es.addEventListener('done', function (e) {
        es.close();
        var result = JSON.parse(e.data);
        if (result.status === 'running') {
            msg.textContent = 'Deployment is still running. Checking again shortly...';
            window.setTimeout(function () {
                window.location.reload();
            }, 5000);
            return;
        }
        if (result.status === 'missing_zip') {
            msg.textContent = 'The uploaded zip is no longer available. Upload it again to retry.';
            finish.querySelector('.btn-primary').href = 'customer-onboarding/register_deployment';
            finish.querySelector('.btn-primary').textContent = 'Upload zip again';
            finish.style.display = '';
            return;
        }
        if (result.status === 'staged') {
            msg.textContent = 'Release staged. Open the deployment page to update the database and promote it.';
            fetch('<?= BASE_URL ?>customer-onboarding/complete', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).catch(function () {
                msg.textContent = 'Release staged. Refresh once before opening the dashboard.';
            });
        } else {
            msg.textContent = '✗ Deployment failed — you can retry from the deployment page.';
        }
        finish.style.display = '';
    });

    es.onerror = function () {
        if (es.readyState === EventSource.CLOSED) return;
        es.close();
        log.textContent += '\n[Connection closed]\n';
        finish.style.display = '';
    };
})();
</script>
