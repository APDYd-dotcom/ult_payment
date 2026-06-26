<?php
session_start();

require_once __DIR__ . '/functions.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';

try {
    $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];
    $plainPassword = $_POST['password'];

    try {
        $stmt = $bdd->prepare("SELECT userId, fullname, email, password, role FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($plainPassword, $user['password'])) {
            $_SESSION['email'] = $user['email'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['userId'] = $user['userId'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // ✅ ENREGISTRER L'HISTORIQUE DE CONNEXION
            logLogin($bdd, $user['userId'], $user['email']);

            if ($user['role'] === 'admin') {
                header('Location: /payment/admin/dashboard.php');
            } else {
                header('Location: /payment/student/dashboard.php');
            }
            exit();
        } else {
            $error = 'Email ou mot de passe incorrect.';
        }
    } catch (PDOException $e) {
        die('Query error: ' . $e->getMessage());
    }
}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ULT Payment System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet" />
    <style>
        /* ===== TES STYLES EXISTANTS ===== */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 1.5rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 2rem;
            padding: 2.5rem 2rem 2.25rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 24px rgba(0, 0, 0, 0.04);
            transition: transform 0.25s ease, box-shadow 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.10), 0 10px 30px rgba(0, 0, 0, 0.05);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 0.5rem;
        }
        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: -0.5px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
        }
        .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #0f172a;
        }
        .brand-text span {
            color: #2563eb;
        }
        .subhead {
            font-size: 0.95rem;
            color: #64748b;
            margin-bottom: 2rem;
            font-weight: 400;
            letter-spacing: -0.2px;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            margin-top: 0.25rem;
        }
        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.4rem;
            letter-spacing: -0.2px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .form-group label .label-icon {
            font-size: 1rem;
            opacity: 0.6;
        }
        .form-group input {
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            color: #0f172a;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            outline: none;
            width: 100%;
        }
        .form-group input::placeholder {
            color: #94a3b8;
            font-weight: 400;
            font-size: 0.9rem;
        }
        .form-group input:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10);
        }
        .form-group input:hover {
            border-color: #94a3b8;
        }
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.75rem;
            margin-bottom: 0.25rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #475569;
            cursor: pointer;
        }
        .remember-me input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: #2563eb;
            border-radius: 4px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .forgot-link {
            font-size: 0.85rem;
            font-weight: 500;
            color: #2563eb;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .forgot-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .buttons {
            margin-top: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .btn-primary {
            padding: 0.9rem 1.5rem;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.25s ease, background 0.25s ease;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.30);
            letter-spacing: -0.2px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(37, 99, 235, 0.35);
        }
        .btn-primary:active {
            transform: translateY(0px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .btn-primary .btn-icon {
            font-size: 1.1rem;
            line-height: 1;
        }
        .signup-row {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }
        .signup-row a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .signup-row a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 0.5rem 0 0.25rem;
        }
        .divider-line {
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        .divider-text {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
        .demo-hint {
            margin-top: 1.25rem;
            padding: 0.7rem 1rem;
            background: #f1f5f9;
            border-radius: 10px;
            font-size: 0.8rem;
            color: #475569;
            text-align: center;
            border: 1px dashed #cbd5e1;
        }
        .demo-hint code {
            background: #e2e8f0;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #1e293b;
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            border-left: 4px solid #dc2626;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .success-message {
            background: #dcfce7;
            color: #166534;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            border-left: 4px solid #16a34a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        @media (max-width: 480px) {
            .login-card {
                padding: 1.75rem 1.25rem 1.5rem;
                border-radius: 1.5rem;
            }
            .brand-text {
                font-size: 1.25rem;
            }
            .brand-icon {
                width: 38px;
                height: 38px;
                font-size: 1rem;
            }
            .form-group input {
                padding: 0.7rem 0.9rem;
                font-size: 0.9rem;
            }
            .btn-primary {
                padding: 0.8rem 1.25rem;
                font-size: 0.95rem;
            }
            .form-options {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }
    </style>
</head>
<body>

    <main>
        <div class="login-card">

            <div class="brand">
                <div class="brand-icon" aria-hidden="true">ULT</div>
                <div class="brand-text">ULT<span>Pay</span></div>
            </div>
            <p class="subhead">Secure access to your payment dashboard</p>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <span>⚠️</span> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <span>✓</span> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">
                        <span class="label-icon" aria-hidden="true">✉</span>
                        Email address
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="you@example.com"
                        autocomplete="email"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="password">
                        <span class="label-icon" aria-hidden="true">🔒</span>
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    />
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" />
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <div class="buttons">
                    <button type="submit" name="login" class="btn-primary">
                        <span class="btn-icon" aria-hidden="true">→</span>
                        Sign in
                    </button>
                </div>
            </form>

            <div class="divider">
                <span class="divider-line"></span>
                <span class="divider-text">or</span>
                <span class="divider-line"></span>
            </div>

            <div class="signup-row">
                Don't have an account? <a href="signup.php">Create one</a>
            </div>

        </div>
    </main>

</body>
</html>
