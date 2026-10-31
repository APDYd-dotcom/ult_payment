<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');
ini_set('memory_limit', '256M');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

// --- Récupération des filtres ---
$filterDepartment = $_GET['department'] ?? '';
$filterTranche    = $_GET['tranche'] ?? '';
$filterDateStart  = $_GET['date_start'] ?? '';
$filterDateEnd    = $_GET['date_end'] ?? '';
$filterMethod     = $_GET['payment_method'] ?? '';

// --- Construction de la requête (sans vues) ---
$sql = "
    SELECT 
        CONCAT('P-', LPAD(p.id, 4, '0')) AS payment_reference,
        s.name AS student_name,
        s.matricule,
        d.name AS department_name,
        t.name AS tranche_name,
        p.amount,
        p.payment_method,
        p.reference_number,
        p.created_at AS payment_date
    FROM payment p
    JOIN student s ON p.student_id = s.id
    JOIN tranche t ON p.tranche_id = t.id
    JOIN department d ON t.department_id = d.id
";

$where = [];
$params = [];

if ($filterDepartment !== '') {
    $where[] = 'd.name = ?';
    $params[] = $filterDepartment;
}
if ($filterTranche !== '') {
    $where[] = 't.name = ?';
    $params[] = $filterTranche;
}
if ($filterMethod !== '') {
    $where[] = 'p.payment_method = ?';
    $params[] = $filterMethod;
}
if ($filterDateStart !== '') {
    $where[] = 'p.created_at >= ?';
    $params[] = $filterDateStart . ' 00:00:00';
}
if ($filterDateEnd !== '') {
    $where[] = 'p.created_at <= ?';
    $params[] = $filterDateEnd . ' 23:59:59';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.created_at DESC';

try {
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des rapports : ' . $e->getMessage());
}

// --- Calcul des statistiques ---
$totalPayments = count($payments);
$totalAmount = array_sum(array_column($payments, 'amount'));
$byMethod = [];
foreach ($payments as $p) {
    $method = $p['payment_method'] ?: 'OTHER';
    if (!isset($byMethod[$method])) {
        $byMethod[$method] = ['count' => 0, 'total' => 0];
    }
    $byMethod[$method]['count']++;
    $byMethod[$method]['total'] += $p['amount'];
}

// --- Listes pour les filtres ---
$departments = $bdd->query("SELECT name FROM department ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $bdd->query("SELECT name FROM tranche ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$paymentMethods = ['IBBM+', 'BANKOBU', 'OTHER'];

// --- Gestion des exports (logique inchangée) ---
$exportFormat = $_GET['export'] ?? '';
if ($exportFormat === 'pdf' || $exportFormat === 'excel') {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (ob_get_length()) ob_end_clean();

    if ($exportFormat === 'excel') {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Référence');
        $sheet->setCellValue('B1', 'Étudiant');
        $sheet->setCellValue('C1', 'Matricule');
        $sheet->setCellValue('D1', 'Département');
        $sheet->setCellValue('E1', 'Tranche');
        $sheet->setCellValue('F1', 'Montant');
        $sheet->setCellValue('G1', 'Méthode');
        $sheet->setCellValue('H1', 'Date');
        $rowNum = 2;
        foreach ($payments as $p) {
            $sheet->setCellValue('A' . $rowNum, $p['payment_reference']);
            $sheet->setCellValue('B' . $rowNum, $p['student_name']);
            $sheet->setCellValue('C' . $rowNum, $p['matricule']);
            $sheet->setCellValue('D' . $rowNum, $p['department_name']);
            $sheet->setCellValue('E' . $rowNum, $p['tranche_name']);
            $sheet->setCellValue('F' . $rowNum, $p['amount']);
            $sheet->setCellValue('G' . $rowNum, $p['payment_method']);
            $sheet->setCellValue('H' . $rowNum, date('d/m/Y H:i', strtotime($p['payment_date'])));
            $rowNum++;
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="rapport_paiements.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit();
    } elseif ($exportFormat === 'pdf') {
        $html = '<h1 style="text-align:center;color:#1e3a8a;">Rapport des paiements</h1>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;font-family:sans-serif;font-size:12px;">';
        $html .= '<thead><tr style="background:#1e3a8a;color:white;">';
        $html .= '<th>Référence</th><th>Étudiant</th><th>Matricule</th><th>Département</th><th>Tranche</th><th>Montant</th><th>Méthode</th><th>Date</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($payments as $p) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($p['payment_reference']) . '</td>';
            $html .= '<td>' . htmlspecialchars($p['student_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($p['matricule']) . '</td>';
            $html .= '<td>' . htmlspecialchars($p['department_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($p['tranche_name']) . '</td>';
            $html .= '<td>' . number_format($p['amount'], 2) . ' BIF</td>';
            $html .= '<td>' . htmlspecialchars($p['payment_method']) . '</td>';
            $html .= '<td>' . date('d/m/Y H:i', strtotime($p['payment_date'])) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<p style="text-align:right;font-weight:bold;margin-top:20px;">Total: ' . number_format($totalAmount, 2) . ' BIF</p>';
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('rapport_paiements.pdf', ['Attachment' => true]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Rapports - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .reports-layout { display: flex; flex-direction: column; gap: 24px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .kpi-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 8px; }
        .kpi-label { font-size: 0.85rem; color: var(--gray-600); font-weight: 500; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; color: var(--primary); line-height: 1.2; }
        .kpi-sub { font-size: 0.8rem; color: var(--gray-600); }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; }
        .filters-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .table-modern th, .table-modern td { vertical-align: middle; }
        .table-modern td:nth-child(2) { white-space: normal; max-width: 220px; }
        .export-badge { margin-left: 8px; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Rapports des paiements</h1>

            <div class="reports-layout">
                <!-- Bloc Filtres -->
                <div class="page-card">
                    <h2>Filtres du rapport</h2>
                    <form method="GET" action="reports.php" class="filters-grid">
                        <div class="form-group">
                            <label for="date_start">Date début</label>
                            <input type="date" id="date_start" name="date_start" value="<?= htmlspecialchars($filterDateStart) ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_end">Date fin</label>
                            <input type="date" id="date_end" name="date_end" value="<?= htmlspecialchars($filterDateEnd) ?>">
                        </div>
                        <div class="form-group">
                            <label for="department">Département</label>
                            <select id="department" name="department">
                                <option value="">Tous</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= htmlspecialchars($d['name']) ?>" <?= $filterDepartment == $d['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tranche">Tranche</label>
                            <select id="tranche" name="tranche">
                                <option value="">Toutes</option>
                                <?php foreach ($tranches as $t): ?>
                                    <option value="<?= htmlspecialchars($t['name']) ?>" <?= $filterTranche == $t['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_method">Mode de paiement</label>
                            <select id="payment_method" name="payment_method">
                                <option value="">Tous</option>
                                <?php foreach ($paymentMethods as $m): ?>
                                    <option value="<?= $m ?>" <?= $filterMethod == $m ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filters-actions" style="grid-column: 1 / -1;">
                            <button type="submit" class="btn btn-primary">Filtrer</button>
                            <a href="reports.php" class="btn btn-secondary">Réinitialiser</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>" class="btn btn-secondary">Export PDF</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-secondary">Export Excel</a>
                        </div>
                    </form>
                </div>

                <!-- Bloc KPI -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <span class="kpi-label">Nombre de paiements</span>
                        <span class="kpi-value"><?= $totalPayments ?></span>
                        <span class="kpi-sub">sur la période filtrée</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Montant total</span>
                        <span class="kpi-value"><?= number_format($totalAmount, 2, ',', ' ') ?> BIF</span>
                        <span class="kpi-sub">cumul des paiements</span>
                    </div>
                    <?php foreach ($byMethod as $method => $data): ?>
                    <div class="kpi-card">
                        <span class="kpi-label"><?= htmlspecialchars($method) ?></span>
                        <span class="kpi-value"><?= number_format($data['total'], 2, ',', ' ') ?> BIF</span>
                        <span class="kpi-sub"><?= $data['count'] ?> paiement(s)</span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Bloc Tableau -->
                <div class="page-card">
                    <h2>Détail des paiements</h2>
                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Étudiant</th>
                                    <th>Matricule</th>
                                    <th>Département</th>
                                    <th>Tranche</th>
                                    <th>Montant</th>
                                    <th>Mode</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments): ?>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['payment_reference']) ?></td>
                                            <td><?= htmlspecialchars($p['student_name']) ?></td>
                                            <td><?= htmlspecialchars($p['matricule']) ?></td>
                                            <td><?= htmlspecialchars($p['department_name']) ?></td>
                                            <td><?= htmlspecialchars($p['tranche_name']) ?></td>
                                            <td><?= number_format($p['amount'], 2) ?> BIF</td>
                                            <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($p['payment_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 20px;">Aucun paiement trouvé avec ces critères.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
