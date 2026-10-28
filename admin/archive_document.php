<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'archive';
$stmt = $bdd->prepare("SELECT * FROM documents WHERE id=?");
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if ($doc) {
    if ($action === 'archive') {
        $bdd->prepare("UPDATE documents SET is_archived=1 WHERE id=?")->execute([$id]);
        $src = __DIR__ . '/../uploads/documents/' . $doc['stored_name'];
        $dstDir = __DIR__ . '/../uploads/archive';
        if (!is_dir($dstDir)) mkdir($dstDir, 0755, true);
        if (is_file($src)) @rename($src, $dstDir . '/' . $doc['stored_name']);
        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'ARCHIVE_DOC', (string)$id);
    } else {
        $bdd->prepare("UPDATE documents SET is_archived=0 WHERE id=?")->execute([$id]);
        $src = __DIR__ . '/../uploads/archive/' . $doc['stored_name'];
        $dstDir = __DIR__ . '/../uploads/documents';
        if (!is_dir($dstDir)) mkdir($dstDir, 0755, true);
        if (is_file($src)) @rename($src, $dstDir . '/' . $doc['stored_name']);
        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'UNARCHIVE_DOC', (string)$id);
    }
}
header('Location: documents.php');
exit();
PHP