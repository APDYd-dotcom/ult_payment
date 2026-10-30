<!-- 1. APP BAR MOBILE (Cachée sur PC) -->
<header class="mobile-appbar">
    <button class="menu-toggle" id="menu-toggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="appbar-title">Tableau de bord</div>
    <div class="appbar-actions">
        <button class="btn-notification">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <span class="notification-badge">1</span>
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



    <li><a href="student.php">Mon dossier</a></li>
    <li><a href="payment.php">Mes paiements</a></li>
    <li><a href="documents.php">Mes documents</a></li>
    <li><a href="partial.php">Paiements partiels</a></li>
    <li><a href="penalty.php">Mes penalites</a></li>
    <li><a href="theme_settings.php">Theme Settings</a></li>
    <li><a href="profile.php">Compte</a></li>
    <li><a href="chat.php">Chat</a></li>
    <li>
        <form method="POST" action="/payment/logout.php">
            <input type="hidden" name="logout_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Logout</button>
        </form>
    </li>

    
    
    </ul>
</aside>
