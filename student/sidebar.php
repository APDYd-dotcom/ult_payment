<?php
require_once __DIR__ . '/auth_check.php';
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<div class="sidebar-head">
    <?php renderThemeLogo($bdd); ?>
    <button type="button" class="sidebar-close" aria-label="Fermer le menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="5" y1="5" x2="19" y2="19"></line>
            <line x1="19" y1="5" x2="5" y2="19"></line>
        </svg>
    </button>
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
