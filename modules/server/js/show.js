'use strict';

function startProvision(id) {
    const btn = document.getElementById('provision-btn');
    const panel = document.getElementById('provision-log-panel');
    const log = document.getElementById('provision-log');
    const title = document.getElementById('provision-log-title');
    const badge = document.getElementById('provision-status-badge');

    btn.disabled = true;
    btn.textContent = 'Provisioning...';
    log.textContent = '';
    panel.style.display = '';
    panel.scrollIntoView({behavior: 'smooth', block: 'start'});

    const es = new EventSource(btn.dataset.streamUrl || ('server/stream/' + id));

    es.onmessage = function (event) {
        log.textContent += event.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('done', function (event) {
        es.close();
        const result = JSON.parse(event.data);
        const ok = result.status === 'active';

        title.textContent = ok ? 'Provisioning complete' : 'Provisioning failed';
        badge.innerHTML = ok
            ? '<span class="badge badge-active">active</span>'
            : '<span class="badge badge-failed">failed</span>';

        const statusBadge = document.querySelector('.detail-item .badge');
        if (statusBadge) {
            statusBadge.className = 'badge badge-' + (ok ? 'active' : 'failed');
            statusBadge.textContent = result.status;
        }

        btn.textContent = 'Re-provision';
        btn.disabled = false;
    });

    es.onerror = function () {
        if (es.readyState === EventSource.CLOSED) return;
        es.close();
        log.textContent += '\n[connection closed]\n';
        btn.disabled = false;
        btn.textContent = 'Re-provision';
    };
}
