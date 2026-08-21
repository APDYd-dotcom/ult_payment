<?php
require_once __DIR__ . '/functions.php';

if (!function_exists('runSystemAlertChecks')) {
    function runSystemAlertChecks(?PDO $existingConnection = null): void
    {
        $bdd = $existingConnection;

        if (!$bdd) {
            $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
            $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        checkAlerts($bdd);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    try {
        runSystemAlertChecks();
        echo "System alerts checked successfully.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'System alert check failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
}
