<?php
require __DIR__ . '/../functions.php';
$bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8mb4', 'app_user', 'secure_password_123');
$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$token = trim($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) { http_response_code(404); exit('Lien invalide ou expiré.'); }
$stmt = $bdd->prepare("SELECT ds.*, d.* FROM document_shares ds JOIN documents d ON d.id=ds.document_id WHERE ds.token=?");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || strtotime($row['expires_at']) < time()) { http_response_code(404); exit('Lien invalide ou expiré.'); }
$path = __DIR__ . '/uploads/' . ($row['is_archived'] ? 'archive/' : 'documents/') . $row['stored_name'];
if (!is_file($path)) { $path = __DIR__ . '/uploads/documents/' . $row['stored_name']; }
if (!is_file($path)) { http_response_code(404); exit('Fichier absent.'); }
logActivity($bdd, 0, 'public', '', 'SHARED_DOWNLOAD', $row['original_name']);
header('Content-Type: ' . $row['mime_type']);
header('Content-Disposition: attachment; filename="' . $row['original_name'] . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit();
