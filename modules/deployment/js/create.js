'use strict';

function toggleSource(type) {
    const git = document.getElementById('src-git-fields');
    const zip = document.getElementById('src-zip-fields');
    if (git) git.style.display = type === 'git' ? '' : 'none';
    if (zip) zip.style.display = type === 'zip' ? '' : 'none';
}

function toggleCanary(on) {
    const group = document.getElementById('canary-weight-group');
    if (group) group.style.display = on ? '' : 'none';
}

function applyEnvLock(sel) {
    const opt = sel.options[sel.selectedIndex];
    const locked = parseInt(opt.dataset.lockedServer || '0', 10);
    const name = opt.dataset.lockedName || '';
    const serverSelect = document.getElementById('server-select');
    const hint = document.getElementById('server-lock-hint');

    if (locked) {
        for (let i = 0; i < serverSelect.options.length; i++) {
            if (parseInt(serverSelect.options[i].value, 10) === locked) {
                serverSelect.selectedIndex = i;
                break;
            }
        }
        serverSelect.onchange = event => {
            event.preventDefault();
        };
        hint.textContent = 'Locked to "' + name + '"';
        hint.style.display = '';
    } else {
        serverSelect.onchange = undefined;
        hint.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const envSelect = document.getElementById('env-select');
    if (envSelect && envSelect.value) applyEnvLock(envSelect);

    const checked = document.querySelector('input[name=source_type]:checked');
    toggleSource(checked ? checked.value : 'git');
});
