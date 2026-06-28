(function () {
    'use strict';

    const config = window.ULTSessionTimeout || {};
    const timeoutSeconds = Number(config.timeoutSeconds || 900);
    const warningBeforeSeconds = Number(config.warningBeforeSeconds || 60);
    const extendUrl = config.extendUrl || '/payment/extend_session.php';
    const expiredRedirectUrl = config.expiredRedirectUrl || '/payment?expired=1';

    const modal = document.getElementById('session-timeout-modal');
    const countdown = document.getElementById('session-timeout-countdown');
    const stayButton = document.getElementById('session-timeout-extend');
    const logoutButton = document.getElementById('session-timeout-logout');
    const logoutForm = document.getElementById('session-timeout-logout-form');
    const expiredField = document.getElementById('session-timeout-expired-field');

    if (!modal || !countdown || !stayButton || !logoutButton || !logoutForm || timeoutSeconds <= 0) {
        return;
    }

    let deadline = Date.now() + timeoutSeconds * 1000;
    let modalVisible = false;
    let lastServerExtendAt = 0;
    let extendInProgress = false;
    const serverExtendThrottleMs = 60000;

    function secondsRemaining() {
        return Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    }

    function showModal() {
        if (modalVisible) {
            return;
        }

        modalVisible = true;
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        stayButton.focus();
    }

    function hideModal() {
        modalVisible = false;
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
    }

    function submitLogout(expired) {
        if (expiredField) {
            expiredField.value = expired ? '1' : '0';
        }

        logoutForm.submit();
    }

    function resetClientTimer() {
        deadline = Date.now() + timeoutSeconds * 1000;
        hideModal();
    }

    function extendServerSession() {
        if (extendInProgress) {
            return Promise.resolve(false);
        }

        extendInProgress = true;

        return fetch(extendUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () {
                        return { success: false };
                    });
                }

                return response.json();
            })
            .then(function (data) {
                if (data && data.success) {
                    lastServerExtendAt = Date.now();
                    resetClientTimer();
                    return true;
                }

                if (data && data.expired && data.redirect) {
                    window.location.href = data.redirect;
                }

                return false;
            })
            .catch(function () {
                return false;
            })
            .finally(function () {
                extendInProgress = false;
            });
    }

    function handleActivity() {
        if (modalVisible) {
            return;
        }

        resetClientTimer();

        if (Date.now() - lastServerExtendAt >= serverExtendThrottleMs) {
            extendServerSession();
        }
    }

    function tick() {
        const remaining = secondsRemaining();

        countdown.textContent = String(Math.min(remaining, warningBeforeSeconds));

        if (remaining <= 0) {
            submitLogout(true);
            return;
        }

        if (remaining <= warningBeforeSeconds) {
            showModal();
        }

        window.setTimeout(tick, 1000);
    }

    ['mousemove', 'keydown', 'click', 'scroll'].forEach(function (eventName) {
        window.addEventListener(eventName, handleActivity, { passive: true });
    });

    stayButton.addEventListener('click', function () {
        extendServerSession();
    });

    logoutButton.addEventListener('click', function () {
        submitLogout(false);
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !modalVisible) {
            handleActivity();
        }
    });

    lastServerExtendAt = Date.now();
    tick();
})();
