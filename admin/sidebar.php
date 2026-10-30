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

<?php include_once __DIR__ . '/../notification_assets.php'; ?>
<?php include_once __DIR__ . '/../session_timeout_assets.php'; ?>
