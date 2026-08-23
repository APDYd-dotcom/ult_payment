<?php
if (defined('NOTIFICATION_ASSETS_LOADED')) {
    return;
}

define('NOTIFICATION_ASSETS_LOADED', true);

$notificationCsrfToken = $_SESSION['csrf_token'] ?? '';
?>
<style>
    .notification-widget {
        position: relative;
        margin: 0 0 18px;
    }
    .notification-toggle {
        position: relative;
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid rgba(255, 255, 255, 0.16);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
        cursor: pointer;
        font-weight: 700;
    }
    .notification-toggle:hover {
        background: rgba(255, 255, 255, 0.14);
    }
    .notification-bell {
        font-size: 1.2rem;
        line-height: 1;
    }
    .notification-badge {
        min-width: 24px;
        height: 24px;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0 7px;
        border-radius: 999px;
        background: #dc2626;
        color: #ffffff;
        font-size: 0.78rem;
        font-weight: 800;
    }
    .notification-badge.is-visible {
        display: inline-flex;
    }
    .notification-menu {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 8px);
        width: 100%;
        max-height: 460px;
        display: none;
        flex-direction: column;
        z-index: 6000;
        overflow: hidden;
        border-radius: 10px;
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 22px 60px rgba(15, 23, 42, 0.28);
        border: 1px solid #e2e8f0;
    }
    .notification-menu.is-open {
        display: flex;
    }
    .notification-menu-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid #e2e8f0;
    }
    .notification-menu-title {
        font-size: 0.98rem;
        font-weight: 800;
        color: var(--primary-color, #1e3a8a);
    }
    .notification-mark-all {
        border: 0;
        background: transparent;
        color: var(--secondary-color, #2563eb);
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 4px 0;
    }
    .notification-list {
        max-height: 360px;
        overflow-y: auto;
    }
    .notification-item {
        width: 100%;
        display: block;
        padding: 12px 14px;
        border: 0;
        border-bottom: 1px solid #eef2f7;
        background: #ffffff;
        color: inherit;
        text-align: left;
        cursor: pointer;
    }
    .notification-item:hover {
        background: #f8fafc;
    }
    .notification-item.is-unread {
        background: #eff6ff;
    }
    .notification-item-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
        font-weight: 800;
        color: #0f172a;
    }
    .notification-unread-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--secondary-color, #2563eb);
        flex: 0 0 auto;
    }
    .notification-item-message {
        color: #475569;
        font-size: 0.88rem;
        line-height: 1.35;
        white-space: normal;
    }
    .notification-item-date {
        margin-top: 6px;
        color: #64748b;
        font-size: 0.78rem;
    }
    .notification-empty,
    .notification-error {
        padding: 16px 14px;
        color: #64748b;
        font-size: 0.9rem;
    }
    @media (max-width: 768px) {
        .notification-menu {
            position: fixed;
            top: 78px;
            left: 16px;
            right: 16px;
            width: auto;
        }
    }
</style>

<div class="notification-widget" id="notification-widget">
    <button type="button" class="notification-toggle" id="notification-toggle" aria-expanded="false" aria-controls="notification-menu">
        <span><span class="notification-bell" aria-hidden="true">🔔</span> Notifications</span>
        <span class="notification-badge" id="notification-badge">0</span>
    </button>
    <div class="notification-menu" id="notification-menu">
        <div class="notification-menu-header">
            <span class="notification-menu-title">Notifications</span>
            <button type="button" class="notification-mark-all" id="notification-mark-all">Tout marquer comme lu</button>
        </div>
        <div class="notification-list" id="notification-list">
            <div class="notification-empty">Chargement...</div>
        </div>
    </div>
</div>

<script>
    window.ULTNotifications = {
        csrfToken: <?= json_encode($notificationCsrfToken) ?>,
        getUrl: '/payment/get_notifications.php',
        markUrl: '/payment/mark_notification_read.php',
        markAllUrl: '/payment/mark_all_read.php',
        pollIntervalMs: 30000
    };
</script>
<script src="/payment/notifications.js?v=1.0" defer></script>
