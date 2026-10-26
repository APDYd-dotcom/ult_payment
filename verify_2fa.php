<?php
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/two_factor.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = '';
$pendingUser = $_SESSION['2fa_pending_user'] ?? null;
$pendingStarted = (int) ($_SESSION['2fa_pending_started'] ?? 0);

if (!is_array($pendingUser) || empty($pendingUser['userId']) || $pendingStarted < strtotime('-10 minutes')) {
    unset($_SESSION['2fa_pending_user'], $_SESSION['2fa_pending_started']);
    header('Location: /payment');
    exit();
}

try {
    $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $code = trim($_POST['two_factor_code'] ?? '');

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } elseif ($code === '') {
        $error = 'Veuillez saisir votre code 2FA ou un code de secours.';
    } else {
        $user = ultTwoFactorFetchUser($bdd, (int) $pendingUser['userId']);

        if (!$user || (int) ($user['two_factor_enabled'] ?? 0) !== 1) {
            $error = 'Configuration 2FA introuvable.';
        } elseif (
            ultTwoFactorVerifyTotp((string) $user['two_factor_secret'], $code)
            || ultTwoFactorConsumeBackupCode($bdd, $user, $code)
        ) {
            ultTwoFactorCompleteLogin($bdd, $user);
        } else {
            $error = 'Code 2FA ou code de secours invalide.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification 2FA - ULT Payment System</title>
    <?php loadTheme($bdd); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-family, 'Segoe UI', sans-serif);
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--background-color, #f4f6f9);
        }
        .verify-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: var(--primary-color, #1e3a8a);
            font-size: 1.65rem;
            margin-bottom: 0.75rem;
        }
        p {
            color: #475569;
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }
        label {
            display: block;
            margin-bottom: 0.35rem;
            color: #1e293b;
            font-weight: 700;
        }
        input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        input:focus {
            border-color: var(--secondary-color, #2563eb);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        button {
            width: 100%;
            padding: 0.8rem 1rem;
            border: none;
            border-radius: 8px;
            background: var(--secondary-color, #2563eb);
            color: #ffffff;
            cursor: pointer;
            font-weight: 700;
        }
        button:hover {
            background: #1d4ed8;
        }
        .message-error {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <main class="verify-card">
        <h1>Vérification 2FA</h1>
        <p>Saisissez le code Google Authenticator ou un code de secours pour terminer la connexion.</p>

        <?php if ($error): ?>
            <div class="message-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="verify_2fa.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <label for="two_factor_code">Code</label>
            <input id="two_factor_code" type="text" name="two_factor_code" autocomplete="one-time-code" autofocus required>
            <button type="submit">Valider</button>
        </form>
    </main>
</body>
</html>
