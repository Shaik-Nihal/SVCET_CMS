/* ============================================================
   Notifications JS - Polling for real-time badge updates
   ============================================================ */

(function () {
    const POLL_INTERVAL = 30000; // 30 seconds
    const API_URL = document.querySelector('meta[name="app-url"]')?.content + '/api/get_notifications.php';

    const badge = document.getElementById('notif-badge');
    const dropdownMenu = document.getElementById('notif-dropdown-menu');

    function fetchNotifications() {
        if (!badge) return;

        fetch(API_URL + '?limit=5', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            // Update badge
            if (data.unread_count > 0) {
                badge.textContent  = data.unread_count > 99 ? '99+' : data.unread_count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }

            // Update dropdown items
            if (dropdownMenu) {
                const items = data.notifications || [];
                if (items.length === 0) {
                    dropdownMenu.innerHTML = '<li><span class="dropdown-item text-muted small">No new notifications</span></li>';
                } else {
                    dropdownMenu.innerHTML = items.map(n => {
                        const url = n.ticket_id
                            ? (document.body.dataset.userType === 'staff'
                                ? `staff/ticket_detail.php?id=${n.ticket_id}`
                                : `user/ticket_detail.php?id=${n.ticket_id}`)
                            : '#';
                        const cls = n.is_read ? '' : 'fw-semibold';
                        return `<li>
                            <a class="dropdown-item ${cls} small notif-item"
                               href="${url}" data-id="${n.id}" style="white-space:normal;max-width:280px;">
                                <div style="font-size:.82rem;">${escapeHtml(n.message)}</div>
                                <div class="text-muted" style="font-size:.72rem;">${n.time_ago}</div>
                            </a>
                        </li>`;
                    }).join('') + `<li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-center small text-primary" href="${document.querySelector('meta[name="app-url"]')?.content}/user/notifications.php">View all</a></li>`;
                }
            }
        })
        .catch(() => {}); // silent fail
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // Mark as read on click
    document.addEventListener('click', e => {
        const item = e.target.closest('.notif-item');
        if (!item) return;
        const id = item.dataset.id;
        if (id) {
            fetch(document.querySelector('meta[name="app-url"]')?.content + '/api/mark_notification_read.php?id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(() => {});
        }
    });

    // Initial fetch + polling
    fetchNotifications();
    setInterval(fetchNotifications, POLL_INTERVAL);
})();
