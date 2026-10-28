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
    $cmd = 'mysqldump --defaults-extra-file=' . escapeshellarg($tmp) . ' --single-transaction --routines --triggers ult_payment | gzip > ' . escapeshellarg($file);
    exec($cmd, $out, $rc);
    unlink($tmp);

    if ($rc !== 0 || !file_exists($file)) {
        return ['success' => false, 'message' => 'Echec de la sauvegarde.'];
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
PHP