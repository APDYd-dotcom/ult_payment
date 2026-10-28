<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/report_helpers.php';
require_once __DIR__ . '/../functions.php';

$fromA = $_GET['from_a'] ?? date('Y-m-01');
$toA = $_GET['to_a'] ?? date('Y-m-d');
$fromB = $_GET['from_b'] ?? date('Y-m-01', strtotime('-1 month'));
$toB = $_GET['to_b'] ?? date('Y-m-t', strtotime('-1 month'));

$fa = ['date_from'=>$fromA,'date_to'=>$toA,'department'=>'','tranche'=>'','method'=>'','q'=>'','period'=>''];
$fb = ['date_from'=>$fromB,'date_to'=>$toB,'department'=>'','tranche'=>'','method'=>'','q'=>'','period'=>''];
$ra = getPaymentReport($bdd, $fa);
$rb = getPaymentReport($bdd, $fb);
$sa = summarizeReport($ra);
$sb = summarizeReport($rb);
$diff = $sa['total'] - $sb['total'];
$pct = $sb['total'] > 0 ? ($diff / $sb['total']) * 100 : 0;

// par departement
$deptTotals = $bdd->query("SELECT d.name, COALESCE(SUM(p.amount),0) AS t FROM department d LEFT JOIN tranche t ON t.department_id=d.id LEFT JOIN payment p ON p.tranche_id=t.id GROUP BY d.id ORDER BY d.name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><title>Analyse comparative</title>
<link rel="stylesheet" href="./styles.css?v=1.1">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Analyse comparative</h1>
<form method="GET" class="form-section">
<label>Periode A debut</label><input type="date" name="from_a" value="<?= htmlspecialchars($fromA) ?>">
<label>Periode A fin</label><input type="date" name="to_a" value="<?= htmlspecialchars($toA) ?>">
<label>Periode B debut</label><input type="date" name="from_b" value="<?= htmlspecialchars($fromB) ?>">
<label>Periode B fin</label><input type="date" name="to_b" value="<?= htmlspecialchars($toB) ?>">
<button class="btn-primary" type="submit">Comparer</button>
</form>
<canvas id="chartPeriods" width="400" height="200"></canvas>
<canvas id="chartDept" width="400" height="200"></canvas>
<table class="table-section"><tr><th></th><th>Periode A</th><th>Periode B</th><th>Diff</th><th>%</th></tr>
<tr><td>Total</td><td><?= number_format($sa['total'],0,',',' ') ?></td><td><?= number_format($sb['total'],0,',',' ') ?></td><td><?= number_format($diff,0,',',' ') ?></td><td><?= number_format($pct,2,',',' ') ?>%</td></tr>
</table>
<script>
const ctx1 = document.getElementById('chartPeriods').getContext('2d');
new Chart(ctx1, { type:'bar', data:{ labels:['Periode A','Periode B'], datasets:[{label:'Total paiements', data:[<?= (float)$sa['total'] ?>, <?= (float)$sb['total'] ?>], backgroundColor:['#1e3a8a','#64748b'] }] } });
const ctx2 = document.getElementById('chartDept').getContext('2d');
new Chart(ctx2, { type:'bar', data:{ labels:<?= json_encode(array_column($deptTotals,'name')) ?>, datasets:[{label:'Total par departement', data:<?= json_encode(array_map('floatval', array_column($deptTotals,'t'))) ?>, backgroundColor:'#10b981' }] } });
</script>
</main></div></body></html>
