<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('pcre.jit', '0');

$error = '';
$success = '';
$fullname = $email = $role = '';

try {
    $bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8', 'app_user', 'secure_password_123');
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    // --- Validation ---
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (strlen($fullname) < 3) {
        $error = 'Le nom complet doit contenir au moins 3 caractères.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez entrer une adresse email valide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $bdd->prepare("SELECT userId FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Cet email est déjà utilisé. Veuillez vous connecter.';
            } else {
                // Hasher le mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insérer l'utilisateur
                $stmt = $bdd->prepare("INSERT INTO user (fullname, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$fullname, $email, $hashedPassword, $role]);

                $success = '✅ Inscription réussie ! Vous pouvez maintenant vous connecter.';
                // Réinitialiser les champs
                $fullname = $email = '';
            }
        } catch (PDOException $e) {
            $error = 'X Une erreur est survenue lors de l\'inscription. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Inscription - ULT Payment System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet" />
    <style>
        /* ===== MÊMES STYLES QUE LA PAGE DE CONNEXION ===== */
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
        .signup-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 2rem;
            padding: 2.5rem 2rem 2.25rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08), 0 8px 24px rgba(0, 0, 0, 0.04);
            transition: transform 0.25s ease, box-shadow 0.3s ease;
        }
        .signup-card:hover {
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
            margin-bottom: 1.5rem;
            font-weight: 400;
            letter-spacing: -0.2px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
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
        .form-group input,
        .form-group select {
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
        .form-group input:focus,
        .form-group select:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10);
        }
        .form-group input:hover,
        .form-group select:hover {
            border-color: #94a3b8;
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
            margin-top: 1rem;
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
            background: #d4edda;
            color: #155724;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.25rem 0 0.5rem;
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

        .login-row {
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 0.75rem;
        }
        .login-row a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .login-row a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .signup-card {
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
            .form-group input,
            .form-group select {
                padding: 0.7rem 0.9rem;
                font-size: 0.9rem;
            }
            .btn-primary {
                padding: 0.8rem 1.25rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>

    <main>
        <div class="signup-card">

            <div class="brand">
                <div class="brand-icon" aria-hidden="true">ULT</div>
                <div class="brand-text">ULT<span>Pay</span></div>
            </div>
            <p class="subhead">Créez votre compte</p>

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <span></span> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <span></span> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="signup.php">
                <div class="form-group">
                    <label for="fullname">
                        <span class="label-icon" aria-hidden="true">👤</span>
                        Nom complet
                    </label>
                    <input
                        id="fullname"
                        type="text"
                        name="fullname"
                        placeholder="Jean Dupont"
                        value="<?= htmlspecialchars($fullname) ?>"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="email">
                        <span class="label-icon" aria-hidden="true">✉</span>
                        Email
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        placeholder="vous@exemple.com"
                        value="<?= htmlspecialchars($email) ?>"
                        autocomplete="email"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="password">
                        <span class="label-icon" aria-hidden="true">🔒</span>
                        Mot de passe (6 caractères minimum)
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="••••••••"
                        autocomplete="new-password"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <span class="label-icon" aria-hidden="true">✓</span>
                        Confirmer le mot de passe
                    </label>
                    <input
                        id="confirm_password"
                        type="password"
                        name="confirm_password"
                        placeholder="••••••••"
                        autocomplete="new-password"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="role">
                        <span class="label-icon" aria-hidden="true">🎓</span>
                        Rôle
                    </label>
                    <select id="role" name="role">
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Étudiant</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                    </select>
                </div>

                <button type="submit" name="signup" class="btn-primary">
                    <span aria-hidden="true">✨</span>
                    S'inscrire
                </button>
            </form>

            <div class="divider">
                <span class="divider-line"></span>
                <span class="divider-text">ou</span>
                <span class="divider-line"></span>
            </div>

            <div class="login-row">
                Vous avez déjà un compte ? <a href="index.php">Se connecter</a>
            </div>

        </div>
    </main>

</body>
</html>