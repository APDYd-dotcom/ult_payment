<?php
session_start();

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('pcre.jit', '0');

$message = '';
$messageType = '';



try {
    $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

function buildResetUrl(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/payment'), '/\\');

    return $scheme . '://' . $host . $path . '/reset_password.php?token=' . urlencode($token);
}

function sendPasswordResetEmail(string $toEmail, string $toName, string $resetUrl): void
{
    global $smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpEncryption;

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('PHPMailer is not installed. Run: composer require phpmailer/phpmailer');
    }

    require_once $autoload;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = $smtpEncryption;
    $mail->Port = $smtpPort;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($smtpUsername, 'ULT Payment System');
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = 'Réinitialisation de votre mot de passe';
    $mail->Body = '
        <p>Bonjour ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ',</p>
        <p>Vous avez demandé la réinitialisation de votre mot de passe ULT Payment System.</p>
        <p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Réinitialiser mon mot de passe</a></p>
        <p>Ce lien expire dans 1 heure. Si vous n\'avez pas demandé cette action, ignorez cet email.</p>
    ';
    $mail->AltBody = "Bonjour $toName,\n\nUtilisez ce lien pour réinitialiser votre mot de passe : $resetUrl\n\nCe lien expire dans 1 heure.";

    $mail->send();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Veuillez saisir une adresse email valide.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $bdd->prepare("SELECT userId, fullname, email FROM user WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                $cleanup = $bdd->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
                $cleanup->execute([$user['userId']]);

                $insert = $bdd->prepare("
                    INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
                ");
                $insert->execute([$user['userId'], $tokenHash]);

                try {
                    sendPasswordResetEmail($user['email'], $user['fullname'], buildResetUrl($rawToken));
                } catch (Throwable $mailError) {
                    error_log('Password reset email error: ' . $mailError->getMessage());
                }
            }

            $message = 'Si cette adresse existe, un lien de réinitialisation vient d\'être envoyé.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mot de passe oublié - ULT Payment System</title>
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
        form { display: flex; flex-direction: column; gap: .25rem; }
        .form-group { display: flex; flex-direction: column; margin-top: .25rem; }
        .form-group label { font-size: .85rem; font-weight: 600; color: #1e293b; margin-bottom: .4rem; }
        .form-group input { padding: .8rem 1rem; font-size: .95rem; font-family: 'Inter', sans-serif; border: 1.5px solid #e2e8f0; border-radius: 12px; background: #f8fafc; color: #0f172a; outline: none; width: 100%; }
        .form-group input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,.10); }
        .buttons { margin-top: 1.75rem; }
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
        <p class="subhead">Entrez votre email. Si un compte existe, nous vous enverrons un lien valable pendant 1 heure.</p>

        <?php if ($message): ?>
            <div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input id="email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required />
            </div>
            <div class="buttons">
                <button type="submit" class="btn-primary">Envoyer le lien</button>
            </div>
        </form>

        <div class="back-link">
            <a href="index.php">Retour à la connexion</a>
        </div>
    </div>
</main>
</body>
</html>
