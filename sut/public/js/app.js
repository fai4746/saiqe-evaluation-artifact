// Agile1Tech FCCSMS — Application JavaScript

const BASE_URL = document.querySelector('meta[name="base-url"]')?.content || '';

// ── Notification badge poller ──────────────────
(function pollNotifications() {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;

    function refresh() {
        fetch(BASE_URL + '/notifications/count', { credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                if (data.unread > 0) {
                    badge.textContent = data.unread > 99 ? '99+' : data.unread;
                    badge.style.display = '';
                    // Pulse the bell icon
                    const bell = badge.previousElementSibling;
                    if (bell) { bell.classList.add('text-warning'); }
                } else {
                    badge.style.display = 'none';
                    const bell = badge.previousElementSibling;
                    if (bell) bell.classList.remove('text-warning');
                }
            })
            .catch(() => {});
    }

    setInterval(refresh, 60000);
})();

// ── Auto-dismiss alerts after 5 s ─────────────
document.querySelectorAll('.alert:not([aria-live])').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    }, 5000);
});

// ── Confirm destructive forms ─────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('submit', function(e) {
        if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});

// ── Table row click ripple ────────────────────
document.querySelectorAll('.table-clickable tbody tr[onclick]').forEach(row => {
    row.style.cursor = 'pointer';
});

// ── Toast helper ──────────────────────────────
window.showToast = function(msg, type = 'success') {
    const wrap = document.getElementById('toastContainer');
    if (!wrap) return;
    const id = 'toast_' + Date.now();
    wrap.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center text-white border-0 bg-${type === 'success' ? 'success' : 'danger'}" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    new bootstrap.Toast(document.getElementById(id), { delay: 3500 }).show();
};
