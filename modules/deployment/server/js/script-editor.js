'use strict';

function updateVarRef(type) {
    const deploy = document.getElementById('deploy-vars');
    const lamp   = document.getElementById('lamp-vars');
    if (deploy) deploy.style.display = type === 'deploy' ? '' : 'none';
    if (lamp)   lamp.style.display   = type === 'lamp'   ? '' : 'none';
}

function insertVar(v) {
    const ta = document.querySelector('textarea[name="body"]');
    if (!ta) return;
    const start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.slice(0, start) + v + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + v.length;
    ta.focus();
}

document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('script-type');
    if (sel) updateVarRef(sel.value);
});
