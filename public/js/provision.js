'use strict';

// Generic copy-to-clipboard for code blocks and textareas
function copyScript(elementId) {
    const el = document.getElementById(elementId);
    const text = el.tagName === 'TEXTAREA' ? el.value : el.innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target;
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = orig, 2000);
    });
}

// Deployment create — canary weight toggle
function toggleCanary(checked) {
    const group = document.getElementById('canary-weight-group');
    if (group) group.style.display = checked ? '' : 'none';
}

// Auto-refresh countdown — reads from <span id="countdown" data-secs="10">
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('countdown');
    if (!el || el.dataset.secs === undefined) return;
    let secs = parseInt(el.dataset.secs, 10) || 10;
    el.textContent = '(' + secs + 's)';
    setInterval(() => {
        secs--;
        el.textContent = '(' + secs + 's)';
        if (secs <= 0) location.reload();
    }, 1000);
});
