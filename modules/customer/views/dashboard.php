<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Dashboard</div>
    </div>
    <a href="server/create" class="btn btn-primary">+ New Server</a>
</div>

<?php if (empty($servers)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">&#9646;</div>
            <div class="empty-title">No servers yet</div>
            <div class="empty-desc">
                Start by creating an <a href="environment" style="color:#6366f1">environment</a>,
                then <a href="server/create" style="color:#6366f1">add a server</a> and deploy.
            </div>
        </div>
    </div>
<?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem">
        <?php foreach ($servers as $s):
            $healthy   = (int) $s->healthy_count;
            $unhealthy = (int) $s->unhealthy_count;
            $total     = (int) $s->deploy_count;
            $checked   = $healthy + $unhealthy;

            if ($total === 0) {
                $health_color = '#94a3b8';
                $health_label = 'No deployments';
                $card_accent  = '';
            } elseif ($unhealthy > 0) {
                $health_color = '#b91c1c';
                $health_label = $unhealthy . ' unhealthy';
                $card_accent  = 'border-top:3px solid #ef4444';
            } elseif ($checked === 0) {
                $health_color = '#94a3b8';
                $health_label = 'Not checked';
                $card_accent  = '';
            } else {
                $health_color = '#15803d';
                $health_label = 'All healthy';
                $card_accent  = 'border-top:3px solid #22c55e';
            }
        ?>
        <div class="card" style="margin:0;display:flex;flex-direction:column;<?= $card_accent ?>">
            <div style="padding:1rem 1.25rem .5rem;display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                <a href="server/show/<?= $s->id ?>" style="font-weight:700;font-size:1rem;color:#0f172a;text-decoration:none;line-height:1.3">
                    <?= htmlspecialchars($s->name) ?>
                </a>
                <span class="badge badge-<?= htmlspecialchars($s->status) ?>" style="white-space:nowrap;flex-shrink:0">
                    <?= htmlspecialchars($s->status) ?>
                </span>
            </div>

            <div style="padding:.25rem 1.25rem .75rem;flex:1">
                <div style="font-size:.82rem;color:#64748b;margin-bottom:.35rem">
                    <span style="color:#94a3b8">env</span> <?= htmlspecialchars($s->env_name) ?>
                </div>
                <div style="font-size:.82rem;color:#64748b;margin-bottom:.35rem">
                    <code style="font-size:.78rem"><?= htmlspecialchars($s->ip_address) ?></code>
                    <?php if ($s->provider !== 'manual'): ?>
                        <span style="color:#94a3b8;margin-left:.35rem">&middot; <?= htmlspecialchars(ucfirst($s->provider)) ?>
                            <?php if (!empty($s->region)): ?>
                                (<?= htmlspecialchars($s->region) ?>)
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($total > 0): ?>
                <div style="font-size:.82rem;color:#64748b">
                    <?= $total ?> deployment<?= $total !== 1 ? 's' : '' ?>
                </div>
                <?php endif; ?>
            </div>

            <div style="padding:.75rem 1.25rem;border-top:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:.82rem;font-weight:600;color:<?= $health_color ?>">
                    &#9679; <?= $health_label ?>
                    <?php if ($s->last_checked_at): ?>
                        <span style="font-weight:400;color:#94a3b8;margin-left:.35rem"><?= date('M j H:i', strtotime($s->last_checked_at)) ?></span>
                    <?php endif; ?>
                </span>
                <a href="server/show/<?= $s->id ?>" class="btn btn-secondary btn-sm">View</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
