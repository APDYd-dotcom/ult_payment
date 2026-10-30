// ULT Payment System Responsive Navigation
(function() {
    'use strict';
    
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');
    let lastFocusedElement = null;
    
    function openMenu() {
        if (!sidebar) return;
        lastFocusedElement = document.activeElement;
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'true');
        }
        // Focus trap - focus first focusable element in sidebar
        const focusableElements = sidebar.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusableElements.length > 0) {
            focusableElements[0].focus();
        }
    }
    
    function closeMenu() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
        if (menuToggle) {
            menuToggle.setAttribute('aria-expanded', 'false');
            if (lastFocusedElement) {
                lastFocusedElement.focus();
            }
        }
    }
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (sidebar && sidebar.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeMenu);
    }
    
    // Close menu on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (sidebar && sidebar.classList.contains('open')) {
                closeMenu();
            }
        }
        
        // Focus trap
        if (e.key === 'Tab' && sidebar && sidebar.classList.contains('open')) {
            const focusableElements = Array.from(sidebar.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'))
                .filter(el => el.offsetWidth > 0 || el.offsetHeight > 0 || el === menuToggle);
            
            if (focusableElements.length === 0) return;
            
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            if (e.shiftKey) {
                if (document.activeElement === firstElement) {
                    lastElement.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === lastElement) {
                    firstElement.focus();
                    e.preventDefault();
                }
            }
        }
    });
    
    // Close menu when clicking on a link
    if (sidebar) {
        const links = sidebar.querySelectorAll('a');
        links.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    closeMenu();
                }
            });
        });
    }
    
    // Close menu on window resize to desktop
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 1024) {
                closeMenu();
            }
        }, 250);
    });
    
    // Initialize ARIA attributes
    if (menuToggle && !menuToggle.hasAttribute('aria-expanded')) {
        menuToggle.setAttribute('aria-expanded', 'false');
    }
    if (menuToggle && !menuToggle.hasAttribute('aria-label')) {
        menuToggle.setAttribute('aria-label', 'Menu');
    }
    if (menuToggle && !menuToggle.hasAttribute('aria-controls')) {
        menuToggle.setAttribute('aria-controls', 'sidebar');
    }
    if (sidebar && !sidebar.hasAttribute('id')) {
        sidebar.setAttribute('id', 'sidebar');
    }
})();
