<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'any');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $bdd->prepare("SELECT * FROM documents WHERE id=?");
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doc) { http_response_code(404); exit('Introuvable'); }

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$isOwner = ((int)$doc['user_id'] === (int)($_SESSION['userId'] ?? 0));
if (!$isAdmin && !$isOwner) { http_response_code(403); exit('Interdit'); }

$path = __DIR__ . '/../uploads/' . ($doc['is_archived'] ? 'archive/' : 'documents/') . $doc['stored_name'];
if (!is_file($path)) { $path = __DIR__ . '/../uploads/documents/' . $doc['stored_name']; }
if (!is_file($path)) { http_response_code(404); exit('Fichier absent'); }

logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'DOWNLOAD_DOC', $doc['original_name']);
header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: attachment; filename="' . $doc['original_name'] . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit();
