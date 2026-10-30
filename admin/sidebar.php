<!-- 1. APP BAR MOBILE -->
<header class="mobile-appbar">
    <button class="menu-toggle" id="menu-toggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="appbar-title" id="appbar-title">Tableau de bord</div>
    <div class="appbar-actions">
        <button class="btn-notification">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </button>
    </div>
</header>

<!-- 2. OVERLAY SOMBRE -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- 3. SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <button class="close-sidebar" id="close-sidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="logo"><h2>ULT Payment</h2></div>
    <ul>





    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="departement.php">Departement</a></li>
    <li><a href="student.php">Students</a></li>
    <li><a href="payment.php">Payments</a></li>
    <li><a href="reports.php">Rapports</a></li>
    <li><a href="documents.php">Documents</a></li>
    <li><a href="periodic_reports.php">Rapport periodique</a></li>
    <li><a href="custom_reports.php">Rapport personnalise</a></li>
    <li><a href="comparative_analysis.php">Analyse comparative</a></li>
    <li><a href="forecasting.php">Prevision</a></li>
    <li><a href="partial.php">Partial Payments</a></li>
    <li><a href="penalty.php">Penalties</a></li>
    <li><a href="login_history.php">Login History</a></li>
    <li><a href="manage_users.php">Manage Users</a></li>
    <li><a href="system_settings.php">System Settings</a></li>
    <li><a href="theme_settings.php">Theme Settings</a></li>
    <li><a href="mailing.php">Mailing List</a></li>
    <li><a href="activity_log.php">Activity Log</a></li>
    <li><a href="chat.php">Chat</a></li>
    <li><a href="archives.php">Archives</a></li>
    <li><a href="backups.php">Sauvegardes</a></li>
    <li><a href="restore.php">Restauration</a></li>
    <li><a href="profile.php">Profile</a></li>
    <li>
        <form method="POST" action="/payment/logout.php">
            <input type="hidden" name="logout_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit">Logout</button>
        </form>
    </li>

    
    
    
    
    </ul>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menu-toggle');
    const closeSidebar = document.getElementById('close-sidebar');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (menuToggle && sidebar && overlay) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        const closeMenu = () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        };

        if (closeSidebar) closeSidebar.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);
    }

    const pageTitleElement = document.querySelector('.page-title');
    const appbarTitleElement = document.getElementById('appbar-title');
    if (pageTitleElement && appbarTitleElement) {
        appbarTitleElement.textContent = pageTitleElement.textContent;
    }
});
</script>
