<?php
declare(strict_types=1);
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}
$bdd = new PDO('mysql:host=localhost;dbname=ult_payment;charset=utf8mb4', 'app_user', 'secure_password_123');
$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin/report_helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$month = date('Y-m', strtotime('first day of last month'));
$parts = explode('-', $month);
$first = sprintf('%04d-%02d-01', (int)$parts[0], (int)$parts[1]);
$last = date('Y-m-t', strtotime($first));

$filters = ['date_from' => $first, 'date_to' => $last, 'department' => '', 'tranche' => '', 'method' => '', 'q' => '', 'period' => ''];
$rows = getPaymentReport($bdd, $filters);
$summary = summarizeReport($rows);

$byDept = [];
foreach ($rows as $r) {
    $d = $r['department_name'] ?? 'Inconnu';
    if (!isset($byDept[$d])) $byDept[$d] = ['count' => 0, 'total' => 0.0];
    $byDept[$d]['count']++;
    $byDept[$d]['total'] += (float)$r['amount'];
}
$penStmt = $bdd->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(montant_penalite),0) AS t FROM penalite WHERE DATE(created_at) BETWEEN ? AND ?");
$penStmt->execute([$first, $last]);
$pen = $penStmt->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 't' => 0];
$stuStmt = $bdd->prepare("SELECT COUNT(*) FROM student WHERE created_at BETWEEN ? AND ?");
$stuStmt->execute([$first . ' 00:00:00', $last . ' 23:59:59']);
$newStudents = (int)$stuStmt->fetchColumn();

ob_start();
include __DIR__ . '/admin/report_templates/periodic.php';
$html = ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dir = __DIR__ . '/reports_output';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$file = $dir . '/rapport_periodique_' . $month . '.pdf';
file_put_contents($file, $dompdf->output());

// email admins
$admins = $bdd->query("SELECT email, fullname FROM user WHERE role='admin'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($admins as $a) {
    sendEmail($a['email'], $a['fullname'], 'Rapport periodique ' . $month, 'Voir piece jointe.');
}
echo "Saved $file\n";
