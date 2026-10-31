<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');
ini_set('memory_limit', '256M');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$month = $_GET['month'] ?? date('Y-m');
$first = $month . '-01';
$last  = date('Y-m-t', strtotime($first));

// --- Récupération des paiements du mois ---
try {
    $stmt = $bdd->prepare("
        SELECT p.id, s.name AS student_name, d.name AS department_name,
               t.name AS tranche_name, p.amount, p.payment_method,
               p.reference_number, p.created_at AS payment_date
        FROM payment p
        JOIN student s ON p.student_id = s.id
        JOIN tranche t ON p.tranche_id = t.id
        JOIN department d ON t.department_id = d.id
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$first, $last]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des paiements : ' . $e->getMessage());
}

// --- Calcul des statistiques ---
$totalCount = count($rows);
$totalAmount = 0.0;
$byMethod = [];
$byDept = [];

foreach ($rows as $r) {
    $amount = (float) $r['amount'];
    $totalAmount += $amount;

    $method = $r['payment_method'] ?: 'OTHER';
    if (!isset($byMethod[$method])) {
        $byMethod[$method] = ['count' => 0, 'total' => 0.0];
    }
    $byMethod[$method]['count']++;
    $byMethod[$method]['total'] += $amount;

    $dept = $r['department_name'] ?? 'Inconnu';
    if (!isset($byDept[$dept])) {
        $byDept[$dept] = ['count' => 0, 'total' => 0.0];
    }
    $byDept[$dept]['count']++;
    $byDept[$dept]['total'] += $amount;
}

// --- Pénalités du mois ---
$penStmt = $bdd->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(montant_penalite),0) AS t 
                         FROM penalite 
                         WHERE DATE(created_at) BETWEEN ? AND ?");
$penStmt->execute([$first, $last]);
$pen = $penStmt->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 't' => 0];

// --- Nouveaux étudiants du mois ---
$stuStmt = $bdd->prepare("SELECT COUNT(*) FROM student WHERE DATE(created_at) BETWEEN ? AND ?");
$stuStmt->execute([$first, $last]);
$newStudents = (int) $stuStmt->fetchColumn();

// --- Génération PDF ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    if (ob_get_length()) ob_end_clean();

    // ⚠️ CORRECTION : Charger l'autoloader Composer AVANT d'utiliser Dompdf
    require_once __DIR__ . '/../vendor/autoload.php';

    $html = '<h1 style="color:#1e3a8a;">Rapport périodique - ' . date('F Y', strtotime($first)) . '</h1>';
    $html .= '<p>Période : ' . date('d/m/Y', strtotime($first)) . ' au ' . date('d/m/Y', strtotime($last)) . '</p>';

    $html .= '<h2>Paiements du mois</h2>';
    $html .= '<p>Nombre : ' . $totalCount . ' - Total : ' . number_format($totalAmount, 0, ',', ' ') . ' BIF</p>';

    $html .= '<h2>Par mode de paiement</h2>';
    $html .= '<table border="1" cellpadding="5" style="border-collapse:collapse;width:100%;">';
    $html .= '<tr><th>Mode</th><th>Nombre</th><th>Total</th></tr>';
    foreach ($byMethod as $m => $v) {
        $html .= '<tr><td>' . htmlspecialchars($m) . '</td><td>' . $v['count'] . '</td><td>' . number_format($v['total'], 0, ',', ' ') . ' BIF</td></tr>';
    }
    $html .= '</table>';

    $html .= '<h2>Par département</h2>';
    $html .= '<table border="1" cellpadding="5" style="border-collapse:collapse;width:100%;">';
    $html .= '<tr><th>Département</th><th>Nombre</th><th>Total</th></tr>';
    foreach ($byDept as $dept => $v) {
        $html .= '<tr><td>' . htmlspecialchars($dept) . '</td><td>' . $v['count'] . '</td><td>' . number_format($v['total'], 0, ',', ' ') . ' BIF</td></tr>';
    }
    $html .= '</table>';

    $html .= '<h2>Pénalités</h2>';
    $html .= '<p>Nombre : ' . (int)$pen['c'] . ' - Total : ' . number_format((float)$pen['t'], 0, ',', ' ') . ' BIF</p>';

    $html .= '<h2>Nouveaux étudiants</h2>';
    $html .= '<p>' . $newStudents . '</p>';

    try {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="rapport_periodique_' . $month . '.pdf"');
        echo $dompdf->output();
        exit();
    } catch (Throwable $e) {
        die('Erreur Dompdf : ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Rapport périodique - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .report-layout { display: flex; flex-direction: column; gap: 24px; }
        .month-bar {
            display: flex; flex-wrap: wrap; align-items: end; gap: 16px;
            background: #fff; padding: 20px; border-radius: var(--radius);
            box-shadow: var(--shadow); border: 1px solid var(--gray-200);
        }
        .month-bar .form-group { margin: 0; min-width: 220px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .kpi-card {
            background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius);
            padding: 20px; display: flex; flex-direction: column; gap: 8px;
            box-shadow: var(--shadow);
        }
        .kpi-label { font-size: 0.85rem; color: var(--gray-600); font-weight: 500; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; color: var(--primary); line-height: 1.2; }
        .kpi-sub { font-size: 0.8rem; color: var(--gray-600); }
        .two-tables { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .two-tables { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Rapport périodique</h1>

            <div class="report-layout">
                <div class="month-bar">
                    <form method="POST" action="periodic_reports.php?month=<?= htmlspecialchars($month) ?>" style="display:flex; align-items:end; gap:16px; flex-wrap:wrap; width:100%;">
                        <div class="form-group">
                            <label for="month">Mois</label>
                            <input type="month" id="month" name="month" value="<?= htmlspecialchars($month) ?>" required>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="btn btn-primary">Générer PDF</button>
                            <a href="?month=<?= htmlspecialchars($month) ?>" class="btn btn-secondary">Actualiser</a>
                        </div>
                        <input type="hidden" name="action" value="generate">
                    </form>
                </div>

                <div class="kpi-grid">
                    <div class="kpi-card">
                        <span class="kpi-label">Paiements du mois</span>
                        <span class="kpi-value"><?= $totalCount ?></span>
                        <span class="kpi-sub"><?= number_format($totalAmount, 0, ',', ' ') ?> BIF</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Pénalités</span>
                        <span class="kpi-value"><?= (int)$pen['c'] ?></span>
                        <span class="kpi-sub"><?= number_format((float)$pen['t'], 0, ',', ' ') ?> BIF</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Nouveaux étudiants</span>
                        <span class="kpi-value"><?= $newStudents ?></span>
                        <span class="kpi-sub">inscrits ce mois</span>
                    </div>
                </div>

                <div class="two-tables">
                    <div class="page-card">
                        <h2>Par mode de paiement</h2>
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr><th>Mode</th><th>Nombre</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($byMethod)): ?>
                                        <?php foreach ($byMethod as $m => $d): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($m) ?></td>
                                                <td><?= $d['count'] ?></td>
                                                <td><?= number_format($d['total'], 0, ',', ' ') ?> BIF</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" style="text-align:center; padding:16px;">Aucune donnée</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="page-card">
                        <h2>Par département</h2>
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr><th>Département</th><th>Nombre</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($byDept)): ?>
                                        <?php foreach ($byDept as $dept => $v): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dept) ?></td>
                                                <td><?= $v['count'] ?></td>
                                                <td><?= number_format($v['total'], 0, ',', ' ') ?> BIF</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" style="text-align:center; padding:16px;">Aucune donnée</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
