'use strict';

function startDeploy(id) {
    const btn = document.getElementById('deploy-btn');
    const panel = document.getElementById('live-log-panel');
    const log = document.getElementById('live-log');
    const badge = document.getElementById('live-status-badge');

    if (!btn || !panel || !log || !badge) return;

    btn.disabled = true;
    btn.textContent = 'Deploying...';
    log.textContent = '';
    panel.style.display = 'block';
    panel.querySelector('.card-title').textContent = 'Deploying...';
    badge.innerHTML = '<span class="badge badge-running">running</span>';
    requestAnimationFrame(function () {
        panel.scrollIntoView({behavior: 'smooth', block: 'start'});
    });

    const es = new EventSource(btn.dataset.streamUrl || ('deployment/stream/' + id));

    es.onmessage = function (event) {
        log.textContent += event.data + '\n';
        log.scrollTop = log.scrollHeight;
    };

    es.addEventListener('done', function (event) {
        es.close();
        const result = JSON.parse(event.data);
        const ok = result.status === 'success';

        panel.querySelector('.card-title').textContent = ok ? 'Deploy complete' : 'Deploy failed';
        badge.innerHTML = ok
            ? '<span class="badge badge-active">success</span>'
            : '<span class="badge badge-failed">failed</span>';

        const statusBadge = document.querySelector('.detail-item .badge[class*="badge-"]');
        if (statusBadge) {
            statusBadge.className = 'badge badge-' + (ok ? 'active' : 'failed');
            statusBadge.textContent = result.status;
        }

        btn.disabled = false;
        btn.textContent = 'Re-deploy';

        if (ok && result.sha) {
            log.textContent += '\nSHA: ' + result.sha + '\n';
            log.scrollTop = log.scrollHeight;
        }
    });

    es.onerror = function () {
        if (es.readyState === EventSource.CLOSED) return;
        es.close();
        log.textContent += '\n[connection closed]\n';
        btn.disabled = false;
        btn.textContent = 'Re-deploy';
    };
}
