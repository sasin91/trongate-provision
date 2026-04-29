<div class="page-header">
    <div class="page-header-left">
        <div class="breadcrumb">
            <a href="environment">Environments</a>
            <span class="breadcrumb-sep">/</span>
            <a href="environment/show/<?= $env->id ?>"><?= htmlspecialchars($env->name) ?></a>
            <span class="breadcrumb-sep">/</span>
            Variables
        </div>
        <div class="page-title">Environment Variables</div>
    </div>
</div>

<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem;padding:.75rem 1rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:.375rem;font-size:.82rem;color:#15803d">
    &#128274; Variables are encrypted at rest with AES-256-CBC. They are injected as <code>export</code> statements into your deployment scripts.
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Key–Value Pairs</span>
        <button onclick="addRow()" class="btn btn-secondary btn-sm">+ Add Variable</button>
    </div>
    <div class="card-body">
        <form method="post" action="<?= $form_location ?>" id="vars-form">
            <div id="vars-table">
                <div class="vars-header" style="display:grid;grid-template-columns:1fr 1fr auto;gap:.5rem;padding:.5rem 0;border-bottom:1px solid #e2e8f0;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:.5rem">
                    <span>Key</span><span>Value</span><span></span>
                </div>
                <div id="vars-rows" data-empty="<?= empty($variables) ? '1' : '0' ?>">
                    <?php if (empty($variables)): ?>
                        <!-- empty start row added by JS -->
                    <?php else: ?>
                        <?php foreach ($variables as $k => $v): ?>
                        <div class="var-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:.5rem;margin-bottom:.5rem;align-items:center">
                            <input type="text" name="keys[]" class="form-control" value="<?= htmlspecialchars($k) ?>" placeholder="VARIABLE_NAME" style="font-family:monospace;font-size:.85rem">
                            <div style="position:relative">
                                <input type="password" name="values[]" class="form-control val-input" value="<?= htmlspecialchars($v) ?>" placeholder="value" autocomplete="off" style="padding-right:2.5rem">
                                <button type="button" onclick="toggleVal(this)" style="position:absolute;right:.5rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.8rem" title="Show/hide">&#128065;</button>
                            </div>
                            <button type="button" onclick="this.closest('.var-row').remove()" class="btn btn-danger btn-sm" style="width:2rem;justify-content:center">&#10005;</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions" style="margin-top:1rem">
                <?= form_close() ?>
                <button type="submit" form="vars-form" class="btn btn-primary">Save Variables</button>
                <a href="environment/show/<?= $env->id ?>" class="btn btn-secondary">Cancel</a>
                <span style="font-size:.8rem;color:#94a3b8;margin-left:auto">Changes are encrypted before saving</span>
            </div>
        </form>
    </div>
</div>

<script src="js/variables.js"></script>
