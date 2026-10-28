<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'any');
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/functions.php';

const EDIT_WINDOW = 120;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(403); exit('CSRF'); }

$id = (int)($_POST['id'] ?? 0);
$msg = trim($_POST['message'] ?? '');
if ($id <= 0 || $msg === '' || strlen($msg) > 2000) { http_response_code(400); exit(); }

$stmt = $bdd->prepare("SELECT sender_id, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds FROM messages WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['error'=>'Message introuvable']); exit(); }
$sender = (int)$row['sender_id'];
$age = (int)$row['age_seconds'];
if ($sender !== (int)$_SESSION['userId']) { http_response_code(403); exit(); }
if ($age > EDIT_WINDOW) { http_response_code(403); echo json_encode(['error'=>'Delai de 2 minutes depasse']); exit(); }

$bdd->prepare("UPDATE messages SET message=? WHERE id=?")->execute([$msg, $id]);
logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'EDIT_MESSAGE', (string)$id);
echo json_encode(['ok' => true]);
exit();
