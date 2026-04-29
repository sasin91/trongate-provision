<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploying App — Provision Setup</title>
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

<div class="onboarding-card" style="max-width:560px">
    <div class="onboarding-header">
        <h1>&#10148; Deploying App</h1>
        <p>Running deployment #<?= (int) $deployment->id ?> on
           <strong><?= htmlspecialchars($deployment->server_name) ?></strong>.</p>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="success-message"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <pre id="log-pre">Connecting…</pre>
    <div id="status-msg"></div>

    <div id="finish-panel" style="display:none">
        <div class="steps" style="margin-bottom:1.25rem">
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot completed"></div>
            <div class="step-dot active"></div>
        </div>
        <a href="customer" class="btn-primary">Go to Dashboard &#10148;</a>
        <p style="text-align:center;margin-top:.75rem;margin-bottom:0">
            <a href="deployment/show/<?= (int) $deployment->id ?>"
               style="font-size:.8rem;color:#9ca3af">View deployment details</a>
        </p>
    </div>

    <p style="text-align:center;margin-top:1.25rem;font-size:.8rem;color:#9ca3af">
        Step 9 of 9 &mdash;
        <a href="customer" style="color:#9ca3af">Go to Dashboard</a>
    </p>
</div>

<script>
(function () {
    var log    = document.getElementById('log-pre');
    var msg    = document.getElementById('status-msg');
    var finish = document.getElementById('finish-panel');

    var es = new EventSource('<?= BASE_URL ?>deployment/stream/<?= (int) $deployment->id ?>');

    es.onmessage = function (e) {
        log.textContent += e.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('done', function (e) {
        es.close();
        var result = JSON.parse(e.data);
        msg.textContent = result.status === 'success'
            ? '✓ Deployment complete!'
            : '✗ Deployment failed — you can retry from the deployment page.';
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

</body>
</html>
