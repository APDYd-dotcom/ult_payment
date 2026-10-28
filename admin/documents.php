<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$q = trim($_GET['q'] ?? '');
$archived = $_GET['archived'] ?? '';
$sql = "SELECT d.*, u.fullname AS uploader FROM documents d LEFT JOIN user u ON u.userId=d.user_id WHERE 1=1";
$params = [];
if ($q !== '') { $sql .= " AND d.original_name LIKE ?"; $params[] = '%' . $q . '%'; }
if ($archived === '1') { $sql .= " AND d.is_archived=1"; }
elseif ($archived === '0') { $sql .= " AND d.is_archived=0"; }
$sql .= " ORDER BY d.created_at DESC";
$stmt = $bdd->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><title>Documents</title><link rel="stylesheet" href="./styles.css?v=1.1">
<style>
.doc-card { background:#fff; border-radius:12px; padding:16px; margin-bottom:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); display:flex; justify-content:space-between; align-items:center; }
.doc-info h3 { margin:0; font-size:1rem; color:#1e3a8a; }
.doc-meta { color:#64748b; font-size:.85rem; }
.actions a { margin-left:8px; color:#1e3a8a; text-decoration:none; font-weight:600; }
.filters { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.filters input, .filters select { padding:8px; border-radius:8px; border:1px solid #cbd5e1; }
</style>
</head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Documents</h1>
<form method="GET" class="filters">
  <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Recherche nom">
  <select name="archived">
    <option value="">Tous</option>
    <option value="0" <?= $archived==='0'?'selected':'' ?>>Actifs</option>
    <option value="1" <?= $archived==='1'?'selected':'' ?>>Archivés</option>
  </select>
  <button class="btn-primary" type="submit">Filtrer</button>
  <a class="btn-secondary" href="upload_document.php">Téléverser</a>
</form>
<?php if (!$docs): ?>
  <p>Aucun document.</p>
<?php endif; ?>
<?php foreach ($docs as $d): ?>
  <div class="doc-card">
    <div class="doc-info">
      <h3><?= htmlspecialchars($d['original_name']) ?></h3>
      <div class="doc-meta">v<?= (int)$d['version'] ?> · <?= (int)$d['size'] ?> octets · <?= htmlspecialchars($d['created_at']) ?> · <?= htmlspecialchars($d['uploader'] ?? '') ?> <?= $d['is_archived'] ? '· Archivé' : '' ?></div>
    </div>
    <div class="actions">
      <a href="download.php?id=<?= (int)$d['id'] ?>">Télécharger</a>
      <a href="share_document.php?id=<?= (int)$d['id'] ?>">Partager</a>
      <a href="archive_document.php?id=<?= (int)$d['id'] ?>&action=<?= $d['is_archived']?'unarchive':'archive' ?>"><?= $d['is_archived']?'Désarchiver':'Archiver' ?></a>
    </div>
  </div>
<?php endforeach; ?>
</main></div></body></html>
PHP