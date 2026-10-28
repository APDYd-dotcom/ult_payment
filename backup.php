<?php
declare(strict_types=1);

function runDatabaseBackup(): array {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'ult_');
    file_put_contents($tmp, "[client]\nhost=localhost\nuser=app_user\npassword=secure_password_123\n");
    chmod($tmp, 0600);

    $stamp = date('Y-m-d_His');
    $file = $backupDir . '/' . $stamp . '.sql.gz';
    $errFile = tempnam(sys_get_temp_dir(), 'ulterr_');

    $pipeline = '/usr/bin/mysqldump --defaults-extra-file=' . escapeshellarg($tmp) . ' --single-transaction --routines --triggers ult_payment 2>' . escapeshellarg($errFile) . ' | /usr/bin/gzip > ' . escapeshellarg($file);
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

    // verify dump completed
    $verifyCmd = '/bin/bash -o pipefail -c ' . escapeshellarg('/usr/bin/gunzip -c ' . escapeshellarg($file) . ' | /usr/bin/tail -n 3');
    exec($verifyCmd, $vout, $vrc);
    $tail = implode("\n", $vout);
    if (strpos($tail, 'Dump completed') === false) {
        @unlink($file);
        return ['success' => false, 'message' => 'Sauvegarde incomplete : marque de fin absente'];
    }

    // rotation: keep 7 latest
    $files = glob($backupDir . '/*.sql.gz');
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($files, 7) as $old) {
        @unlink($old);
    }

    return ['success' => true, 'file' => basename($file)];
}

if (php_sapi_name() === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $res = runDatabaseBackup();
    echo ($res['success'] ? "Backup: " . $res['file'] : $res['message']) . "\n";
    exit($res['success'] ? 0 : 1);
}
