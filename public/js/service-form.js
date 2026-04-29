'use strict';

function applyDefaults(type) {
    const sel  = document.getElementById('svc-type');
    const port = document.getElementById('svc-port');
    if (!sel || !port) return;
    const ports = JSON.parse(sel.dataset.ports || '{}');
    const val   = ports[type] ?? 0;
    if (val > 0) port.value = val;
}
