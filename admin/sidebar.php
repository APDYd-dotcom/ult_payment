<?php renderThemeLogo($bdd); ?>

<ul>
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="departement.php">Departement</a></li>
    <li><a href="student.php">Students</a></li>
    <li><a href="payment.php">Payments</a></li>
    <li><a href="partial.php">Partial Payments</a></li>
    <li><a href="penalty.php">Penalties</a></li>
    <li><a href="login_history.php">Login History</a></li>
    <li><a href="manage_users.php">Manage Users</a></li>
    <li><a href="theme_settings.php">Theme Settings</a></li>
    <li><a href="mailing.php">Mailing List</a></li>
    <li><a href="activity_log.php">Activity Log</a></li>
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
