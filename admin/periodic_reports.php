<?php
declare(strict_types=1); // <-- DOIT ETRE LA PREMIERE LIGNE
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);


define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/report_helpers.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$month = trim($_POST['month'] ?? $_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$parts = explode('-', $month);
$year = (int) $parts[0];
$mon = (int) $parts[1];
$first = sprintf('%04d-%02d-01', $year, $mon);
$last = date('Y-m-t', strtotime($first));

$filters = ['date_from' => $first, 'date_to' => $last, 'department' => '', 'tranche' => '', 'method' => '', 'q' => '', 'period' => ''];
$rows = getPaymentReport($bdd, $filters);
$summary = summarizeReport($rows);

// paiements par departement
$byDept = [];
foreach ($rows as $r) {
    $d = $r['department_name'] ?? 'Inconnu';
    if (!isset($byDept[$d])) {
        $byDept[$d] = ['count' => 0, 'total' => 0.0];
    }
    $byDept[$d]['count']++;
    $byDept[$d]['total'] += (float) $r['amount'];
}

// penalites du mois
$penStmt = $bdd->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(montant_penalite),0) AS t FROM penalite WHERE DATE(created_at) BETWEEN ? AND ?");
$penStmt->execute([$first, $last]);
$pen = $penStmt->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 't' => 0];

// nouveaux etudiants
$stuStmt = $bdd->prepare("SELECT COUNT(*) FROM student WHERE created_at BETWEEN ? AND ?");
$stuStmt->execute([$first . ' 00:00:00', $last . ' 23:59:59']);
$newStudents = (int) $stuStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    
    // On nettoie tout ce qui a pu être affiché avant
    if (ob_get_length()) ob_end_clean();

    $html = '<h1 style="color:#1e3a8a; text-align:center;">Rapport Périodique</h1>';
    $html .= '<p>Ceci est un test de génération PDF.</p>';

    try {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'rapport_periodique_test.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit();
    } catch (Throwable $e) {
        die('Erreur Dompdf : ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Rapport periodique - ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head>
<body>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <section class="page active">
            <h1 class="page-title">Rapport periodique</h1>
        <form method="POST" class="form-section">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="generate">
            <label>Mois</label>
            <input type="month" name="month" value="<?= htmlspecialchars($month) ?>">
            <button class="btn-primary" type="submit">Generer PDF</button>
        </form>
        <div class="table-section">
            <h2>Paiements du mois</h2>
            <p>Nombre: <?= (int)$summary['count'] ?> - Total: <?= number_format($summary['total'], 0, ',', ' ') ?></p>
            <div class="table-responsive">
<table><tr><th>Mode</th><th>Nombre</th><th>Total</th></tr>
            <?php foreach ($summary['by_method'] as $m => $d): ?>
            <tr><td><?= htmlspecialchars($m) ?></td><td><?= (int)$d['count'] ?></td><td><?= number_format($d['total'], 0, ',', ' ') ?></td></tr>
            <?php endforeach; ?>
            </table>
</div>
            <h3>Par departement</h3>
            <div class="table-responsive">
<table><tr><th>Departement</th><th>Nombre</th><th>Total</th></tr>
            <?php foreach ($byDept as $d => $v): ?>
            <tr><td><?= htmlspecialchars($d) ?></td><td><?= (int)$v['count'] ?></td><td><?= number_format($v['total'], 0, ',', ' ') ?></td></tr>
            <?php endforeach; ?>
            </table>
</div>
            <p>Penalites: <?= (int)$pen['c'] ?> - Total: <?= number_format((float)$pen['t'], 0, ',', ' ') ?></p>
            <p>Nouveaux etudiants: <?= (int)$newStudents ?></p>
        </div>
    </main>
</div>
    <script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
