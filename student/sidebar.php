<?php
require_once __DIR__ . '/auth_check.php';
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>

<div class="logo">
    <h2>ULT PAYMENT</h2>
</div>

<ul>
    <li><a href="student.php">Mon dossier</a></li>
    <li><a href="payment.php">Mes paiements</a></li>
    <li><a href="partial.php">Paiements partiels</a></li>
    <li><a href="penalty.php">Mes penalites</a></li>
    <li><a href="profile.php">Compte</a></li>
    <li>
        <form method="POST" action="/payment/logout.php">
            <input type="hidden" name="logout_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Logout</button>
        </form>
    </li>
</ul>

<?php include_once __DIR__ . '/../notification_assets.php'; ?>
<?php include_once __DIR__ . '/../session_timeout_assets.php'; ?>
