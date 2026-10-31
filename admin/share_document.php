<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$id = (int)($_GET['id'] ?? 0);
$hours = (int)($_POST['hours'] ?? 24);
if ($hours < 1) $hours = 24;
$link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { die('CSRF'); }
    $token = bin2hex(random_bytes(32));
    $stmt = $bdd->prepare("INSERT INTO document_shares (document_id, token, expires_at, created_by) VALUES (?,?,DATE_ADD(NOW(), INTERVAL ? HOUR),?)");
    $stmt->execute([$id, $token, $hours, $_SESSION['userId']]);
    $link = 'http://' . $_SERVER['HTTP_HOST'] . '/payment/shared.php?token=' . $token;
    logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'SHARE_DOC', (string)$id);
}
?>
<!DOCTYPE html>
<html><head>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Partage</title><link rel="stylesheet" href="./styles.css?v=20261030">    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head><body>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <section class="page active">
            <h1 class="page-title">Partager un document</h1>
<form method="POST" class="form-section">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<label>Durée (heures)</label><input type="number" name="hours" value="24" inputmode="numeric">
<button class="btn-primary" type="submit">Générer le lien</button>
</form>
<?php if ($link): ?><div class="message message-success"><?= htmlspecialchars($link) ?></div><?php endif; ?>
<p><a href="documents.php">Retour</a></p>
</main></div>    <script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body></html>
PHP