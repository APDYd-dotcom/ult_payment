# PHASE 0 AUDIT - ULT Payment System Responsive

Date: 2026-10-30
Branch: feature/responsive

## A. Menu mobile accessibility
- Menu button `.menu-toggle` exists only in: admin/payment.php, admin/reports.php, student/payment.php (3 pages have menu-toggle with inline onclick).
- Sidebar mobile behavior (`transform: translateX(-100%)`, `.sidebar.open`) defined in admin/styles.css and student/styles.css (both under media queries). 
- Many pages don't have `.menu-toggle` button - sidebar not accessible on mobile. Pages without menu-toggle include: admin/dashboard.php, admin/departement.php, admin/student.php, admin/partial.php, admin/penalty.php, admin/profile.php, admin/manage_users.php, admin/system_settings.php, admin/theme_settings.php, admin/mailing.php, admin/activity_log.php, admin/login_history.php, admin/reports.php has menu-toggle but let us check - admin/reports.php DOES have it (line 73 as shown). admin/payment.php has it. admin/partial.php - let us check.
- student pages: student/student.php, student/partial.php, student/penalty.php, student/profile.php, student/theme_settings.php etc may not have menu-toggle. student/payment.php has it.

Pages with menu-toggle: admin/payment.php, admin/reports.php, student/payment.php (3 total found). So yes, majority of pages don't have accessible mobile menu.

## B. Viewport meta tag
Pages WITH viewport (from grep): signup.php, forgot_password.php, student/student.php, student/theme_settings.php, student/payment.php, student/partial.php, student/penalty.php, index.php, reset_password.php, verify_2fa.php, admin/system_settings.php, admin/reports.php, admin/theme_settings.php, admin/manage_users.php, admin/payment.php, admin/partial.php, admin/dashboard.php, admin/penalty.php + others found. Total ~20+ pages have it.

Pages WITHOUT viewport (HTML files): admin/activity_log.php, admin/archives.php, admin/backups.php, admin/chat.php, admin/comparative_analysis.php, admin/custom_reports.php, admin/departement.php, admin/documents.php, admin/forecasting.php, admin/login_history.php, admin/mailing.php, admin/periodic_reports.php, admin/profile.php, admin/report_export.php, admin/restore.php, admin/share_document.php, admin/student.php, admin/upload_document.php, student/chat.php, student/documents.php, student/profile.php, student/upload_document.php (22 pages). Public pages like index.php, signup.php, forgot_password.php, reset_password.php, verify_2fa.php DO have viewport.

## C. Duplication
- admin/styles.css: 1135 lines
- student/styles.css: 1135 lines  
- Diff between them: only 1 line different (border-top color uses CSS variable in student version)
- Inline <style> blocks: 28 occurrences across admin/, student/, root PHP files (excludes report_templates)
- index.php has large inline styles (~160+ line style block starting at line ~160)

## D. Hamburger button
Uses ☰ character with onclick inline in admin/payment.php, admin/reports.php, student/payment.php. Needs replacement with accessible <button> with SVG icon, aria-label, aria-expanded, aria-controls.

## E. Heights
Uses 100vh in sidebar/styles (needs dvh with fallback).

## F. Tables
24 <table> occurrences in admin/student. Many tables in admin/payment.php, admin/student.php, admin/manage_users.php etc. Need to count columns per table - will inspect key ones. Tables with many columns (payment lists likely have 10+ columns). min-width:900px appears in styles.

## G. Breakpoints
Current breakpoints mixed (max-width 480, 768, 900, 992) - need consolidation to xs/sm/md/lg/xl/2xl as specified.

## H. Overflow sources
Notifications dropdown (notifications.js), modals, Chart.js in dashboard, QR codes in 2FA, multi-column forms, advanced filters, table action buttons, date inputs, theme logo/images.

## Security counts (baseline)
- csrf_field/csrf_token: 66 occurrences
- htmlspecialchars: 317 occurrences  
- require auth_check: 44 occurrences
- REQUIRED_ROLE: 39 occurrences

## Summary of key issues
- Mobile menu only on 3 pages (admin/payment.php, admin/reports.php, student/payment.php). Sidebar hidden via transform on mobile but no menu button/overlay on most pages.
- 22 HTML pages missing viewport meta tag (affecting mobile scaling/media queries).
- Styles are duplicated between admin/styles.css and student/styles.css (1135 lines each, 1 diff). 28 inline <style> blocks.
- Hamburger uses ☰ character with inline onclick, no accessibility attributes.
- Sidebar uses 100vh in places; need dvh support.
- 24 tables across admin/student, many need responsive handling.
- Breakpoints inconsistent (480, 640, 768, 900, 992 mixed). Need unified breakpoints.
- Notifications dropdown, modals, charts need responsive adjustments.

## Security baseline (preserved)
- csrf/csrf_token: 66
- htmlspecialchars: 317
- auth_check requires: 44
- REQUIRED_ROLE: 39

## Plan for Phase 1
1. Create layout_head.php with viewport, charset, theme-color, CSS includes - include on ALL pages with HTML
2. Consolidate styles: create assets/css/app.css from common parts, keep role-specific if needed, add assets/css/responsive.css
3. Create unified mobile navigation: topbar + sidebar behavior on all pages, add responsive.js with a11y (focus trap, ESC, aria, scroll lock)
4. Replace hamburger with accessible button + SVG, move menu-toggle/overlay into shared structure
5. Add viewport to all HTML pages missing it
6. Fix heights to use dvh with fallbacks
7. Global box-sizing, img max-width, prevent horizontal scroll
