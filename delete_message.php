<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'any');
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(403); exit('CSRF'); }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit(); }

$stmt = $bdd->prepare("SELECT sender_id FROM messages WHERE id=?");
$stmt->execute([$id]);
$sender = (int)$stmt->fetchColumn();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if (!$isAdmin && $sender !== (int)$_SESSION['userId']) { http_response_code(403); exit(); }

$bdd->prepare("DELETE FROM messages WHERE id=?")->execute([$id]);
logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'DELETE_MESSAGE', (string)$id);
echo json_encode(['ok' => true]);
exit();

