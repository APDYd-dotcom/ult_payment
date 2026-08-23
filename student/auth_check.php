<?php
// Authentification commune aux pages etudiantes.
// Ce fichier verifie le role, puis rattache l'utilisateur connecte a sa ligne student.

define('REQUIRED_ROLE', 'student');
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

if (!function_exists('studentResolveCurrentStudent')) {
    function studentResolveCurrentStudent(PDO $bdd): ?array
    {
        $userId = (int) ($_SESSION['userId'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $hasMatriculeColumn = tableColumnExists($bdd, 'user', 'matricule');
        $selectFields = $hasMatriculeColumn
            ? 'userId, fullname, email, matricule'
            : 'userId, fullname, email';

        $userStmt = $bdd->prepare("SELECT {$selectFields} FROM user WHERE userId = ? AND role = 'student' LIMIT 1");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $candidates = [];
        if ($hasMatriculeColumn && !empty($user['matricule'])) {
            $candidates[] = trim((string) $user['matricule']);
        }
        if (!empty($user['email'])) {
            $email = trim((string) $user['email']);
            $candidates[] = $email;
            $studentLocalSuffix = '@student.local';
            if (substr($email, -strlen($studentLocalSuffix)) === $studentLocalSuffix) {
                $candidates[] = substr($email, 0, -strlen($studentLocalSuffix));
            }
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            $stmt = $bdd->prepare("
                SELECT s.id, s.matricule, s.name, s.age, s.department_id,
                       d.name AS department_name, d.minerval_total
                FROM student s
                LEFT JOIN department d ON d.id = s.department_id
                WHERE s.matricule = ?
                LIMIT 1
            ");
            $stmt->execute([$candidate]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($student) {
                return $student;
            }
        }

        // Dernier recours pour les anciens comptes crees avec le nom complet comme seul lien.
        $stmt = $bdd->prepare("
            SELECT s.id, s.matricule, s.name, s.age, s.department_id,
                   d.name AS department_name, d.minerval_total
            FROM student s
            LEFT JOIN department d ON d.id = s.department_id
            WHERE LOWER(TRIM(s.name)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([(string) ($user['fullname'] ?? '')]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        return $student ?: null;
    }
}

$currentStudent = studentResolveCurrentStudent($bdd);
$studentId = $currentStudent ? (int) $currentStudent['id'] : null;

if ($currentStudent) {
    $_SESSION['student_id'] = $studentId;
    $_SESSION['student_matricule'] = $currentStudent['matricule'] ?? '';
} else {
    unset($_SESSION['student_id'], $_SESSION['student_matricule']);

    $currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($currentPage, ['student.php', 'profile.php'], true)) {
        header('Location: /payment/student/student.php?missing_profile=1');
        exit();
    }
}
?>
