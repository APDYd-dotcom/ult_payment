<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('pcre.jit', '0');

$message = '';
$messageType = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenIsValid = false;
$tokenRow = null;

try {
    $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

function findValidResetToken(PDO $bdd, string $rawToken): ?array
{
    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);
    $stmt = $bdd->prepare("
        SELECT prt.id, prt.user_id, prt.expires_at, u.email
        FROM password_reset_tokens prt
        JOIN user u ON u.userId = prt.user_id
        WHERE prt.token = ?
          AND prt.used_at IS NULL
          AND prt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

if ($token !== '') {
    $tokenRow = findValidResetToken($bdd, $token);
    $tokenIsValid = $tokenRow !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!$tokenIsValid) {
        $message = 'Le lien de réinitialisation est invalide ou expiré.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Les deux mots de passe ne correspondent pas.';
        $messageType = 'error';
    } else {
        try {
            $bdd->beginTransaction();

            $tokenRow = findValidResetToken($bdd, $token);
            if (!$tokenRow) {
                throw new RuntimeException('Token expired or already used.');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $updateUser = $bdd->prepare("UPDATE user SET password = ? WHERE userId = ?");
            $updateUser->execute([$passwordHash, $tokenRow['user_id']]);

            $markToken = $bdd->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
            $markToken->execute([$tokenRow['id']]);

            $bdd->commit();

            header('Location: index.php?reset=success');
            exit();
        } catch (Throwable $e) {
            if ($bdd->inTransaction()) {
                $bdd->rollBack();
            }

            $message = 'Impossible de réinitialiser le mot de passe. Veuillez demander un nouveau lien.';
            $messageType = 'error';
            $tokenIsValid = false;
        }
    }
} elseif ($token === '') {
    $message = 'Token manquant. Veuillez utiliser le lien reçu par email.';
    $messageType = 'error';
} elseif (!$tokenIsValid) {
    $message = 'Le lien de réinitialisation est invalide ou expiré.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Réinitialiser le mot de passe - ULT Payment System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%); padding: 1.5rem; }
        .login-card { width: 100%; max-width: 420px; background: #fff; border-radius: 2rem; padding: 2.5rem 2rem 2.25rem; box-shadow: 0 20px 60px rgba(0,0,0,.08), 0 8px 24px rgba(0,0,0,.04); }
        .brand { display: flex; align-items: center; gap: .6rem; margin-bottom: .5rem; }
        .brand-icon { width: 44px; height: 44px; background: linear-gradient(135deg, #2563eb, #1d4ed8); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.25rem; box-shadow: 0 6px 16px rgba(37,99,235,.25); }
        .brand-text { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
        .brand-text span { color: #2563eb; }
        .subhead { font-size: .95rem; color: #64748b; margin-bottom: 2rem; line-height: 1.5; }
        form { display: flex; flex-direction: column; gap: .75rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: .85rem; font-weight: 600; color: #1e293b; margin-bottom: .4rem; }
        .form-group input { padding: .8rem 1rem; font-size: .95rem; font-family: 'Inter', sans-serif; border: 1.5px solid #e2e8f0; border-radius: 12px; background: #f8fafc; color: #0f172a; outline: none; width: 100%; }
        .form-group input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,.10); }
        .buttons { margin-top: 1rem; }
        .btn-primary { padding: .9rem 1.5rem; font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 600; border: none; border-radius: 12px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; cursor: pointer; width: 100%; }
        .message { padding: .75rem 1rem; border-radius: 10px; font-size: .9rem; margin-bottom: 1rem; line-height: 1.4; }
        .message-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        .message-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .back-link { margin-top: 1.5rem; text-align: center; font-size: .9rem; }
        .back-link a { color: #2563eb; font-weight: 600; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<main>
    <div class="login-card">
        <div class="brand">
            <div class="brand-icon" aria-hidden="true">ULT</div>
            <div class="brand-text">ULT<span>Pay</span></div>
        </div>
        <p class="subhead">Choisissez un nouveau mot de passe pour votre compte.</p>

        <?php if ($message): ?>
            <div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($tokenIsValid): ?>
            <form method="POST" action="reset_password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required />
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required />
                </div>
                <div class="buttons">
                    <button type="submit" class="btn-primary">Réinitialiser</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="index.php">Retour à la connexion</a>
        </div>
    </div>
</main>
</body>
</html>
