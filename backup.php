<?php
declare(strict_types=1);

function runDatabaseBackup(): array {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // Création du fichier de configuration temporaire pour mysqldump
    // On force l'hôte à 127.0.0.1 (réseau TCP) et non localhost (socket)
    $tmp = tempnam(sys_get_temp_dir(), 'ult_');
    file_put_contents($tmp, "[client]\nhost=127.0.0.1\nuser=app_user\npassword=secure_password_123\n");
    chmod($tmp, 0600);

    $stamp = date('Y-m-d_His');
    $file = $backupDir . '/' . $stamp . '.sql.gz';
    $errFile = tempnam(sys_get_temp_dir(), 'ulterr_');

    // Ajout de --protocol=tcp pour être certain d'utiliser TCP
    $pipeline = '/usr/bin/mysqldump --defaults-extra-file=' . escapeshellarg($tmp) . ' --protocol=tcp --single-transaction --routines --triggers ult_payment 2>' . escapeshellarg($errFile) . ' | /usr/bin/gzip > ' . escapeshellarg($file);
    $cmd = '/bin/bash -o pipefail -c ' . escapeshellarg($pipeline);
    exec($cmd, $out, $rc);

    $dumpErr = is_file($errFile) ? trim((string) file_get_contents($errFile)) : '';
    @unlink($errFile);
    @unlink($tmp);

    if ($rc !== 0 || !file_exists($file) || filesize($file) <= 0) {
        @unlink($file);
        $detail = $dumpErr !== '' ? $dumpErr : 'code ' . $rc;
        return ['success' => false, 'message' => 'Sauvegarde incomplete : ' . $detail];
    }

    // Vérification que le dump est complet (marque de fin)
    $verifyCmd = '/bin/bash -o pipefail -c ' . escapeshellarg('/usr/bin/gunzip -c ' . escapeshellarg($file) . ' | /usr/bin/tail -n 3');
    exec($verifyCmd, $vout, $vrc);
    $tail = implode("\n", $vout);
    if (strpos($tail, 'Dump completed') === false) {
        @unlink($file);
        return ['success' => false, 'message' => 'Sauvegarde incomplete : marque de fin absente'];
    }

    // Rotation : garder les 7 dernières sauvegardes
    $files = glob($backupDir . '/*.sql.gz');
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($files, 7) as $old) {
        @unlink($old);
    }

    return ['success' => true, 'file' => basename($file)];
}

// Exécution en ligne de commande (pour cron)
if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $res = runDatabaseBackup();
    echo ($res['success'] ? "Backup: " . $res['file'] : $res['message']) . "\n";
    exit($res['success'] ? 0 : 1);
}
