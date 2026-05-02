<?php
$_server_ipv6 = trim((string) ($server->ipv6_address ?? ''));
$_ipv6_part   = $_server_ipv6 !== ''
    ? ', <code>' . htmlspecialchars($_server_ipv6) . '</code>'
    : '';

$wizard_title           = 'Provisioning Server — Provision Setup';
$wizard_css             = 'customer-onboarding_module/css/onboarding.css';
$wizard_heading         = '&#9881; Provisioning Server';
$wizard_subheading_html = 'Installing the LAMP stack on <strong>' . htmlspecialchars($server->name) . '</strong>'
    . ' (<code>' . htmlspecialchars($server->ip_address) . '</code>' . $_ipv6_part . '). This takes a few minutes.';
$wizard_card_class = '';
$wizard_card_style = 'max-width:560px';
$wizard_css_inline = '
        #log-pre {
            background: #0f172a;
            color: #e2e8f0;
            font-family: \'SFMono-Regular\', Consolas, \'Liberation Mono\', Menlo, monospace;
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
        }';
include APPPATH . 'modules/wizard/views/open.php';
?>

    <pre id="log-pre">Connecting…</pre>
    <div id="status-msg"></div>

    <div id="next-panel" style="display:none">
        <?= wizard_step_dots(wizard_step_classes(8, 5)) ?>
        <a id="next-btn" href="customer-onboarding/dns_ssl" class="btn-primary">
            Configure DNS &amp; SSL &#10148;
        </a>
    </div>

    <p class="onboarding-footer-note">
        Step 5 of 8 &mdash;
        <a href="customer">Skip to Dashboard</a>
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
