<?php
// Dans functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



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

function findUserEmailByFullname(PDO $bdd, string $fullname): ?array {
    $stmt = $bdd->prepare("
        SELECT fullname, email
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
