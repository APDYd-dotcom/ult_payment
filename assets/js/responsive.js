// ULT Payment System — navigation mobile (drawer / plein écran)
(function () {
    'use strict';

    var BREAKPOINT = 1024;
    var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

    var sidebar  = document.querySelector('.sidebar');
    var toggle   = document.querySelector('.menu-toggle');
    var overlay  = document.querySelector('.overlay');
    var closeBtn = document.querySelector('.sidebar-close');
    var root     = document.documentElement;
    var lastFocus = null;

    if (!sidebar) { return; }
    if (!sidebar.id) { sidebar.id = 'sidebar'; }

    function isOpen() {
        return sidebar.classList.contains('open');
    }

    function openMenu() {
        if (isOpen()) { return; }
        lastFocus = document.activeElement;
        sidebar.classList.add('open');
        if (overlay) { overlay.classList.add('active'); }
        root.classList.add('menu-open');
        if (toggle) { toggle.setAttribute('aria-expanded', 'true'); }
        (closeBtn || sidebar.querySelector(FOCUSABLE) || sidebar).focus();
    }

    function closeMenu(restoreFocus) {
        if (!isOpen()) { return; }
        sidebar.classList.remove('open');
        if (overlay) { overlay.classList.remove('active'); }
        root.classList.remove('menu-open');
        if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
        if (restoreFocus !== false && lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }

    if (toggle) {
        toggle.setAttribute('aria-controls', sidebar.id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (isOpen()) { closeMenu(); } else { openMenu(); }
        });
    }
    if (closeBtn) { closeBtn.addEventListener('click', function () { closeMenu(); }); }
    if (overlay)  { overlay.addEventListener('click', function () { closeMenu(); }); }

    sidebar.addEventListener('click', function (e) {
        if (e.target.closest('a') && window.innerWidth <= BREAKPOINT) { closeMenu(false); }
    });

    document.addEventListener('keydown', function (e) {
        if (!isOpen()) { return; }
        if (e.key === 'Escape') { closeMenu(); return; }
        if (e.key !== 'Tab') { return; }

        var items = Array.prototype.filter.call(sidebar.querySelectorAll(FOCUSABLE), function (el) {
            return el.offsetWidth > 0 || el.offsetHeight > 0;
        });
        if (!items.length) { return; }
        var first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { last.focus(); e.preventDefault(); }
        else if (!e.shiftKey && document.activeElement === last) { first.focus(); e.preventDefault(); }
    });

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > BREAKPOINT) { closeMenu(false); }
        }, 150);
    });
})();


document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menu-toggle');
    const closeSidebar = document.getElementById('close-sidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (menuToggle && sidebar && overlay) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        const closeMenu = function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        };

        if (closeSidebar) {
            closeSidebar.addEventListener('click', closeMenu);
        }
        overlay.addEventListener('click', closeMenu);
    }
});
