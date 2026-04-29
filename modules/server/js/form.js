'use strict';

function syncShared() {
    const env  = document.getElementById('shared-env').value;
    const name = document.getElementById('shared-name').value;
    ['hz-env', 'imp-env', 'm-env'].forEach(id => { const el = document.getElementById(id); if (el) el.value = env; });
    ['hz-name', 'imp-name', 'm-name'].forEach(id => { const el = document.getElementById(id); if (el) el.value = name; });
}

function switchTab(tab) {
    ['hetzner', 'import', 'manual'].forEach(t => {
        const el = document.getElementById('tab-' + t);
        if (el) el.style.display = (t === tab) ? '' : 'none';
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });

    syncShared();
}

function markSelected(radio) {
    document.querySelectorAll('.type-card').forEach(c => {
        c.classList.remove('selected');
    });
    const card = radio.closest('.type-card');
    if (card) {
        card.classList.add('selected');
    }
}

function markImportSelected(radio) {
    document.querySelectorAll('.import-card').forEach(c => {
        c.classList.remove('selected');
    });
    const card = radio.closest('.import-card');
    if (card) {
        card.classList.add('selected');
    }
    const nameInp = document.getElementById('shared-name');
    if (nameInp && !nameInp.value) {
        nameInp.value = radio.dataset.name || '';
        syncShared();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const envSel  = document.getElementById('shared-env');
    const nameInp = document.getElementById('shared-name');
    if (envSel)  envSel.addEventListener('change', syncShared);
    if (nameInp) nameInp.addEventListener('input', syncShared);

    document.addEventListener('change', event => {
        const serverTypeRadio = event.target.closest('.type-card input[type="radio"]');
        if (serverTypeRadio) {
            markSelected(serverTypeRadio);
            return;
        }

        const importRadio = event.target.closest('.import-card input[type="radio"]');
        if (importRadio) {
            markImportSelected(importRadio);
        }
    });
});
