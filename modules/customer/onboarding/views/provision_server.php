<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provisioning Server — Provision Setup</title>
    <link rel="stylesheet" href="customer-onboarding_module/css/onboarding.css">
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
</head>
<body>

<?php $server_ipv6 = trim((string) ($server->ipv6_address ?? '')); ?>

<div class="onboarding-card" style="max-width:560px">
    <div class="onboarding-header">
        <h1>&#9881; Provisioning Server</h1>
        <p>
            Installing the LAMP stack on <strong><?= htmlspecialchars($server->name) ?></strong>
            (<code><?= htmlspecialchars($server->ip_address) ?></code><?php if ($server_ipv6 !== ''): ?>,
            <code><?= htmlspecialchars($server_ipv6) ?></code><?php endif; ?>). This takes a few minutes.
        </p>
    </div>

    <pre id="log-pre">Connecting…</pre>
    <div id="status-msg"></div>

    <div id="next-panel" style="display:none">
        <div class="steps" style="margin-bottom:1.25rem">
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot active"></div>
            <div class="step-dot"></div>
            <div class="step-dot"></div>
        </div>
        <a id="next-btn" href="customer-onboarding/dns_ssl" class="btn-primary">
            Configure DNS &amp; SSL &#10148;
        </a>
    </div>

    <p style="text-align:center;margin-top:1.25rem;font-size:.8rem;color:#9ca3af">
        Step 5 of 8 &mdash;
        <a href="customer" style="color:#9ca3af">Skip to Dashboard</a>
    </p>
</div>

<script>
(function () {
    var log    = document.getElementById('log-pre');
    var msg    = document.getElementById('status-msg');
    var next   = document.getElementById('next-panel');
    var nextBtn = document.getElementById('next-btn');

    function showNext(ok) {
        msg.textContent = ok
            ? '✓ Provisioning complete!'
            : '✗ Provisioning failed — you can retry from here.';
        if (!ok) {
            nextBtn.href = 'customer-onboarding/provision_server';
            nextBtn.textContent = 'Retry provisioning';
        }
        next.style.display = '';
    }

    function showStillRunning() {
        msg.textContent = 'Provisioning is still running — this page will check again shortly.';
        window.setTimeout(function () {
            window.location.reload();
        }, 10000);
    }

    function retryProvisioning() {
        msg.textContent = 'Restarting provisioning…';
        window.setTimeout(function () {
            window.location.reload();
        }, 1000);
    }

    <?php if ($server->status === 'active'): ?>
    log.textContent = 'Server is already provisioned and active.';
    showNext(true);
    <?php else: ?>
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
    <?php endif; ?>
})();
</script>

</body>
</html>
