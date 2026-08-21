<?php
// Dans functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Configuration SMTP (à centraliser) ---
$smtpHost = 'smtp.gmail.com';
$smtpPort = 587;
$smtpUsername = 'arakazaprincedestinyvan@gmail.com';
$smtpPassword = 'nahnnpxmjwxcbaua';
$smtpEncryption = 'tls';

/**
 * Envoie un email générique
 * @param string $toEmail Destinataire
 * @param string $toName Nom du destinataire
 * @param string $subject Sujet
 * @param string $bodyHTML Contenu HTML
 * @param string $altBody Contenu texte (optionnel)
 * @return bool Succès ou échec
 */
function sendEmail($toEmail, $toName, $subject, $bodyHTML, $altBody = '') {
    global $smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpEncryption;

    // Vérifier que PHPMailer est installé
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('PHPMailer not installed');
        return false;
    }
    require_once $autoload;

    try {
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
        $mail->Subject = $subject;
        $mail->Body = $bodyHTML;
        $mail->AltBody = $altBody ?: strip_tags($bodyHTML);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email error: ' . $e->getMessage());
        return false;
    }
}

function createNotification(PDO $bdd, int $userId, string $title, string $message, ?string $link = null): bool {
    if ($userId <= 0 || trim($title) === '' || trim($message) === '') {
        return false;
    }

    $stmt = $bdd->prepare("
        INSERT INTO notifications (user_id, title, message, link, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");

    return $stmt->execute([$userId, $title, $message, $link]);
}

function tableColumnExists(PDO $bdd, string $table, string $column): bool {
    $stmt = $bdd->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function getStudentByNameAndDepartment(PDO $bdd, string $fullName, string $department): ?array {
    $stmt = $bdd->prepare("
        SELECT matricule, student_name, department_name
        FROM vw_students_with_department
        WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?))
          AND LOWER(TRIM(department_name)) = LOWER(TRIM(?))
        ORDER BY matricule DESC
        LIMIT 1
    ");
    $stmt->execute([$fullName, $department]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    return $student ?: null;
}

function createStudentUserAccount(PDO $bdd, string $fullName, string $department): ?array {
    $student = getStudentByNameAndDepartment($bdd, $fullName, $department);
    if (!$student || empty($student['matricule'])) {
        return null;
    }

    $matricule = trim((string) $student['matricule']);
    $hasMatriculeColumn = tableColumnExists($bdd, 'user', 'matricule');

    if ($hasMatriculeColumn) {
        $existingStmt = $bdd->prepare("SELECT userId FROM user WHERE matricule = ? LIMIT 1");
        $existingStmt->execute([$matricule]);
    } else {
        $existingStmt = $bdd->prepare("SELECT userId FROM user WHERE email = ? LIMIT 1");
        $existingStmt->execute([$matricule]);
    }

    if ($existingStmt->fetch()) {
        return $student;
    }

    $hashedPassword = password_hash($matricule, PASSWORD_DEFAULT);

    if ($hasMatriculeColumn) {
        $email = $matricule . '@student.local';
        $stmt = $bdd->prepare("
            INSERT INTO user (fullname, email, password, role, matricule)
            VALUES (?, ?, ?, 'student', ?)
        ");
        $stmt->execute([$student['student_name'], $email, $hashedPassword, $matricule]);
    } else {
        $stmt = $bdd->prepare("
            INSERT INTO user (fullname, email, password, role)
            VALUES (?, ?, ?, 'student')
        ");
        $stmt->execute([$student['student_name'], $matricule, $hashedPassword]);
    }

    return $student;
}

function createAlert(
    PDO $bdd,
    ?int $userId,
    string $type,
    string $title,
    string $message,
    string $severity,
    ?string $link = null,
    ?string $sourceKey = null
): bool {
    $validSeverities = ['info', 'warning', 'important', 'danger'];
    if (!in_array($severity, $validSeverities, true)) {
        $severity = 'info';
    }

    if ($sourceKey !== null && $sourceKey !== '') {
        $duplicateSql = "
            SELECT id
            FROM alerts
            WHERE type = ?
              AND source_key = ?
              AND is_resolved = 0
              AND " . ($userId === null ? 'user_id IS NULL' : 'user_id = ?') . "
            LIMIT 1
        ";
        $duplicateParams = $userId === null ? [$type, $sourceKey] : [$type, $sourceKey, $userId];
    } else {
        $duplicateSql = "
            SELECT id
            FROM alerts
            WHERE type = ?
              AND is_resolved = 0
              AND " . ($userId === null ? 'user_id IS NULL' : 'user_id = ?') . "
            LIMIT 1
        ";
        $duplicateParams = $userId === null ? [$type] : [$type, $userId];
    }

    $duplicateStmt = $bdd->prepare($duplicateSql);
    $duplicateStmt->execute($duplicateParams);
    if ($duplicateStmt->fetchColumn()) {
        return false;
    }

    $stmt = $bdd->prepare("
        INSERT INTO alerts (user_id, source_key, type, title, message, severity, link, is_resolved, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ");

    return $stmt->execute([$userId, $sourceKey, $type, $title, $message, $severity, $link]);
}

function resolveMissingAlertSources(PDO $bdd, string $type, array $activeSourceKeys): void {
    $activeSourceKeys = array_values(array_unique(array_filter($activeSourceKeys, static function ($key) {
        return is_string($key) && $key !== '';
    })));

    if (!$activeSourceKeys) {
        $stmt = $bdd->prepare("
            UPDATE alerts
            SET is_resolved = 1, resolved_at = NOW()
            WHERE type = ?
              AND is_resolved = 0
        ");
        $stmt->execute([$type]);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($activeSourceKeys), '?'));
    $stmt = $bdd->prepare("
        UPDATE alerts
        SET is_resolved = 1, resolved_at = NOW()
        WHERE type = ?
          AND is_resolved = 0
          AND source_key IS NOT NULL
          AND source_key NOT IN ($placeholders)
    ");
    $stmt->execute(array_merge([$type], $activeSourceKeys));
}

function getSystemAlertSummary(PDO $bdd, ?int $userId = null, bool $includeGlobal = false): array {
    $sql = "
        SELECT severity, COUNT(*) AS total
        FROM alerts
        WHERE is_resolved = 0
    ";
    $params = [];

    if ($userId !== null && $includeGlobal) {
        $sql .= " AND (user_id = ? OR user_id IS NULL)";
        $params[] = $userId;
    } elseif ($userId !== null) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }

    $sql .= " GROUP BY severity";
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);

    $summary = ['info' => 0, 'warning' => 0, 'important' => 0, 'danger' => 0, 'total' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $severity = $row['severity'];
        $count = (int) $row['total'];
        $summary[$severity] = $count;
        $summary['total'] += $count;
    }

    return $summary;
}

function getSystemAlerts(PDO $bdd, ?int $userId = null, bool $includeGlobal = false, int $limit = 5): array {
    $sql = "
        SELECT id, user_id, type, title, message, severity, link, is_resolved, created_at, resolved_at
        FROM alerts
        WHERE is_resolved = 0
    ";
    $params = [];

    if ($userId !== null && $includeGlobal) {
        $sql .= " AND (user_id = ? OR user_id IS NULL)";
        $params[] = $userId;
    } elseif ($userId !== null) {
        $sql .= " AND user_id = ?";
        $params[] = $userId;
    }

    $sql .= "
        ORDER BY FIELD(severity, 'danger', 'important', 'warning', 'info'), created_at DESC
        LIMIT " . max(1, $limit);

    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function checkAlerts(PDO $bdd): void {
    $adminUsers = getAdminUsers($bdd);
    $adminIds = array_map(static fn (array $admin): int => (int) $admin['userId'], $adminUsers);

    $lateSources = [];
    $stmt = $bdd->query("
        SELECT p.id AS penalty_id, p.due_date, p.retard_jours,
               s.name AS student_name, s.matricule, u.userId AS student_user_id
        FROM penalite p
        JOIN student s ON s.id = p.student_id
        LEFT JOIN user u ON u.matricule = s.matricule AND u.role = 'student'
        WHERE DATEDIFF(CURDATE(), p.due_date) > 15
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceKey = 'late_payment:penalty:' . $row['penalty_id'];
        $lateSources[] = $sourceKey;
        $message = "Le paiement de {$row['student_name']} ({$row['matricule']}) dépasse l'échéance depuis {$row['retard_jours']} jours.";

        if (!empty($row['student_user_id'])) {
            createAlert($bdd, (int) $row['student_user_id'], 'late_payment', 'Paiement en retard', $message, 'danger', '/payment/student/payment.php', $sourceKey);
        }
        foreach ($adminIds as $adminId) {
            createAlert($bdd, $adminId, 'late_payment', 'Paiement en retard', $message, 'danger', '/payment/admin/penalty.php', $sourceKey);
        }
    }
    resolveMissingAlertSources($bdd, 'late_payment', $lateSources);

    $upcomingSources = [];
    $stmt = $bdd->query("
        SELECT s.id AS student_id, s.name AS student_name, s.matricule,
               t.id AS tranche_id, t.name AS tranche_name, t.date_fin, u.userId AS student_user_id
        FROM student s
        JOIN tranche t ON t.department_id = s.department_id
        LEFT JOIN payment p ON p.student_id = s.id AND p.tranche_id = t.id
        LEFT JOIN user u ON u.matricule = s.matricule AND u.role = 'student'
        WHERE p.id IS NULL
          AND DATEDIFF(t.date_fin, CURDATE()) BETWEEN 1 AND 5
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (empty($row['student_user_id'])) {
            continue;
        }

        $sourceKey = 'upcoming_due:student:' . $row['student_id'] . ':tranche:' . $row['tranche_id'];
        $upcomingSources[] = $sourceKey;
        $message = "La tranche {$row['tranche_name']} arrive à échéance le {$row['date_fin']}.";
        createAlert($bdd, (int) $row['student_user_id'], 'upcoming_due', 'Échéance approchante', $message, 'warning', '/payment/student/payment.php', $sourceKey);
    }
    resolveMissingAlertSources($bdd, 'upcoming_due', $upcomingSources);

    $examSources = [];
    $stmt = $bdd->query("
        SELECT p.id AS penalty_id, p.retard_jours,
               s.name AS student_name, s.matricule, u.userId AS student_user_id
        FROM penalite p
        JOIN student s ON s.id = p.student_id
        LEFT JOIN user u ON u.matricule = s.matricule AND u.role = 'student'
        WHERE p.retard_jours > 60 OR p.exam_acces = 0
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceKey = 'exam_access_lost:penalty:' . $row['penalty_id'];
        $examSources[] = $sourceKey;
        $message = "{$row['student_name']} ({$row['matricule']}) a perdu l'accès aux examens après {$row['retard_jours']} jours de retard.";

        if (!empty($row['student_user_id'])) {
            createAlert($bdd, (int) $row['student_user_id'], 'exam_access_lost', 'Accès aux examens perdu', $message, 'danger', '/payment/student/penalty.php', $sourceKey);
        }
        foreach ($adminIds as $adminId) {
            createAlert($bdd, $adminId, 'exam_access_lost', 'Accès aux examens perdu', $message, 'danger', '/payment/admin/penalty.php', $sourceKey);
        }
    }
    resolveMissingAlertSources($bdd, 'exam_access_lost', $examSources);

    $highPenaltySources = [];
    $stmt = $bdd->query("
        SELECT p.id AS penalty_id, p.montant_penalite, d.minerval_total,
               s.name AS student_name, s.matricule
        FROM penalite p
        JOIN student s ON s.id = p.student_id
        JOIN department d ON d.id = s.department_id
        WHERE p.montant_penalite > (d.minerval_total * 0.2)
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceKey = 'high_penalty:penalty:' . $row['penalty_id'];
        $highPenaltySources[] = $sourceKey;
        $amount = number_format((float) $row['montant_penalite'], 2, ',', ' ');
        $message = "La pénalité de {$row['student_name']} ({$row['matricule']}) atteint {$amount} BIF, soit plus de 20% du minerval.";

        foreach ($adminIds as $adminId) {
            createAlert($bdd, $adminId, 'high_penalty', 'Pénalité élevée', $message, 'warning', '/payment/admin/penalty.php', $sourceKey);
        }
    }
    resolveMissingAlertSources($bdd, 'high_penalty', $highPenaltySources);

    $unpaidTrancheSources = [];
    $stmt = $bdd->query("
        SELECT t.id AS tranche_id, t.name AS tranche_name, t.date_fin, d.name AS department_name
        FROM tranche t
        JOIN department d ON d.id = t.department_id
        WHERE t.date_fin <= CURDATE()
          AND NOT EXISTS (
              SELECT 1
              FROM payment p
              WHERE p.tranche_id = t.id
              LIMIT 1
          )
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceKey = 'unpaid_tranche:tranche:' . $row['tranche_id'];
        $unpaidTrancheSources[] = $sourceKey;
        $message = "La tranche {$row['tranche_name']} du département {$row['department_name']} n'a aucun paiement enregistré après l'échéance du {$row['date_fin']}.";

        foreach ($adminIds as $adminId) {
            createAlert($bdd, $adminId, 'unpaid_tranche', 'Tranche non payée', $message, 'important', '/payment/admin/payment.php', $sourceKey);
        }
    }
    resolveMissingAlertSources($bdd, 'unpaid_tranche', $unpaidTrancheSources);

    $minervalSources = [];
    $stmt = $bdd->query("
        SELECT s.id AS student_id, s.name AS student_name, s.matricule,
               d.name AS department_name, d.minerval_total,
               COALESCE(SUM(p.amount), 0) AS paid_total
        FROM student s
        JOIN department d ON d.id = s.department_id
        LEFT JOIN payment p ON p.student_id = s.id
        GROUP BY s.id, s.name, s.matricule, d.name, d.minerval_total
        HAVING paid_total < d.minerval_total
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sourceKey = 'minerval_not_reached:student:' . $row['student_id'];
        $minervalSources[] = $sourceKey;
        $paid = number_format((float) $row['paid_total'], 2, ',', ' ');
        $expected = number_format((float) $row['minerval_total'], 2, ',', ' ');
        $message = "{$row['student_name']} ({$row['matricule']}) a payé {$paid} BIF sur {$expected} BIF pour {$row['department_name']}.";

        foreach ($adminIds as $adminId) {
            createAlert($bdd, $adminId, 'minerval_not_reached', 'Minerval non atteint', $message, 'warning', '/payment/admin/payment.php', $sourceKey);
        }
    }
    resolveMissingAlertSources($bdd, 'minerval_not_reached', $minervalSources);
}

function getAdminUsers(PDO $bdd): array {
    $stmt = $bdd->prepare("
        SELECT userId, fullname, email
        FROM user
        WHERE role = ?
          AND is_locked = 0
          AND email IS NOT NULL
          AND email <> ''
    ");
    $stmt->execute(['admin']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function findUserEmailByFullname(PDO $bdd, string $fullname): ?array {
    $stmt = $bdd->prepare("
        SELECT userId, fullname, email
        FROM user
        WHERE LOWER(TRIM(fullname)) = LOWER(TRIM(?))
          AND email IS NOT NULL
          AND email <> ''
        LIMIT 1
    ");
    $stmt->execute([$fullname]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function createPaymentInAppNotification(PDO $bdd, string $matricule, string $referenceNumber): void {
    $stmt = $bdd->prepare("
        SELECT payment_reference, student_name, matricule, tranche_name, amount, reference_number
        FROM vw_payment_details
        WHERE matricule = ?
          AND reference_number = ?
        ORDER BY payment_date DESC
        LIMIT 1
    ");
    $stmt->execute([$matricule, $referenceNumber]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        return;
    }

    $studentUser = findUserEmailByFullname($bdd, $payment['student_name']);
    if (!$studentUser) {
        return;
    }

    $amount = number_format((float) $payment['amount'], 2, ',', ' ');
    createNotification(
        $bdd,
        (int) $studentUser['userId'],
        'Paiement enregistré',
        "Votre paiement {$payment['payment_reference']} de {$amount} BIF pour {$payment['tranche_name']} a été enregistré.",
        '/payment/student/payment.php'
    );
}

function createPenaltyInAppNotification(PDO $bdd, string $paymentReference): void {
    $stmt = $bdd->prepare("
        SELECT penalite_reference, payment_reference, matricule, student_name,
               retard_jours, montant_penalite
        FROM vw_penalites
        WHERE payment_reference = ?
        ORDER BY penalite_created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$paymentReference]);
    $penalty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$penalty) {
        return;
    }

    $studentUser = findUserEmailByFullname($bdd, $penalty['student_name']);
    if (!$studentUser) {
        return;
    }

    $amount = number_format((float) $penalty['montant_penalite'], 2, ',', ' ');
    createNotification(
        $bdd,
        (int) $studentUser['userId'],
        'Pénalité appliquée',
        "Une pénalité de {$amount} BIF a été appliquée à votre paiement {$penalty['payment_reference']} après {$penalty['retard_jours']} jours de retard.",
        '/payment/student/penalty.php'
    );
}

function createNewStudentAdminInAppNotifications(PDO $bdd, string $fullName, string $department): void {
    $studentStmt = $bdd->prepare("
        SELECT matricule, student_name, department_name
        FROM vw_students_with_department
        WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?))
          AND LOWER(TRIM(department_name)) = LOWER(TRIM(?))
        ORDER BY matricule DESC
        LIMIT 1
    ");
    $studentStmt->execute([$fullName, $department]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'matricule' => 'N/A',
        'student_name' => $fullName,
        'department_name' => $department,
    ];

    foreach (getAdminUsers($bdd) as $admin) {
        createNotification(
            $bdd,
            (int) $admin['userId'],
            'Nouvel étudiant',
            "{$student['student_name']} ({$student['matricule']}) a été ajouté dans {$student['department_name']}.",
            '/payment/admin/student.php'
        );
    }
}

function createPasswordResetAdminNotifications(PDO $bdd, int $resetUserId): void {
    $userStmt = $bdd->prepare("SELECT fullname, email FROM user WHERE userId = ? LIMIT 1");
    $userStmt->execute([$resetUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return;
    }

    foreach (getAdminUsers($bdd) as $admin) {
        createNotification(
            $bdd,
            (int) $admin['userId'],
            'Mot de passe réinitialisé',
            "Le mot de passe du compte {$user['fullname']} ({$user['email']}) a été réinitialisé.",
            '/payment/admin/activity_log.php'
        );
    }
}

function sendPaymentCreatedNotification(PDO $bdd, string $matricule, string $referenceNumber): void {
    $stmt = $bdd->prepare("
        SELECT payment_reference, student_name, matricule, department_name, tranche_name,
               amount, payment_method, reference_number, payment_date
        FROM vw_payment_details
        WHERE matricule = ?
          AND reference_number = ?
        ORDER BY payment_date DESC
        LIMIT 1
    ");
    $stmt->execute([$matricule, $referenceNumber]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        error_log("Payment email skipped: payment details not found for matricule {$matricule}, reference {$referenceNumber}");
        return;
    }

    $studentUser = findUserEmailByFullname($bdd, $payment['student_name']);
    if (!$studentUser) {
        error_log("Payment email skipped: no user email found for student {$payment['student_name']} ({$matricule})");
        return;
    }

    $amount = number_format((float) $payment['amount'], 2, ',', ' ');
    $subject = 'Confirmation de paiement - ULT Payment System';
    $body = '
        <p>Bonjour ' . htmlspecialchars($payment['student_name'], ENT_QUOTES, 'UTF-8') . ',</p>
        <p>Votre paiement a été enregistré avec succès.</p>
        <ul>
            <li><strong>Référence paiement :</strong> ' . htmlspecialchars($payment['payment_reference'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Référence externe :</strong> ' . htmlspecialchars($payment['reference_number'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Matricule :</strong> ' . htmlspecialchars($payment['matricule'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Département :</strong> ' . htmlspecialchars($payment['department_name'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Tranche :</strong> ' . htmlspecialchars($payment['tranche_name'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Montant :</strong> ' . $amount . ' BIF</li>
            <li><strong>Méthode :</strong> ' . htmlspecialchars($payment['payment_method'], ENT_QUOTES, 'UTF-8') . '</li>
        </ul>
        <p>Merci d\'utiliser ULT Payment System.</p>
    ';

    if (!sendEmail($studentUser['email'], $payment['student_name'], $subject, $body)) {
        error_log("Payment email failed for {$studentUser['email']} - payment {$payment['payment_reference']}");
    }
}

function sendPenaltyCreatedNotification(PDO $bdd, string $paymentReference): void {
    $stmt = $bdd->prepare("
        SELECT penalite_reference, payment_reference, matricule, student_name,
               payment_amount, retard_jours, pourcentage_penalite,
               montant_penalite, penalite_created_at
        FROM vw_penalites
        WHERE payment_reference = ?
        ORDER BY penalite_created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$paymentReference]);
    $penalty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$penalty) {
        return;
    }

    $studentUser = findUserEmailByFullname($bdd, $penalty['student_name']);
    if (!$studentUser) {
        error_log("Penalty email skipped: no user email found for student {$penalty['student_name']} ({$penalty['matricule']})");
        return;
    }

    $penaltyAmount = number_format((float) $penalty['montant_penalite'], 2, ',', ' ');
    $paymentAmount = number_format((float) $penalty['payment_amount'], 2, ',', ' ');
    $subject = 'Alerte pénalité - ULT Payment System';
    $body = '
        <p>Bonjour ' . htmlspecialchars($penalty['student_name'], ENT_QUOTES, 'UTF-8') . ',</p>
        <p>Une pénalité a été appliquée à votre paiement.</p>
        <ul>
            <li><strong>Référence pénalité :</strong> ' . htmlspecialchars($penalty['penalite_reference'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Référence paiement :</strong> ' . htmlspecialchars($penalty['payment_reference'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Matricule :</strong> ' . htmlspecialchars($penalty['matricule'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Montant paiement :</strong> ' . $paymentAmount . ' BIF</li>
            <li><strong>Retard :</strong> ' . htmlspecialchars($penalty['retard_jours'], ENT_QUOTES, 'UTF-8') . ' jours</li>
            <li><strong>Taux :</strong> ' . number_format((float) $penalty['pourcentage_penalite'], 2, ',', ' ') . '%</li>
            <li><strong>Montant pénalité :</strong> ' . $penaltyAmount . ' BIF</li>
        </ul>
        <p>Veuillez régulariser votre situation auprès de l\'administration.</p>
    ';

    if (!sendEmail($studentUser['email'], $penalty['student_name'], $subject, $body)) {
        error_log("Penalty email failed for {$studentUser['email']} - penalty {$penalty['penalite_reference']}");
    }
}

function sendNewStudentAdminNotification(PDO $bdd, string $fullName, string $department): void {
    $studentStmt = $bdd->prepare("
        SELECT matricule, student_name, department_name
        FROM vw_students_with_department
        WHERE LOWER(TRIM(student_name)) = LOWER(TRIM(?))
          AND LOWER(TRIM(department_name)) = LOWER(TRIM(?))
        ORDER BY matricule DESC
        LIMIT 1
    ");
    $studentStmt->execute([$fullName, $department]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'matricule' => 'N/A',
        'student_name' => $fullName,
        'department_name' => $department,
    ];

    $adminStmt = $bdd->prepare("
        SELECT fullname, email
        FROM user
        WHERE role = ?
          AND email IS NOT NULL
          AND email <> ''
    ");
    $adminStmt->execute(['admin']);
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$admins) {
        error_log("New student email skipped: no admin email found for student {$fullName}");
        return;
    }

    $subject = 'Nouvel étudiant enregistré - ULT Payment System';
    $body = '
        <p>Bonjour,</p>
        <p>Un nouvel étudiant vient d\'être enregistré.</p>
        <ul>
            <li><strong>Nom :</strong> ' . htmlspecialchars($student['student_name'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Matricule :</strong> ' . htmlspecialchars($student['matricule'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Département :</strong> ' . htmlspecialchars($student['department_name'], ENT_QUOTES, 'UTF-8') . '</li>
        </ul>
    ';

    foreach ($admins as $admin) {
        if (!sendEmail($admin['email'], $admin['fullname'], $subject, $body)) {
            error_log("New student email failed for admin {$admin['email']} - student {$fullName}");
        }
    }
}

function sendAccountLockedAdminNotification(PDO $bdd, int $lockedUserId): void {
    $userStmt = $bdd->prepare("
        SELECT userId, fullname, email, failed_attempts, last_failed_attempt
        FROM user
        WHERE userId = ?
        LIMIT 1
    ");
    $userStmt->execute([$lockedUserId]);
    $lockedUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$lockedUser) {
        return;
    }

    $adminStmt = $bdd->prepare("
        SELECT fullname, email
        FROM user
        WHERE role = ?
          AND is_locked = 0
          AND email IS NOT NULL
          AND email <> ''
    ");
    $adminStmt->execute(['admin']);
    $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$admins) {
        error_log("Account lock email skipped: no active admin email found for user {$lockedUserId}");
        return;
    }

    $subject = 'Compte verrouillé - ULT Payment System';
    $body = '
        <p>Bonjour,</p>
        <p>Un compte utilisateur a été verrouillé automatiquement après plusieurs tentatives de connexion infructueuses.</p>
        <ul>
            <li><strong>Nom :</strong> ' . htmlspecialchars($lockedUser['fullname'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Email :</strong> ' . htmlspecialchars($lockedUser['email'], ENT_QUOTES, 'UTF-8') . '</li>
            <li><strong>Tentatives échouées :</strong> ' . (int) $lockedUser['failed_attempts'] . '</li>
            <li><strong>Dernière tentative :</strong> ' . htmlspecialchars((string) $lockedUser['last_failed_attempt'], ENT_QUOTES, 'UTF-8') . '</li>
        </ul>
        <p>Vous pouvez déverrouiller ce compte depuis l\'interface d\'administration.</p>
    ';

    foreach ($admins as $admin) {
        if (!sendEmail($admin['email'], $admin['fullname'], $subject, $body)) {
            error_log("Account lock email failed for admin {$admin['email']} - user {$lockedUserId}");
        }
    }
}

/**
 * Récupère l'adresse IP du client
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Récupère le User Agent du client
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Enregistre une connexion dans login_history
 * @param PDO $bdd
 * @param int $userId
 * @param string $email
 * @return int L'ID de la ligne insérée
 */
function logLogin($bdd, $userId, $email) {
    $ip = getClientIP();
    $userAgent = getUserAgent();
    
    $stmt = $bdd->prepare("INSERT INTO login_history (userId, email, ip, user_agent, login_time) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $email, $ip, $userAgent]);
    
    return $bdd->lastInsertId();
}

function logActivity($bdd, $userId, $fullname, $email, $action, $details = null) {
    $ip = getClientIP();
    $stmt = $bdd->prepare("INSERT INTO activity_logs (userId, action, details, ip, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$userId, $action, $details, $ip]);
}

/**
 * Met à jour l'heure de déconnexion et calcule la durée
 * @param PDO $bdd
 * @param int $userId
 */
function logLogout($bdd, $userId) {
    // Récupère la dernière session non terminée
    $stmt = $bdd->prepare("SELECT id, login_time FROM login_history WHERE userId = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1");
    $stmt->execute([$userId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        $logoutTime = date('Y-m-d H:i:s');
        $duration = strtotime($logoutTime) - strtotime($session['login_time']);
        
        $update = $bdd->prepare("UPDATE login_history SET logout_time = ?, session_duration = ? WHERE id = ?");
        $update->execute([$logoutTime, $duration, $session['id']]);
    }
}
