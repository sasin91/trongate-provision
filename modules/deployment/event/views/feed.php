<div class="page-header">
    <div class="page-header-left">
        <div class="page-title">Activity Feed</div>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-body" style="padding:.6rem 1.25rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
        <span style="font-size:.8rem;color:#64748b">Filter:</span>
        <?php
        $filters = ['' => 'All', 'deployment' => 'Deployments', 'server' => 'Servers', 'service' => 'Services', 'environment' => 'Environments', 'customer' => 'Auth'];
        foreach ($filters as $val => $label):
            $active = $active_filter === $val;
        ?>
        <a href="event/feed<?= $val !== '' ? '?filter=' . $val : '' ?>"
           class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-secondary' ?>"
           style="padding:.2rem .6rem;font-size:.78rem"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Events</span>
        <span style="font-size:.78rem;color:#64748b"><?= count($events) ?> shown</span>
    </div>
    <div class="card-body" style="padding:0 1.25rem">
        <?php include __DIR__ . '/timeline.php'; ?>
    </div>
</div>
