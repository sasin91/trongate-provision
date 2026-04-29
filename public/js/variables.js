'use strict';

const VAR_ROW = `
<div class="var-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:.5rem;margin-bottom:.5rem;align-items:center">
    <input type="text" name="keys[]" class="form-control" placeholder="VARIABLE_NAME" style="font-family:monospace;font-size:.85rem">
    <div style="position:relative">
        <input type="password" name="values[]" class="form-control val-input" placeholder="value" autocomplete="off" style="padding-right:2.5rem">
        <button type="button" onclick="toggleVal(this)" style="position:absolute;right:.5rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.8rem" title="Show/hide">&#128065;</button>
    </div>
    <button type="button" onclick="this.closest('.var-row').remove()" class="btn btn-danger btn-sm" style="width:2rem;justify-content:center">&#10005;</button>
</div>`;

function addRow() {
    const rows = document.getElementById('vars-rows');
    if (!rows) return;
    rows.insertAdjacentHTML('beforeend', VAR_ROW);
    rows.querySelector('.var-row:last-child input[name="keys[]"]').focus();
}

function toggleVal(btn) {
    const inp = btn.previousElementSibling;
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

document.addEventListener('DOMContentLoaded', function () {
    const rows = document.getElementById('vars-rows');
    if (rows && rows.dataset.empty === '1') addRow();
});
