<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Providers</div>
        <div style="font-size:.85rem;color:#64748b;margin-top:.25rem">Cloud provider integrations for server provisioning</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <span class="card-title">Hetzner Cloud</span>
            <?php if ($has_hetzner): ?>
                <span class="badge badge-active" style="margin-left:.6rem">Connected</span>
            <?php endif; ?>
        </div>
        <?php if (!$has_hetzner): ?>
            <a href="provider/connect" class="btn btn-primary btn-sm">Connect</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($has_hetzner): ?>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>SSH Key</label>
                    <span>
                        <?php if (!empty($hetzner['ssh_key_id'])): ?>
                            <span class="badge badge-active">Uploaded</span>
                            <span style="font-size:.78rem;color:#94a3b8;margin-left:.4rem"><?= htmlspecialchars($hetzner['ssh_key_label'] ?? '') ?> #<?= htmlspecialchars($hetzner['ssh_key_id']) ?></span>
                        <?php else: ?>
                            <span class="badge badge-pending">Not uploaded</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>API Token</label>
                    <span><code style="font-size:.82rem">••••••••<?= htmlspecialchars(substr($hetzner['token'] ?? '', -4)) ?></code></span>
                </div>
            </div>

            <div class="actions-row" style="margin-top:1rem">
                <a href="server/create" class="btn btn-primary">+ Provision Hetzner Server</a>
                <form method="post" action="provider/disconnect" style="display:inline">
                    <?= form_close() ?>
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Disconnect Hetzner? Existing servers will not be affected.')">Disconnect</button>
                </form>
            </div>
        <?php else: ?>
            <div style="display:flex;gap:1.5rem;align-items:flex-start">
                <div style="flex:1">
                    <p style="font-size:.875rem;color:#475569;margin-bottom:.75rem">
                        Connect your Hetzner Cloud account to provision servers directly from Provision.
                        Your SSH public key will be automatically uploaded to Hetzner.
                    </p>
                    <div style="font-size:.82rem;color:#64748b">
                        <strong>How to get your API token:</strong>
                        <ol style="margin:.4rem 0 0 1.25rem;padding:0;line-height:1.8">
                            <li>Log into <a href="https://console.hetzner.cloud" target="_blank" rel="noopener" style="color:#6366f1">Hetzner Cloud Console</a></li>
                            <li>Open your project → <strong>Security</strong> → <strong>API Tokens</strong></li>
                            <li>Generate a token with <strong>Read &amp; Write</strong> permissions</li>
                        </ol>
                    </div>
                </div>
                <?php if (empty($customer_has_ssh_key ?? false)): ?>
                    <div style="background:#fef9c3;border:1px solid #fde047;border-radius:.375rem;padding:.75rem 1rem;font-size:.82rem;color:#854d0e;max-width:260px">
                        &#9888; Add an SSH public key in <a href="customer-onboarding/ssh_key" style="color:#92400e;font-weight:600">your account settings</a> first so it can be uploaded to Hetzner.
                    </div>
                <?php endif; ?>
            </div>
            <div style="margin-top:1rem">
                <a href="provider/connect" class="btn btn-primary">Connect Hetzner Cloud</a>
            </div>
        <?php endif; ?>
    </div>
</div>
