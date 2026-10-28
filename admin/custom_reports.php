<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/report_helpers.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$allowedCols = ['student_name','matricule','department_name','tranche_name','amount','payment_method','reference_number','payment_date'];
$selected = array_values(array_intersect($allowedCols, $_POST['columns'] ?? []));
if (!$selected) $selected = ['student_name','amount','payment_date'];

$departments = $bdd->query("SELECT name FROM department ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$tranches = $bdd->query("SELECT name FROM tranche ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) { die('CSRF'); }
    $dateFrom = trim($_POST['date_from'] ?? '');
    $dateTo = trim($_POST['date_to'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $tranche = trim($_POST['tranche'] ?? '');
    $method = trim($_POST['method'] ?? '');
    $format = in_array($_POST['format'] ?? '', ['pdf','xlsx'], true) ? $_POST['format'] : 'pdf';

    $filters = ['date_from'=>$dateFrom,'date_to'=>$dateTo,'department'=>$dept,'tranche'=>$tranche,'method'=>$method,'q'=>'','period'=>''];
    $rows = getPaymentReport($bdd, $filters);

    logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'CUSTOM_REPORT', 'cols=' . implode(',', $selected));

    if ($format === 'xlsx') {
        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $c = 'A';
        foreach ($selected as $col) { $sh->setCellValue($c . '1', $col); $c++; }
        $r = 2;
        foreach ($rows as $row) {
            $c = 'A';
            foreach ($selected as $col) { $sh->setCellValue($c . $r, $row[$col] ?? ''); $c++; }
            $r++;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="rapport_custom.xlsx"');
        (new Xlsx($ss))->save('php://output');
        exit();
    } else {
        $html = '<h1>Rapport personnalise</h1><table border="1"><tr>';
        foreach ($selected as $col) $html .= '<th>' . htmlspecialchars($col) . '</th>';
        $html .= '</tr>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($selected as $col) $html .= '<td>' . htmlspecialchars((string)($row[$col] ?? '')) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
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
?>
<!DOCTYPE html>
<html><head><title>Rapport personnalise</title><link rel="stylesheet" href="./styles.css?v=1.1"></head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Rapport personnalise</h1>
<form method="POST" class="form-section" style="background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<label>Colonnes</label><br>
<?php foreach ($allowedCols as $col): ?>
<input type="checkbox" name="columns[]" value="<?= $col ?>" <?= in_array($col, $selected, true) ? 'checked' : '' ?>> <?= htmlspecialchars($col) ?><br>
<?php endforeach; ?>
<label>Date debut</label><input type="date" name="date_from">
<label>Date fin</label><input type="date" name="date_to">
<label>Departement</label><select name="department"><option value="">Tous</option><?php foreach ($departments as $d): ?><option><?= htmlspecialchars($d) ?></option><?php endforeach; ?></select>
<label>Tranche</label><select name="tranche"><option value="">Toutes</option><?php foreach ($tranches as $t): ?><option><?= htmlspecialchars($t) ?></option><?php endforeach; ?></select>
<label>Mode</label><select name="method"><option value="">Tous</option><option>IBBM+</option><option>BANKOBU</option><option>OTHER</option></select>
<label>Sortie</label><select name="format"><option value="pdf">PDF</option><option value="xlsx">Excel</option></select>
<button class="btn-primary" type="submit">Generer</button>
</form>
</main></div></body></html>
