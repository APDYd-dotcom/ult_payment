(function () {
    'use strict';

    const config = window.ULTNotifications || {};
    const widget = document.getElementById('notification-widget');
    const toggle = document.getElementById('notification-toggle');
    const menu = document.getElementById('notification-menu');
    const badge = document.getElementById('notification-badge');
    const list = document.getElementById('notification-list');
    const markAllButton = document.getElementById('notification-mark-all');

    if (!widget || !toggle || !menu || !badge || !list || !markAllButton) {
        return;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value || '';
        }

        return date.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function setBadge(count) {
        const safeCount = Number(count || 0);
        badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
        badge.classList.toggle('is-visible', safeCount > 0);
    }

    function renderNotifications(notifications) {
        if (!notifications || notifications.length === 0) {
            list.innerHTML = '<div class="notification-empty">Aucune notification.</div>';
            return;
        }

        list.innerHTML = notifications.map(function (item) {
            const unread = Number(item.is_read) === 0;
            const dot = unread ? '<span class="notification-unread-dot" aria-hidden="true"></span>' : '';

            return [
                '<button type="button" class="notification-item', unread ? ' is-unread' : '', '" data-id="', Number(item.id), '" data-link="', escapeHtml(item.link || ''), '">',
                '<span class="notification-item-title">', dot, escapeHtml(item.title), '</span>',
                '<span class="notification-item-message">', escapeHtml(item.message), '</span>',
                '<span class="notification-item-date">', escapeHtml(formatDate(item.created_at)), '</span>',
                '</button>'
            ].join('');
        }).join('');
    }

    function handleExpired(data) {
        if (data && data.expired && data.redirect) {
            window.location.href = data.redirect;
            return true;
        }

        return false;
    }

    function loadNotifications() {
        return fetch(config.getUrl || '/payment/get_notifications.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (handleExpired(data)) {
                    return;
                }

                if (!data || !data.success) {
                    list.innerHTML = '<div class="notification-error">Impossible de charger les notifications.</div>';
                    return;
                }

                setBadge(data.unread_count);
                renderNotifications(data.notifications);
            })
            .catch(function () {
                list.innerHTML = '<div class="notification-error">Impossible de charger les notifications.</div>';
            });
    }

    function postForm(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        });
    }

    function markNotificationRead(id, link) {
        const body = new URLSearchParams();
        body.set('csrf_token', config.csrfToken || '');
        body.set('notification_id', String(id));

        postForm(config.markUrl || '/payment/mark_notification_read.php', body)
            .then(function (data) {
                if (handleExpired(data)) {
                    return;
                }

                if (link) {
                    window.location.href = link;
                    return;
                }

                loadNotifications();
            })
            .catch(function () {
                if (link) {
                    window.location.href = link;
                }
            });
    }

    function markAllRead() {
        const body = new URLSearchParams();
        body.set('csrf_token', config.csrfToken || '');

        postForm(config.markAllUrl || '/payment/mark_all_read.php', body)
            .then(function (data) {
                if (handleExpired(data)) {
                    return;
                }

                loadNotifications();
            })
            .catch(function () {});
    }

    function setMenuOpen(isOpen) {
        menu.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isOpen) {
            loadNotifications();
        }
    }

    toggle.addEventListener('click', function () {
        setMenuOpen(!menu.classList.contains('is-open'));
    });

    document.addEventListener('click', function (event) {
        if (!widget.contains(event.target)) {
            setMenuOpen(false);
        }
    });

    list.addEventListener('click', function (event) {
        const item = event.target.closest('.notification-item');
        if (!item) {
            return;
        }

        markNotificationRead(item.dataset.id, item.dataset.link || '');
    });

    markAllButton.addEventListener('click', markAllRead);

    loadNotifications();
    window.setInterval(loadNotifications, Number(config.pollIntervalMs || 30000));
})();
