<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'any');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { http_response_code(403); exit('CSRF'); }

$receiver = (int)($_POST['receiver_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
if ($receiver <= 0 || $message === '' || strlen($message) > 2000) { http_response_code(400); exit(); }

// student can only talk to admins
if (($_SESSION['role'] ?? '') === 'student') {
    $st = $bdd->prepare("SELECT role FROM user WHERE userId=?");
    $st->execute([$receiver]);
    if ($st->fetchColumn() !== 'admin') { http_response_code(403); exit(); }
}

$stmt = $bdd->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)");
$stmt->execute([$_SESSION['userId'], $receiver, $message]);
logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'SEND_MESSAGE', 'to=' . $receiver);
echo json_encode(['ok' => true]);
exit();
