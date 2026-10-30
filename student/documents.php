<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'student');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$stmt = $bdd->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['userId']]);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Mes documents</title><link rel="stylesheet" href="../admin/styles.css?v=1.1">
<style>
.doc-card { background:#fff; border-radius:12px; padding:16px; margin-bottom:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); display:flex; justify-content:space-between; align-items:center; }
.doc-info h3 { margin:0; font-size:1rem; color:#1e3a8a; }
.doc-meta { color:#64748b; font-size:.85rem; }
.btn { padding:8px 14px; border-radius:8px; text-decoration:none; color:#fff; background:#1e3a8a; }
</style>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css">
</head><body>
<div class="container"><?php include 'sidebar.php'; ?>    <main class="main-content">
<h1 class="page-title">Mes documents</h1>
<p><a class="btn" href="upload_document.php">Téléverser un document</a></p>
<?php if (!$docs): ?>
  <p>Aucun document.</p>
<?php endif; ?>
<?php foreach ($docs as $d): ?>
  <div class="doc-card">
    <div class="doc-info">
      <h3><?= htmlspecialchars($d['original_name']) ?></h3>
      <div class="doc-meta">Version <?= (int)$d['version'] ?> · <?= (int)$d['size'] ?> octets · <?= htmlspecialchars($d['created_at']) ?> <?= $d['is_archived'] ? '· Archivé' : '' ?></div>
    </div>
    <div>
      <?php if (!$d['is_archived']): ?>
        <a class="btn" href="../admin/download.php?id=<?= (int)$d['id'] ?>">Télécharger</a>
      <?php else: ?>
        <span class="doc-meta">Archivé par l'admin</span>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</main></div>    <script src="/payment/assets/js/responsive.js"></script>
</body></html>
