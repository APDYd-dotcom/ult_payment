<!-- 1. APP BAR MOBILE -->
<header class="mobile-appbar">
    <button class="menu-toggle" id="menu-toggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="appbar-title" id="appbar-title">Tableau de bord</div>
    <div class="appbar-actions">
        <button type="button" class="btn-notification" id="appbar-bell" aria-label="Notifications" aria-expanded="false" aria-controls="notification-menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span class="notification-badge" id="notification-badge-mobile">0</span>
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
    
    <button type="button" class="notification-toggle" id="notification-toggle" aria-expanded="false" aria-controls="notification-menu">
        <span>Notifications</span>
        <span class="notification-badge" id="notification-badge">0</span>
    </button>
    
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="student.php">Étudiants</a></li>
        <li><a href="departement.php">Départements</a></li>
        <li><a href="payment.php">Paiements</a></li>
        <li><a href="partial.php">Paiements partiels</a></li>
        <li><a href="penalty.php">Pénalités</a></li>
        <li><a href="documents.php">Documents</a></li>
        <li><a href="reports.php">Rapports</a></li>
        <li><a href="manage_users.php">Gestion utilisateurs</a></li>
        <li><a href="activity_log.php">Journal d'activité</a></li>
        <li><a href="login_history.php">Historique connexions</a></li>
        <li><a href="system_settings.php">Paramètres</a></li>
        <li><a href="backups.php">Sauvegardes</a></li>
        <li><a href="profile.php">Mon profil</a></li>
        
        <!-- BOUTON DE DECONNEXION -->
        <li>
            <form method="POST" action="/payment/logout.php">
                <input type="hidden" name="logout_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="logout-btn">Déconnexion</button>
            </form>
        </li>
    </ul>
</aside>

<!-- Panneau de notifications -->
<?php include_once __DIR__ . '/../notification_assets.php'; ?>

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
