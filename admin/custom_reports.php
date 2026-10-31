<?php
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;

$allowedCols = ['matricule','student_name','department_name','amount','payment_date','payment_method','tranche_name','reference','school_year','note'];
$departments = $bdd->query('SELECT DISTINCT name FROM department ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$tranches = $bdd->query('SELECT DISTINCT tranche_name FROM vw_payment_details WHERE tranche_name IS NOT NULL ORDER BY tranche_name')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die('CSRF invalide');
    }
    $selected = $_POST['columns'] ?? [];
    $selected = array_values(array_intersect($selected, $allowedCols));
    if (empty($selected)) {
        $errorMsg = 'Choisissez au moins une colonne.';
    } else {
        $where = [];
        $params = [];
        if (!empty($_POST['date_from'])) { $where[] = 'payment_date >= ?'; $params[] = $_POST['date_from']; }
        if (!empty($_POST['date_to'])) { $where[] = 'payment_date <= ?'; $params[] = $_POST['date_to']; }
        if (!empty($_POST['department'])) { $where[] = 'department_name = ?'; $params[] = $_POST['department']; }
        if (!empty($_POST['tranche'])) { $where[] = 'tranche_name = ?'; $params[] = $_POST['tranche']; }
        if (!empty($_POST['method'])) { $where[] = 'payment_method = ?'; $params[] = $_POST['method']; }
        $sql = 'SELECT ' . implode(',', array_map(function($c){return $c;}, $selected)) . ' FROM vw_payment_details';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY payment_date DESC';
        $stmt = $bdd->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $format = $_POST['format'] ?? 'pdf';
        if ($format === 'xlsx') {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $col = 1;
            foreach ($selected as $c) {
                $sheet->setCellValueByColumnAndRow($col++, 1, $c);
            }
            $r = 2;
            foreach ($rows as $row) {
                $c2 = 1;
                foreach ($selected as $colName) {
                    $sheet->setCellValueByColumnAndRow($c2++, $r, $row[$colName] ?? '');
                }
                $r++;
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="rapport_custom.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit();
        } else {
            $html = '<html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#f0f0f0}</style>    <link rel="stylesheet" href="/payment/assets/ui.css">
</head><body><h1>Rapport personnalise</h1><table><tr>';
            foreach ($selected as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
            $html .= '</tr>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($selected as $col) $html .= '<td>' . htmlspecialchars((string)($row[$col] ?? '')) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table></body></html>';
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="rapport_custom.pdf"');
            echo $dompdf->output();
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Rapport personnalisé - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <style>
        .report-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .report-card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); margin-bottom:16px; }
        .report-card-head h2 { margin:0 0 12px 0; color:#1e3a8a; font-size:1.1rem; }
        .option { display:flex; align-items:center; gap:8px; padding:8px 0; }
        .option input { width:18px; height:18px; }
        .filters-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:12px; }
        .filters-row label { display:flex; flex-direction:column; gap:6px; }
        .report-actions { display:flex; justify-content:flex-end; }
        .btn-generate { background:#1e3a8a; color:#fff; border:none; padding:12px 18px; border-radius:10px; cursor:pointer; }
        .report-error { background:#fee2e2; color:#991b1b; padding:10px 14px; border-radius:8px; margin-bottom:12px; }
        @media (max-width: 1024px){ .report-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px){ .report-grid { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head>
<body>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Rapport personnalisé</h1>
            <?php if (!empty($errorMsg)): ?>
                <div class="report-error" id="report-error"><?= htmlspecialchars($errorMsg) ?></div>
            <?php else: ?>
                <div class="report-error" id="report-error" hidden></div>
            <?php endif; ?>
            <form method="POST" id="report-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <section class="report-card">
                    <div class="report-card-head">
                        <div>
                            <h2>Colonnes à inclure</h2>
                            <div style="display:flex; gap:10px;">
                                <button type="button" id="cols-all" class="btn-secondary">Tout sélectionner</button>
                                <button type="button" id="cols-none" class="btn-secondary">Tout désélectionner</button>
                            </div>
                        </div>
                    </div>
                    <div class="report-grid">
                        <?php foreach ($allowedCols as $col): ?>
                            <label class="option"><input type="checkbox" name="columns[]" value="<?= htmlspecialchars($col) ?>" <?= in_array($col, $selected ?? [], true) ? 'checked' : '' ?>><span><?= htmlspecialchars($col) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section class="report-card">
                    <div class="report-card-head"><h2>Filtres</h2></div>
                    <div class="filters-row">
                        <label>Date de début <input type="date" name="date_from"></label>
                        <label>Date de fin <input type="date" name="date_to"></label>
                        <label>Département
                            <select name="department"><option value="">Tous</option><?php foreach ($departments as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option><?php endforeach; ?></select>
                        </label>
                        <label>Tranche
                            <select name="tranche"><option value="">Toutes</option><?php foreach ($tranches as $t): ?><option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select>
                        </label>
                        <label>Mode de paiement
                            <select name="method"><option value="">Tous</option><option value="IBBM+">IBBM+</option><option value="BANKOBU">BANKOBU</option><option value="OTHER">OTHER</option></select>
                        </label>
                    </div>
                </section>
                <section class="report-card">
                    <div class="report-card-head"><h2>Format de sortie</h2></div>
                    <div class="format-options" style="display:flex; gap:16px; flex-wrap:wrap;">
                        <label class="option"><input type="radio" name="format" value="pdf" checked><span>PDF</span></label>
                        <label class="option"><input type="radio" name="format" value="xlsx"><span>Excel (.xlsx)</span></label>
                    </div>
                </section>
                <div class="report-actions">
                    <button class="btn-generate" type="submit">Générer le rapport</button>
                </div>
            </form>
        </section>
    </main>
</div>
<script>
(function () {
    var form = document.getElementById('report-form');
    if (!form) return;
    var boxes = form.querySelectorAll('input[name="columns[]"]');
    var errorBox = document.getElementById('report-error');
    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }
    var allBtn = document.getElementById('cols-all');
    var noneBtn = document.getElementById('cols-none');
    if (allBtn) allBtn.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = true; }); });
    if (noneBtn) noneBtn.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); });
    form.addEventListener('submit', function (event) {
        if (errorBox) errorBox.hidden = true;
        var anyChecked = Array.prototype.some.call(boxes, function (b) { return b.checked; });
        var from = form.date_from ? form.date_from.value : '';
        var to = form.date_to ? form.date_to.value : '';
        if (!anyChecked) {
            event.preventDefault();
            if (errorBox) { showError('Choisissez au moins une colonne.'); errorBox.scrollIntoView({behavior:'smooth', block:'center'}); }
            return;
        }
        if (from && to && from > to) {
            event.preventDefault();
            if (errorBox) { showError('La date de début doit être antérieure à la date de fin.'); errorBox.scrollIntoView({behavior:'smooth', block:'center'}); }
        }
    });
})();
</script>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
