'use strict';

function selectCard(value) {
    document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('selected'));
    const card = document.getElementById('card-' + value);
    if (card) card.classList.add('selected');
    const radio = document.querySelector('input[value="' + value + '"]');
    if (radio) radio.checked = true;
    const btn = document.getElementById('continue-btn');
    if (!btn) return;
    btn.disabled = false;
    btn.textContent = value === 'hetzner' ? 'Continue with Hetzner →' : 'Continue with Manual →';
}
