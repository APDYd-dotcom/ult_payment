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
<html><head><title>Documents</title><link rel="stylesheet" href="./styles.css?v=1.1"></head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Documents</h1>
<form method="GET" class="form-section">
<input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Recherche nom">
<select name="archived"><option value="">Tous</option><option value="0" <?= $archived==='0'?'selected':'' ?>>Actifs</option><option value="1" <?= $archived==='1'?'selected':'' ?>>Archivés</option></select>
<button class="btn-primary" type="submit">Filtrer</button>
</form>
<p><a href="upload_document.php">Téléverser</a></p>
<table class="table-section"><tr><th>Nom</th><th>Version</th><th>Taille</th><th>Date</th><th></th></tr>
<?php foreach ($docs as $d): ?>
<tr>
<td><?= htmlspecialchars($d['original_name']) ?></td>
<td><?= (int)$d['version'] ?></td>
<td><?= (int)$d['size'] ?></td>
<td><?= htmlspecialchars($d['created_at']) ?></td>
<td>
<a href="download.php?id=<?= (int)$d['id'] ?>">Télécharger</a> |
<a href="share_document.php?id=<?= (int)$d['id'] ?>">Partager</a> |
<a href="archive_document.php?id=<?= (int)$d['id'] ?>&action=<?= $d['is_archived']?'unarchive':'archive' ?>"><?= $d['is_archived']?'Désarchiver':'Archiver' ?></a>
</td>
</tr>
<?php endforeach; ?>
</table>
</main></div></body></html>
PHP