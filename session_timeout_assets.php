<?php
if (defined('SESSION_TIMEOUT_ASSETS_LOADED')) {
    return;
}

define('SESSION_TIMEOUT_ASSETS_LOADED', true);

if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 900);
}

$sessionWarningBefore = 60;
$logoutToken = $_SESSION['csrf_token'] ?? '';
?>
<style>
    .session-timeout-overlay {
        position: fixed;
        inset: 0;
        z-index: 5000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.55);
    }
    .session-timeout-overlay.is-visible {
        display: flex;
    }
    .session-timeout-modal {
        width: min(100%, 460px);
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
        overflow: hidden;
        color: #0f172a;
    }
    .session-timeout-header {
        padding: 1.25rem 1.5rem 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .session-timeout-header h2 {
        margin: 0;
        color: var(--primary-color, #1e3a8a);
        font-size: 1.2rem;
        line-height: 1.3;
    }
    .session-timeout-body {
        padding: 1rem 1.5rem;
        color: #334155;
        line-height: 1.5;
    }
    .session-timeout-countdown {
        margin-top: 0.75rem;
        font-weight: 700;
        color: #991b1b;
    }
    .session-timeout-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1rem 1.5rem 1.25rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }
    .session-timeout-btn {
        min-height: 40px;
        padding: 0.65rem 1rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 700;
        background: #ffffff;
        color: #334155;
    }
    .session-timeout-btn:hover {
        background: #f1f5f9;
    }
    .session-timeout-btn-primary {
        border-color: var(--secondary-color, #2563eb);
        background: var(--secondary-color, #2563eb);
        color: #ffffff;
    }
    .session-timeout-btn-primary:hover {
        background: #1d4ed8;
    }
</style>

<div class="session-timeout-overlay" id="session-timeout-modal" role="dialog" aria-modal="true" aria-labelledby="session-timeout-title" aria-hidden="true">
    <div class="session-timeout-modal">
        <div class="session-timeout-header">
            <h2 id="session-timeout-title">Session bientôt expirée</h2>
        </div>
        <div class="session-timeout-body">
            <p>Votre session va expirer dans 1 minute. Souhaitez-vous rester connecté ?</p>
            <p class="session-timeout-countdown">Temps restant : <span id="session-timeout-countdown">60</span>s</p>
        </div>
        <div class="session-timeout-actions">
            <button type="button" class="session-timeout-btn" id="session-timeout-logout">Se déconnecter</button>
            <button type="button" class="session-timeout-btn session-timeout-btn-primary" id="session-timeout-extend">Rester connecté</button>
        </div>
    </div>
</div>

<form id="session-timeout-logout-form" method="POST" action="/payment/logout.php" hidden>
    <input type="hidden" name="logout_token" value="<?= htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="expired" id="session-timeout-expired-field" value="0">
</form>

<script>
    window.ULTSessionTimeout = {
        timeoutSeconds: <?= (int) SESSION_TIMEOUT ?>,
        warningBeforeSeconds: <?= (int) $sessionWarningBefore ?>,
        extendUrl: '/payment/extend_session.php',
        expiredRedirectUrl: '/payment?expired=1'
    };
</script>
<script src="/payment/session-timeout.js?v=1.0" defer></script>
