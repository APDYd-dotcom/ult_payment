<?php
require_once __DIR__ . '/auth_check.php';

$message = '';
$messageType = '';
$partialPayments = [];
$payments = [];

$tableExistsStmt = $bdd->prepare("
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'partial_payment'
");
$tableExistsStmt->execute();
$partialTableExists = (int) $tableExistsStmt->fetchColumn() > 0;

function studentPartialPaymentBelongsToStudent(PDO $bdd, int $partialId, int $studentId): bool
{
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM partial_payment WHERE id = ? AND student_id = ?');
    $stmt->execute([$partialId, $studentId]);
    return (int) $stmt->fetchColumn() === 1;
}

function studentPartialPaymentPaymentBelongsToStudent(PDO $bdd, int $paymentId, int $studentId): bool
{
    $stmt = $bdd->prepare('SELECT COUNT(*) FROM payment WHERE id = ? AND student_id = ?');
    $stmt->execute([$paymentId, $studentId]);
    return (int) $stmt->fetchColumn() === 1;
}

if ($partialTableExists && isset($_GET['delete'])) {
    $partialId = (int) $_GET['delete'];
    try {
        if ($partialId <= 0 || !studentPartialPaymentBelongsToStudent($bdd, $partialId, $studentId)) {
            throw new RuntimeException('Ce paiement partiel ne peut pas etre supprime.');
        }

        $stmt = $bdd->prepare('DELETE FROM partial_payment WHERE id = ? AND student_id = ?');
        $stmt->execute([$partialId, $studentId]);

        logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_partial_payment_deleted', "Partial ID: {$partialId}");
        header('Location: partial.php?success=deleted');
        exit();
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

if ($partialTableExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $expectedAmount = (float) ($_POST['expected_amount'] ?? 0);
    $paidAmount = (float) ($_POST['paid_amount'] ?? 0);
    $missingAmount = max($expectedAmount - $paidAmount, 0);

    try {
        if ($paymentId <= 0 || $expectedAmount <= 0 || $paidAmount < 0) {
            throw new RuntimeException('Tous les champs sont obligatoires et les montants doivent etre valides.');
        }
        if (!studentPartialPaymentPaymentBelongsToStudent($bdd, $paymentId, $studentId)) {
            throw new RuntimeException('Le paiement selectionne ne vous appartient pas.');
        }

        if (isset($_POST['Create'])) {
            $duplicateStmt = $bdd->prepare('SELECT COUNT(*) FROM partial_payment WHERE payment_id = ? AND student_id = ?');
            $duplicateStmt->execute([$paymentId, $studentId]);
            if ((int) $duplicateStmt->fetchColumn() > 0) {
                throw new RuntimeException('Un paiement partiel existe deja pour ce paiement.');
            }

            $stmt = $bdd->prepare("
                INSERT INTO partial_payment (student_id, payment_id, expected_amount, paid_amount, missing_amount)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$studentId, $paymentId, $expectedAmount, $paidAmount, $missingAmount]);
            $partialId = (int) $bdd->lastInsertId();

            logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_partial_payment_created', "Partial ID: {$partialId}, Payment ID: {$paymentId}");
            header('Location: partial.php?success=created');
            exit();
        }

        if (isset($_POST['Update'])) {
            $partialId = (int) ($_POST['partial_id'] ?? 0);
            if ($partialId <= 0 || !studentPartialPaymentBelongsToStudent($bdd, $partialId, $studentId)) {
                throw new RuntimeException('Ce paiement partiel ne peut pas etre modifie.');
            }

            $stmt = $bdd->prepare("
                UPDATE partial_payment
                SET payment_id = ?, expected_amount = ?, paid_amount = ?, missing_amount = ?
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$paymentId, $expectedAmount, $paidAmount, $missingAmount, $partialId, $studentId]);

            logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'student_partial_payment_updated', "Partial ID: {$partialId}, Payment ID: {$paymentId}");
            header('Location: partial.php?success=updated');
            exit();
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

if (isset($_GET['success'])) {
    $messages = [
        'created' => 'Paiement partiel cree avec succes.',
        'updated' => 'Paiement partiel mis a jour avec succes.',
        'deleted' => 'Paiement partiel supprime avec succes.',
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = 'success';
}

if ($partialTableExists) {
    try {
        $paymentStmt = $bdd->prepare("
            SELECT p.id, p.amount, p.reference_number, p.created_at, t.name AS tranche_name
            FROM payment p
            LEFT JOIN tranche t ON t.id = p.tranche_id
            WHERE p.student_id = ?
            ORDER BY p.created_at DESC, p.id DESC
        ");
        $paymentStmt->execute([$studentId]);
        $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $bdd->prepare("
            SELECT pp.id, pp.payment_id, pp.expected_amount, pp.paid_amount, pp.missing_amount, pp.created_at,
                   p.reference_number, t.name AS tranche_name
            FROM partial_payment pp
            LEFT JOIN payment p ON p.id = pp.payment_id
            LEFT JOIN tranche t ON t.id = p.tranche_id
            WHERE pp.student_id = ?
            ORDER BY pp.created_at DESC, pp.id DESC
        ");
        $stmt->execute([$studentId]);
        $partialPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die('Erreur lors du chargement des paiements partiels : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiements partiels - ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.2">
    <style>
        .message{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-weight:500}
        .message-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .message-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .notice{background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);color:#1e293b}
        select{padding:10px;margin-top:5px;border:1px solid #ccc;border-radius:6px}
        .actions{display:flex;gap:8px;flex-wrap:wrap}
        .action-link{display:inline-block;padding:8px 10px;border-radius:6px;text-decoration:none;color:#fff;background:#dc2626}
        .edit-panel{display:none;background:#f8fafc}
        .edit-panel form{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end}
        .muted{color:#64748b;font-size:.9rem}
        @media(max-width:900px){.table-section{overflow-x:auto}}
    </style>
</head>
<body>
<div class="container">
    <aside id="sidebar" class="sidebar"><?php include 'sidebar.php'; ?></aside>
    <main id="main-content" class="main-content">
        <section id="partial" class="page active">
            <h1 class="page-title">Paiements partiels</h1>

            <?php if ($message): ?>
                <div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (!$partialTableExists): ?>
                <div class="notice">La table des paiements partiels n existe pas encore dans cette base de donnees.</div>
            <?php else: ?>
                <div class="crud-container">
                    <div class="table-section">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reference partielle</th>
                                    <th>Reference paiement</th>
                                    <th>Tranche</th>
                                    <th>Montant attendu</th>
                                    <th>Montant paye</th>
                                    <th>Reste a payer</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($partialPayments): ?>
                                <?php foreach ($partialPayments as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars('PART-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars('PAY-' . str_pad((string) $row['payment_id'], 5, '0', STR_PAD_LEFT), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['tranche_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= number_format((float) $row['expected_amount'], 2, ',', ' ') ?> BIF</td>
                                        <td><?= number_format((float) $row['paid_amount'], 2, ',', ' ') ?> BIF</td>
                                        <td><?= number_format((float) $row['missing_amount'], 2, ',', ' ') ?> BIF</td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8">Aucun paiement partiel trouve.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<script>
function toggleEdit(id) {
    const row = document.getElementById('edit-' + id);
    if (row) {
        row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
    }
}
</script>
</body>
</html>
