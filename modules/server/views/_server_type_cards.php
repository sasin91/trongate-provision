<?php foreach ($server_types as $t): ?>
    <label class="type-card">
        <input type="radio" name="server_type" value="<?= htmlspecialchars($t['id']) ?>">
        <div class="type-name"><?= htmlspecialchars($t['name']) ?></div>
        <div class="type-spec">
            <?= (int)$t['vcpus'] ?> vCPU &middot; <?= round($t['memory'] / 1024, 0) ?> GB RAM &middot; <?= (int)$t['disk'] ?> GB disk
        </div>
        <div class="type-price">
            &euro;<?= number_format((float)$t['price_monthly'], 2) ?>/mo
        </div>
    </label>
<?php endforeach; ?>
