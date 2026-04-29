<?php
// Partial: receives $events (array of event_log objects) and optional $customer_id
// Include this partial from deployment.php and feed.php views
$viewer_customer_id = $viewer_customer_id ?? 0;
?>
<?php if (empty($events)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#128203;</div>
        <div class="empty-title">No events yet</div>
        <div class="empty-desc">Actions on this resource will appear here as they happen.</div>
    </div>
<?php else: ?>
    <div class="timeline-list">
        <?php foreach ($events as $ev):
            $payload = json_decode($ev->payload ?? '{}', true) ?: [];
            $dot_color = Event::dot_color($ev->entity_type, $ev->event_type);
            $label     = Event::label_for($ev->event_type);
            $summary   = Event::payload_summary($ev->event_type, $payload);
            $rel_time  = Event::relative_time($ev->created_at);
            $actor     = ((int)$ev->customer_id === 0) ? 'System' : 'You';
        ?>
        <div class="timeline-item">
            <div class="timeline-dot" style="background:<?= $dot_color ?>"></div>
            <div class="timeline-body">
                <div class="timeline-head">
                    <span class="timeline-label"><?= $label ?></span>
                    <span class="timeline-meta">
                        <span class="timeline-actor"><?= $actor ?></span>
                        <span class="timeline-sep">·</span>
                        <span class="timeline-time" title="<?= htmlspecialchars($ev->created_at) ?>"><?= $rel_time ?></span>
                    </span>
                </div>
                <?php if ($summary !== ''): ?>
                    <div class="timeline-summary"><?= $summary ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.timeline-list { padding: .5rem 0; }
.timeline-item { display: flex; gap: .875rem; padding: .6rem 0; position: relative; }
.timeline-item:not(:last-child) { border-bottom: 1px solid #f1f5f9; }
.timeline-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: .35rem; flex-shrink: 0; }
.timeline-body { flex: 1; min-width: 0; }
.timeline-head { display: flex; align-items: baseline; gap: .5rem; flex-wrap: wrap; }
.timeline-label { font-size: .82rem; font-weight: 600; color: #1e293b; }
.timeline-meta { font-size: .75rem; color: #94a3b8; display: flex; align-items: center; gap: .3rem; margin-left: auto; white-space: nowrap; }
.timeline-actor { color: #64748b; }
.timeline-sep { color: #cbd5e1; }
.timeline-summary { font-size: .78rem; color: #64748b; margin-top: .15rem; word-break: break-all; }
</style>
