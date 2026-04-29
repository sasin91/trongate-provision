<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="deployment">Deployments</a>
            <span class="breadcrumb-sep">/</span>
            <a href="deployment/show/<?= $deployment->id ?>">Deployment #<?= $deployment->id ?></a>
            <span class="breadcrumb-sep">/</span>
            Timeline
        </div>
        <div class="page-title">Timeline — Deployment #<?= $deployment->id ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Event History</span>
        <span style="font-size:.78rem;color:#64748b"><?= count($events) ?> event(s)</span>
    </div>
    <div class="card-body" style="padding:0 1.25rem">
        <?php $viewer_customer_id = $viewer_customer_id ?? 0; include __DIR__ . '/timeline.php'; ?>
    </div>
</div>
