<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { die('CSRF'); }
    $table = $_POST['table'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'archive' && in_array($table, ['student','payment'], true) && $id > 0) {
        $stmt = $bdd->prepare("CALL sp_archive_record(?,?,?)");
        $stmt->execute([$table, $id, $_SESSION['userId'] ?? null]);
        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'ARCHIVE', $table . '#' . $id);
    } elseif ($action === 'restore') {
        $aid = (int)($_POST['archive_id'] ?? 0);
        $stmt = $bdd->prepare("CALL sp_restore_record(?)");
        $stmt->execute([$aid]);
        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'RESTORE_ARCHIVE', (string)$aid);
    }
}

$rows = $bdd->query("SELECT * FROM archives ORDER BY archived_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Archives</title><link rel="stylesheet" href="./styles.css?v=1.1">    <link rel="stylesheet" href="/payment/assets/css/responsive.css">
</head><body>
<div class="container"><?php include 'sidebar.php'; ?>    <?php include "topbar.php"; ?>
<main class="main-content">
<h1 class="page-title">Archives</h1>
<div class="table-responsive">
<table class="table-section"><tr><th>ID</th><th>Table</th><th>Record</th><th>Archivé le</th><th>Restauré le</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= (int)$r['id'] ?></td>
<td><?= htmlspecialchars($r['table_name']) ?></td>
<td><?= (int)$r['record_id'] ?></td>
<td><?= htmlspecialchars($r['archived_at']) ?></td>
<td><?= htmlspecialchars((string)$r['restored_at']) ?></td>
<td><?php if (!$r['restored_at']): ?>
<form method="POST" style="display:inline">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="action" value="restore">
<input type="hidden" name="archive_id" value="<?= (int)$r['id'] ?>">
<button class="btn-secondary" type="submit">Restaurer</button>
</form>
<?php endif; ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>
</main></div>    <script src="/payment/assets/js/responsive.js"></script>
</body></html>
PHP