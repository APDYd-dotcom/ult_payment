<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

// monthly totals last 6 months (including current)
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i month"));
    $months[$m] = 0.0;
}
$stmt = $bdd->query("SELECT DATE_FORMAT(created_at,'%Y-%m') AS m, COALESCE(SUM(amount),0) AS t FROM payment WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY m");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if (isset($months[$r['m']])) $months[$r['m']] = (float)$r['t'];
}
$labels = array_keys($months);
$values = array_values($months);

// linear regression (least squares) on index x
$n = count($values);
$slope = 0; $intercept = 0;
if ($n >= 2) {
    $sumX = $sumY = $sumXY = $sumX2 = 0;
    foreach ($values as $i => $y) {
        $x = $i + 1;
        $sumX += $x; $sumY += $y; $sumXY += $x*$y; $sumX2 += $x*$x;
    }
    $den = $n*$sumX2 - $sumX*$sumX;
    if ($den != 0) {
        $slope = ($n*$sumXY - $sumX*$sumY) / $den;
        $intercept = ($sumY - $slope*$sumX) / $n;
    }
}
$nextIndex = $n + 1;
$projection = $intercept + $slope * $nextIndex;
$nextMonth = date('Y-m', strtotime('+1 month'));
$caution = $n < 2 ? 'Donnees insuffisantes (moins de 2 mois).' : 'Projection basee sur un petit echantillon; a interpreter avec prudence.';
?>
<!DOCTYPE html>
<html><head>
    <link rel="stylesheet" href="/payment/assets/css/app.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Prevision</title>
<link rel="stylesheet" href="./styles.css?v=1.1">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css">
</head><body>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <section class="page active">
            <h1 class="page-title">Prevision des paiements</h1>
<p>Pente (slope): <?= number_format($slope, 4, ',', ' ') ?></p>
<p>Projection mois prochain (<?= htmlspecialchars($nextMonth) ?>): <?= number_format($projection, 0, ',', ' ') ?></p>
<p class="message message-error"><?= htmlspecialchars($caution) ?></p>
<canvas id="forecastChart" width="600" height="300"></canvas>
<script>
const labels = <?= json_encode(array_merge($labels, [$nextMonth])) ?>;
const actual = <?= json_encode($values) ?>;
const projected = Array(actual.length).fill(null);
projected.push(<?= (float)$projection ?>);
const ctx = document.getElementById('forecastChart').getContext('2d');
new Chart(ctx, { type:'line', data:{ labels: labels, datasets:[
  { label:'Reel', data: actual, borderColor:'#1e3a8a', fill:false },
  { label:'Projete', data: projected, borderColor:'#ef4444', borderDash:[5,5], fill:false }
] } });
</script>
</main></div>    <script src="/payment/assets/js/responsive.js"></script>
</body></html>
