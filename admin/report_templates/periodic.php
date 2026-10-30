<?php
// Template returns HTML string via output buffering in caller.
// Variables expected: $month, $rows, $summary, $byDept, $pen, $newStudents, $first, $last
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
h1 { color: #1e3a8a; }
table { width: 100%; border-collapse: collapse; margin: 8px 0; }
th, td { border: 1px solid #ccc; padding: 4px; }
th { background: #eef2ff; }
</style></head><body>
<h1>Rapport periodique - <?= htmlspecialchars($month) ?></h1>
<p>Periode: <?= htmlspecialchars($first) ?> au <?= htmlspecialchars($last) ?></p>
<h2>Paiements</h2>
<p>Nombre: <?= (int)$summary['count'] ?> - Total: <?= number_format($summary['total'], 0, ',', ' ') ?></p>
<table><tr><th>Mode</th><th>Nombre</th><th>Total</th></tr>
<?php foreach ($summary['by_method'] as $m => $d): ?>
<tr><td><?= htmlspecialchars($m) ?></td><td><?= (int)$d['count'] ?></td><td><?= number_format($d['total'], 0, ',', ' ') ?></td></tr>
<?php endforeach; ?>
</table>
<h3>Par departement</h3>
<table><tr><th>Departement</th><th>Nombre</th><th>Total</th></tr>
<?php foreach ($byDept as $d => $v): ?>
<tr><td><?= htmlspecialchars($d) ?></td><td><?= (int)$v['count'] ?></td><td><?= number_format($v['total'], 0, ',', ' ') ?></td></tr>
<?php endforeach; ?>
</table>
<h2>Penalites</h2>
<p>Nombre: <?= (int)$pen['c'] ?> - Total: <?= number_format((float)$pen['t'], 0, ',', ' ') ?></p>
<h2>Nouveaux etudiants</h2>
<p><?= (int)$newStudents ?></p>
</body></html>
