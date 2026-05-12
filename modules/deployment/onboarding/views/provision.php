<?php
$_server_ipv6 = trim((string) ($server->ipv6_address ?? ''));
$_ipv6_part   = $_server_ipv6 !== '' ? ', ' . $_server_ipv6 : '';
?>
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
    .lamp-script-box {
        position: relative;
        margin-bottom: 1rem;
    }
    .lamp-script-box pre {
        background: #0f172a;
        color: #e2e8f0;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: .73rem;
        line-height: 1.5;
        padding: 1rem;
        border-radius: 8px;
        max-height: 200px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
        margin: 0;
    }
    .copy-btn {
        position: absolute;
        top: .5rem;
        right: .5rem;
        background: #334155;
        color: #e2e8f0;
        border: none;
        border-radius: 4px;
        padding: .25rem .65rem;
        font-size: .75rem;
        cursor: pointer;
    }
    .copy-btn:hover { background: #475569; }
</style>

<?php if (!$server_active): ?>
<div class="info-box" style="margin-bottom:1rem">
    <strong>Run this script on your server</strong> via SSH to install the LAMP stack.
    Connect as <code>root@<?= htmlspecialchars($server->ip_address) ?></code>
    <?php if ($_server_ipv6 !== ''): ?>(or <code><?= htmlspecialchars($_server_ipv6) ?></code>)<?php endif; ?>
    and paste the script below.
</div>

<div class="lamp-script-box">
    <pre id="lamp-script-pre"><?= htmlspecialchars($lamp_script) ?></pre>
    <button type="button" class="copy-btn" id="copy-script-btn">Copy</button>
</div>
<?php endif; ?>

<pre id="log-pre"><?= $server_active ? 'Server is already provisioned and active.' : 'Connecting…' ?></pre>
<div id="status-msg"></div>

<div id="next-panel" style="display:<?= $server_active ? '' : 'none' ?>">
    <?= wizard_step_dots(wizard_step_classes(5, 3)) ?>
    <a id="next-btn" href="deployment-onboarding/deployment" class="btn-primary">
        Configure Deployment &#10148;
    </a>
</div>

<script>
(function () {
    var log    = document.getElementById('log-pre');
    var msg    = document.getElementById('status-msg');
    var next   = document.getElementById('next-panel');
    var nextBtn = document.getElementById('next-btn');

    // Copy-to-clipboard
    var copyBtn = document.getElementById('copy-script-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var text = document.getElementById('lamp-script-pre').textContent;
            navigator.clipboard.writeText(text).then(function () {
                copyBtn.textContent = 'Copied!';
                setTimeout(function () { copyBtn.textContent = 'Copy'; }, 2000);
            }).catch(function () {
                copyBtn.textContent = 'Failed';
            });
        });
    }

    function showNext(ok) {
        msg.textContent = ok
            ? '✓ Provisioning complete!'
            : '✗ Provisioning failed — you can retry from here.';
        if (!ok) {
            nextBtn.href = 'deployment-onboarding/provision';
            nextBtn.textContent = 'Retry provisioning';
        }
        next.style.display = '';
    }

    function showStillRunning() {
        msg.textContent = 'Provisioning is still running — this page will check again shortly.';
        window.setTimeout(function () { window.location.reload(); }, 10000);
    }

    function retryProvisioning() {
        msg.textContent = 'Restarting provisioning…';
        window.setTimeout(function () { window.location.reload(); }, 1000);
    }

    <?php if (!$server_active): ?>
    var es = new EventSource('<?= BASE_URL ?>server/stream/<?= (int) $server->id ?>');

    es.onmessage = function (e) {
        log.textContent += e.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('done', function (e) {
        es.close();
        var result = JSON.parse(e.data);
        if (result.status === 'provisioning') {
            showStillRunning();
            return;
        }
        if (result.status === 'retry') {
            retryProvisioning();
            return;
        }
        showNext(result.status === 'active');
    });

    es.onerror = function () {
        if (es.readyState === EventSource.CLOSED) return;
        es.close();
        log.textContent += '\n[Connection closed]\n';
        msg.textContent = 'Connection lost — check the server page for status.';
        next.style.display = '';
    };
    <?php else: ?>
    showNext(true);
    <?php endif; ?>
})();
</script>
