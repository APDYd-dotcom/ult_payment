<?php
require_once __DIR__ . '/auth_check.php';

$message = '';
$messageType = '';
$hasMatriculeColumn = tableColumnExists($bdd, 'user', 'matricule');

$userFields = $hasMatriculeColumn ? 'fullname, email, matricule' : 'fullname, email';
$userStmt = $bdd->prepare("SELECT {$userFields} FROM user WHERE userId = ? LIMIT 1");
$userStmt->execute([$_SESSION['userId']]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

try {
    $departments = $bdd->query('SELECT id, name FROM department ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
    $message = 'Erreur lors du chargement des departements : ' . $e->getMessage();
    $messageType = 'error';
}

if (isset($_GET['missing_profile'])) {
    $message = 'Votre compte etudiant existe, mais aucun dossier etudiant ne lui est encore rattache. Completez votre profil pour continuer.';
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullName'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $fallbackMatricule = $hasMatriculeColumn && !empty($userData['matricule'])
        ? trim((string) $userData['matricule'])
        : trim((string) ($userData['email'] ?? ''));
    $matricule = trim($_POST['matricule'] ?? $fallbackMatricule);

    if ($fullName === '' || $age <= 0 || $departmentId <= 0 || $matricule === '') {
        $message = 'Tous les champs sont obligatoires.';
        $messageType = 'error';
    } elseif ($age < 18) {
        $message = 'L age doit etre superieur ou egal a 18 ans.';
        $messageType = 'error';
    } else {
        try {
            $deptStmt = $bdd->prepare('SELECT id FROM department WHERE id = ? LIMIT 1');
            $deptStmt->execute([$departmentId]);
            if (!$deptStmt->fetchColumn()) {
                throw new RuntimeException('Departement introuvable.');
            }

            if ($studentId) {
                $stmt = $bdd->prepare('UPDATE student SET name = ?, age = ?, department_id = ? WHERE id = ?');
                $stmt->execute([$fullName, $age, $departmentId, $studentId]);

                $userUpdate = $bdd->prepare('UPDATE user SET fullname = ? WHERE userId = ?');
                $userUpdate->execute([$fullName, $_SESSION['userId']]);
                $_SESSION['fullname'] = $fullName;

                logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_profile_updated', "Student ID: {$studentId}");
                header('Location: student.php?success=updated');
                exit();
            }

            $duplicateStmt = $bdd->prepare('SELECT id FROM student WHERE matricule = ? LIMIT 1');
            $duplicateStmt->execute([$matricule]);
            $existingStudentId = (int) $duplicateStmt->fetchColumn();

            if ($existingStudentId > 0) {
                if ($hasMatriculeColumn) {
                    $ownerStmt = $bdd->prepare("SELECT userId FROM user WHERE matricule = ? AND role = 'student' AND userId != ? LIMIT 1");
                    $ownerStmt->execute([$matricule, $_SESSION['userId']]);
                    if ($ownerStmt->fetchColumn()) {
                        throw new RuntimeException('Ce matricule est deja rattache a un autre compte etudiant.');
                    }
                }

                $bdd->beginTransaction();

                $stmt = $bdd->prepare('UPDATE student SET name = ?, age = ?, department_id = ? WHERE id = ?');
                $stmt->execute([$fullName, $age, $departmentId, $existingStudentId]);

                $userSql = $hasMatriculeColumn
                    ? 'UPDATE user SET fullname = ?, matricule = ? WHERE userId = ?'
                    : 'UPDATE user SET fullname = ? WHERE userId = ?';
                $userUpdate = $bdd->prepare($userSql);
                $userUpdate->execute($hasMatriculeColumn ? [$fullName, $matricule, $_SESSION['userId']] : [$fullName, $_SESSION['userId']]);

                $bdd->commit();

                $_SESSION['fullname'] = $fullName;
                $_SESSION['student_id'] = $existingStudentId;
                $_SESSION['student_matricule'] = $matricule;

                logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_profile_linked', "Student ID: {$existingStudentId}, Matricule: {$matricule}");
                header('Location: student.php?success=linked');
                exit();
            }

            $stmt = $bdd->prepare('INSERT INTO student (matricule, name, age, department_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$matricule, $fullName, $age, $departmentId]);
            $newStudentId = (int) $bdd->lastInsertId();

            $userSql = $hasMatriculeColumn
                ? 'UPDATE user SET fullname = ?, matricule = ? WHERE userId = ?'
                : 'UPDATE user SET fullname = ? WHERE userId = ?';
            $userUpdate = $bdd->prepare($userSql);
            $userUpdate->execute($hasMatriculeColumn ? [$fullName, $matricule, $_SESSION['userId']] : [$fullName, $_SESSION['userId']]);

            $_SESSION['fullname'] = $fullName;
            $_SESSION['student_id'] = $newStudentId;
            $_SESSION['student_matricule'] = $matricule;

            logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_profile_created', "Student ID: {$newStudentId}, Matricule: {$matricule}");
            header('Location: student.php?success=created');
            exit();
        } catch (Throwable $e) {
            $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $messageType = 'error';
        }
    }
}

if (isset($_GET['success'])) {
    $successMessages = [
        'created' => 'Profil etudiant cree avec succes.',
        'updated' => 'Profil etudiant mis a jour avec succes.',
        'linked' => 'Votre compte a ete rattache au dossier etudiant existant.',
    ];
    $message = $successMessages[$_GET['success']] ?? 'Operation effectuee avec succes.';
    $messageType = 'success';
    $currentStudent = studentResolveCurrentStudent($bdd);
    $studentId = $currentStudent ? (int) $currentStudent['id'] : null;
}

$formData = [
    'matricule' => $currentStudent['matricule'] ?? ($userData['matricule'] ?? ''),
    'name' => $currentStudent['name'] ?? ($userData['fullname'] ?? ''),
    'age' => $currentStudent['age'] ?? '',
    'department_id' => $currentStudent['department_id'] ?? '',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon dossier - ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.2">
    <style>
        .message{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-weight:500}
        .message-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .message-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        select{padding:10px;margin-top:5px;border:1px solid #ccc;border-radius:6px}
        .profile-summary{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);margin-bottom:20px}
        .profile-summary p{margin:8px 0;color:#1e293b}
    </style>
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar"><?php include 'sidebar.php'; ?></aside>
    <main id="main-content" class="main-content">
        <section id="student" class="page active">
            <h1 class="page-title">Mon dossier etudiant</h1>

            <?php if ($message): ?>
                <div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($currentStudent): ?>
                <div class="profile-summary">
                    <p><strong>Matricule :</strong> <?= htmlspecialchars($currentStudent['matricule'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Nom :</strong> <?= htmlspecialchars($currentStudent['name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Age :</strong> <?= htmlspecialchars((string) $currentStudent['age'], ENT_QUOTES, 'UTF-8') ?></p>
                    <p><strong>Departement :</strong> <?= htmlspecialchars($currentStudent['department_name'] ?? 'Non defini', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endif; ?>

            <div class="crud-container">
                <div class="form-section" style="max-width:640px">
                    <h3><?= $currentStudent ? 'Modifier mon profil' : 'Completer mon profil' ?></h3>
                    <form method="POST" action="student.php">
                        <label for="matricule">Matricule</label>
                        <input id="matricule" type="text" name="matricule" value="<?= htmlspecialchars((string) $formData['matricule'], ENT_QUOTES, 'UTF-8') ?>" <?= $currentStudent ? 'readonly' : 'required' ?>>

                        <label for="fullName">Nom complet</label>
                        <input id="fullName" type="text" name="fullName" value="<?= htmlspecialchars((string) $formData['name'], ENT_QUOTES, 'UTF-8') ?>" required>

                        <label for="age">Age</label>
                        <input id="age" type="number" name="age" min="18" value="<?= htmlspecialchars((string) $formData['age'], ENT_QUOTES, 'UTF-8') ?>" required>

                        <label for="department_id">Departement</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">-- Selectionner --</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= (int) $department['id'] ?>" <?= (int) $formData['department_id'] === (int) $department['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="buttons">
                            <button type="submit"><?= $currentStudent ? 'Mettre a jour' : 'Creer mon dossier' ?></button>
                            <button type="reset">Effacer</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
