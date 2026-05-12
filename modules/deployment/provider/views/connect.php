<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb"><a href="provider">Providers</a> <span class="breadcrumb-sep">/</span> Connect Hetzner</div>
        <div class="page-title">Connect Hetzner Cloud</div>
    </div>
</div>

<?php if (!empty($_SESSION['form_submission_errors'])): ?>
    <div class="alert alert-danger">
        <?php foreach ($_SESSION['form_submission_errors'] as $errs): ?>
            <?php foreach ((array) $errs as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
        <?php endforeach; unset($_SESSION['form_submission_errors']); ?>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:1.25rem;align-items:start">
    <div class="card">
        <div class="card-body">
            <form method="post" action="<?= $form_location ?>">
                <div class="form-group">
                    <label class="form-label">Hetzner API Token</label>
                    <input type="password" name="token" class="form-control"
                        placeholder="Paste your Hetzner Cloud API token"
                        autocomplete="off" required autofocus>
                    <span class="form-hint">Must have <strong>Read &amp; Write</strong> permissions. Token is encrypted at rest.</span>
                </div>

                <div class="form-actions">
                    <?= form_close() ?>
                    <button type="submit" class="btn btn-primary">Connect &amp; Upload SSH Key</button>
                    <a href="provider" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="position:sticky;top:4rem">
        <div class="card-header"><span class="card-title">What happens when you connect?</span></div>
        <div class="card-body" style="font-size:.82rem;color:#475569;line-height:1.7">
            <p>1. Your API token is <strong>validated</strong> against the Hetzner API.</p>
            <p>2. Your SSH public key is <strong>uploaded</strong> to Hetzner so your servers accept it immediately after provisioning.</p>
            <p>3. Your token is <strong>encrypted</strong> and stored. It is never logged or displayed in full.</p>
            <p style="margin-top:.75rem">After connecting, the <strong>Create Server</strong> page will offer a <em>Hetzner Cloud</em> tab where you can pick a plan and region and provision a real server instantly.</p>
        </div>
    </div>
</div>
