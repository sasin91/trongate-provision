<?php
$single_ipv6_address = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $parts = explode('/', $value, 2);
    $address = $parts[0];
    if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return '';
    }

    if (!isset($parts[1]) || (int) $parts[1] >= 128) {
        return $address;
    }

    $packed = inet_pton($address);
    if ($packed === false) {
        return '';
    }

    $bytes = array_values(unpack('C*', $packed));
    for ($i = count($bytes) - 1; $i >= 0; $i--) {
        $bytes[$i]++;
        if ($bytes[$i] <= 255) {
            break;
        }
        $bytes[$i] = 0;
    }

    return inet_ntop(pack('C*', ...$bytes)) ?: $address;
};

$server_ip = trim((string) ($server->ip_address ?? ''));
$server_ipv4 = filter_var($server_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $server_ip : '';
$server_ipv6 = $single_ipv6_address((string) ($server->ipv6_address ?? ''));

if ($server_ipv6 === '' && filter_var($server_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $server_ipv6 = $single_ipv6_address($server_ip);
}

$copyable_value = static function (string $value): void {
    ?>
    <span class="copy-value" data-copy-value="<?= htmlspecialchars($value) ?>" title="Click to copy">
        <code><?= htmlspecialchars($value) ?></code>
        <button type="button" class="copy-btn" aria-label="Copy <?= htmlspecialchars($value) ?>">Copy</button>
    </span>
    <?php
};
?>
<style>
    #ssl-log-panel {
        display: none;
        margin: 1rem 0;
    }
    #ssl-log {
        background: #0f172a;
        color: #e2e8f0;
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: .775rem;
        line-height: 1.6;
        padding: 1rem 1.125rem;
        border-radius: 8px;
        max-height: 260px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
        margin: 0 0 .75rem;
    }
    #ssl-status-msg {
        font-size: .825rem;
        text-align: center;
        color: var(--text-muted);
        min-height: 1.25rem;
    }
</style>

    <?= validation_errors('<div class="error-message">', '</div>') ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="error-message"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <?php if ($domain === ''): ?>
        <div class="info-box">
            <strong>No domain configured.</strong><br>
            SSL can be enabled later after adding a domain to this environment.
        </div>
    <?php else: ?>
        <div class="summary-box">
            <div class="summary-row">
                <span>Domain</span>
                <strong><?= htmlspecialchars($domain) ?></strong>
            </div>
            <div class="summary-row">
                <span>Server IP</span>
                <strong><?php $copyable_value($server->ip_address); ?></strong>
            </div>
            <?php if ($server_ipv6 !== '' && $server_ipv6 !== $server_ip): ?>
                <div class="summary-row">
                    <span>Server IPv6</span>
                    <strong><?php $copyable_value($server_ipv6); ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($server_ipv4 !== ''): ?>
            <div class="dns-record">
                <div class="dns-record-row">
                    <span>Type</span>
                    <strong>A</strong>
                </div>
                <div class="dns-record-row">
                    <span>Name</span>
                    <strong><?= htmlspecialchars($domain) ?></strong>
                </div>
                <div class="dns-record-row">
                    <span>Value</span>
                    <strong><?php $copyable_value($server_ipv4); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($server_ipv6 !== ''): ?>
            <div class="dns-record">
                <div class="dns-record-row">
                    <span>Type</span>
                    <strong>AAAA</strong>
                </div>
                <div class="dns-record-row">
                    <span>Name</span>
                    <strong><?= htmlspecialchars($domain) ?></strong>
                </div>
                <div class="dns-record-row">
                    <span>Value</span>
                    <strong><?php $copyable_value($server_ipv6); ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <div class="guidance">
            <h4>Before running SSL</h4>
            <ol>
                <li>Create or update the DNS record<?= $server_ipv4 !== '' && $server_ipv6 !== '' ? 's' : '' ?> above at your DNS provider. In Porkbun, use the IPv4 address for the A record and the single IPv6 address for the AAAA record.</li>
                <?php if ($server_ipv6 !== ''): ?>
                    <li>If your provider shows a routed IPv6 range such as <code>2a01:4f9:c013:851d::/64</code>, do not paste the range into the AAAA record. Use one address from it, for example <code>2a01:4f9:c013:851d::1</code>.</li>
                <?php endif; ?>
                <li>Wait until the record<?= $server_ipv4 !== '' && $server_ipv6 !== '' ? 's resolve' : ' resolves' ?> to the server IP<?= $server_ipv4 !== '' && $server_ipv6 !== '' ? 's' : '' ?>.</li>
                <li>Leave ports 80 and 443 open so Let's Encrypt can validate the domain.</li>
            </ol>
        </div>

        <?php if (!$can_enable_ssl): ?>
            <div class="error-message"><?= htmlspecialchars($ssl_error) ?></div>
        <?php endif; ?>

        <?php if (!empty($ssl_retryable_failure)): ?>
            <div class="info-box">
                <strong>SSL can be enabled later.</strong><br>
                The server must allow the SSH user to run root-level commands before Provision can install certbot. Continue without SSL now, then enable SSL after connecting as root or adding passwordless sudo.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?= wizard_step_dots(wizard_step_classes(8, 6), 'steps steps--compact') ?>

    <?php if ($domain !== ''): ?>
        <button type="button" id="enable-ssl-btn" class="btn-primary" data-stream-url="<?= htmlspecialchars($ssl_stream_url) ?>" <?= $can_enable_ssl ? '' : 'disabled' ?>>
            <div class="spinner"></div>
            Enable SSL &amp; Continue
        </button>
        <div id="ssl-log-panel">
            <pre id="ssl-log">Connecting...</pre>
            <div id="ssl-status-msg"></div>
        </div>
    <?php endif; ?>

    <?= form_open('customer-onboarding/dns_ssl', ['class' => 'secondary-action-form']) ?>
    <input type="hidden" name="action" value="skip">
    <button type="submit" class="btn-secondary-onboarding">
        <?= $domain === '' || !empty($ssl_retryable_failure) ? 'Continue to Deployment Setup' : 'Skip SSL for Now' ?> &#10148;
    </button>
    <?= form_close() ?>

<script>
(function () {
    function fallbackCopy(value) {
        var input = document.createElement('textarea');
        input.value = value;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select();

        try {
            document.execCommand('copy');
        } finally {
            document.body.removeChild(input);
        }
    }

    function copyText(value) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(value);
        }

        fallbackCopy(value);
        return Promise.resolve();
    }

    document.querySelectorAll('[data-copy-value]').forEach(function (copyEl) {
        var button = copyEl.querySelector('.copy-btn');
        var originalText = button ? button.textContent : '';

        copyEl.addEventListener('click', function () {
            var value = copyEl.dataset.copyValue || '';
            if (!value) return;

            copyText(value).then(function () {
                if (!button) return;
                button.textContent = 'Copied';
                setTimeout(function () {
                    button.textContent = originalText;
                }, 1400);
            });
        });
    });

    var enableSslButton = document.getElementById('enable-ssl-btn');
    var skipForm = document.querySelector('.secondary-action-form');
    var sslPanel = document.getElementById('ssl-log-panel');
    var sslLog = document.getElementById('ssl-log');
    var sslStatus = document.getElementById('ssl-status-msg');

    function appendSslLog(line) {
        if (!sslLog) return;
        sslLog.textContent += line + '\n';
        sslLog.scrollTop = sslLog.scrollHeight;
    }

    function startSslStream() {
        if (!enableSslButton || typeof EventSource === 'undefined') return;

        enableSslButton.disabled = true;
        enableSslButton.classList.add('btn-loading');
        if (skipForm) {
            skipForm.style.display = 'none';
        }
        if (sslPanel) {
            sslPanel.style.display = 'block';
        }
        if (sslLog) {
            sslLog.textContent = 'Connecting...\n';
        }
        if (sslStatus) {
            sslStatus.textContent = '';
        }

        var stream = new EventSource(enableSslButton.dataset.streamUrl);

        stream.onmessage = function (event) {
            appendSslLog(event.data);
        };

        stream.addEventListener('done', function (event) {
            stream.close();
            var result = JSON.parse(event.data);
            if (result.status === 'success') {
                if (sslStatus) {
                    sslStatus.textContent = 'SSL enabled. Continuing...';
                }
                window.location.href = 'customer-onboarding/register_deployment';
                return;
            }

            enableSslButton.disabled = false;
            enableSslButton.classList.remove('btn-loading');
            if (skipForm) {
                skipForm.style.display = '';
            }
            if (sslStatus) {
                sslStatus.textContent = 'SSL setup failed. You can retry or skip SSL for now.';
            }
        });

        stream.onerror = function () {
            if (stream.readyState === EventSource.CLOSED) return;
            stream.close();
            appendSslLog('[Connection closed]');
            enableSslButton.disabled = false;
            enableSslButton.classList.remove('btn-loading');
            if (skipForm) {
                skipForm.style.display = '';
            }
            if (sslStatus) {
                sslStatus.textContent = 'Connection lost. You can retry or skip SSL for now.';
            }
        };
    }

    if (enableSslButton) {
        enableSslButton.addEventListener('click', startSslStream);
    }
})();
</script>
