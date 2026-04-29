'use strict';

function toggleSource(type) {
    const git = document.getElementById('src-git-fields');
    const zip = document.getElementById('src-zip-fields');
    if (git) git.style.display = type === 'git' ? '' : 'none';
    if (zip) zip.style.display = type === 'zip' ? '' : 'none';
}

function autoDbName(name) {
    const db = document.getElementById('env-db-name');
    if (!db || db._edited) return;
    db.value = name.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
}

document.addEventListener('DOMContentLoaded', function () {
    const db = document.getElementById('env-db-name');
    if (db) {
        db.addEventListener('input', function () { this._edited = this.value !== ''; });
    }

    const checked = document.querySelector('input[name=source_type]:checked');
    toggleSource(checked ? checked.value : 'git');
});
