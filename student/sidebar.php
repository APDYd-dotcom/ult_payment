<button id="menu-toggle" class="menu-toggle" aria-label="Ouvrir le menu">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<div id="sidebar-overlay" class="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">
    <button id="close-sidebar" class="close-sidebar" aria-label="Fermer le menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="logo">
        <?php renderThemeLogo($bdd); ?>
    </div>
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

    <?php include_once __DIR__ . '/../notification_assets.php'; ?>
    <?php include_once __DIR__ . '/../session_timeout_assets.php'; ?>
</aside>
