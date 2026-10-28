<?php
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); echo 'CLI only'; exit(1); }
require __DIR__ . '/../functions.php';
$bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8mb4', 'app_user', 'secure_password_123');
$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$rows = $bdd->query("SELECT userId, two_factor_secret, two_factor_backup_codes FROM user WHERE two_factor_secret IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $encSec = encryptData(decryptData($r['two_factor_secret']));
    $encCodes = encryptData(decryptData($r['two_factor_backup_codes']));
    $st = $bdd->prepare("UPDATE user SET two_factor_secret=?, two_factor_backup_codes=? WHERE userId=?");
    $st->execute([$encSec, $encCodes, $r['userId']]);
    echo "Migrated user " . $r['userId'] . "\n";
}
