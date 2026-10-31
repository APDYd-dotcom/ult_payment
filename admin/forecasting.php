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

// ==========================================
// 1. RÉCUPÉRATION DES DONNÉES (6 derniers mois)
// ==========================================
try {
    $stmt = $bdd->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            DATE_FORMAT(created_at, '%b %Y') AS month_label,
            COUNT(*) AS payment_count,
            COALESCE(SUM(amount), 0) AS total_amount
        FROM payment
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY created_at ASC
    ");
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des données : ' . $e->getMessage());
}

// ==========================================
// 2. CALCUL DE LA RÉGRESSION LINÉAIRE
// ==========================================
$n = count($monthlyData);
$sumX = 0;
$sumY = 0;
$sumXY = 0;
$sumX2 = 0;

foreach ($monthlyData as $i => $row) {
    $x = $i + 1; // 1, 2, 3...
    $y = (float) $row['total_amount'];
    $sumX += $x;
    $sumY += $y;
    $sumXY += $x * $y;
    $sumX2 += $x * $x;
}

$slope = 0;
$intercept = 0;
$nextMonthProjection = 0;
$nextMonthLabel = '';

if ($n >= 2) {
    $denominator = ($n * $sumX2) - ($sumX * $sumX);
    if ($denominator != 0) {
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;
    }
    $nextX = $n + 1;
    $nextMonthProjection = max(0, ($slope * $nextX) + $intercept);
    $nextMonthLabel = date('F Y', strtotime('+1 month'));
}

// ==========================================
// 3. PRÉPARATION DES DONNÉES POUR LE GRAPHIQUE
// ==========================================
$chartLabels = [];
$chartRealData = [];
$chartProjectedData = [];

foreach ($monthlyData as $row) {
    $chartLabels[] = $row['month_label'];
    $chartRealData[] = (float) $row['total_amount'];
    $chartProjectedData[] = null; // Pas de projection sur les données historiques
}

// On ajoute le point de projection à la fin
if ($n >= 2) {
    $chartLabels[] = $nextMonthLabel;
    $chartRealData[] = null;
    $chartProjectedData[] = round($nextMonthProjection, 2);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Prévisions - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .forecast-layout { display: flex; flex-direction: column; gap: 24px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .kpi-card {
            background: #fff; border: 1px solid var(--gray-200); border-radius: var(--radius);
            padding: 20px; display: flex; flex-direction: column; gap: 8px;
            box-shadow: var(--shadow);
        }
        .kpi-label { font-size: 0.85rem; color: var(--gray-600); font-weight: 500; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; color: var(--primary); line-height: 1.2; }
        .kpi-sub { font-size: 0.8rem; color: var(--gray-600); }
        .kpi-value.positive { color: var(--success); }
        .kpi-value.negative { color: var(--danger); }

        .chart-container {
            position: relative;
            height: 380px;
            width: 100%;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        @media (max-width: 600px) { .chart-container { height: 280px; padding: 12px; } }

        .warning-banner {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid var(--warning);
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Prévisions des paiements</h1>

            <div class="forecast-layout">
                <!-- Bandeau d'avertissement si échantillon petit -->
                <?php if ($n < 2): ?>
                    <div class="warning-banner">
                        <span>⚠️</span>
                        <span>Données insuffisantes pour établir une prévision fiable. Au moins 2 mois de données sont nécessaires.</span>
                    </div>
                <?php elseif ($n < 4): ?>
                    <div class="warning-banner">
                        <span>⚠️</span>
                        <span>Projection basée sur un petit échantillon (<?= $n ?> mois) ; à interpréter avec prudence.</span>
                    </div>
                <?php endif; ?>

                <!-- Cartes KPI -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <span class="kpi-label">Pente (tendance mensuelle)</span>
                        <span class="kpi-value <?= $slope >= 0 ? 'positive' : 'negative' ?>">
                            <?= number_format($slope, 2, ',', ' ') ?> BIF/mois
                        </span>
                        <span class="kpi-sub">Variation moyenne du montant perçu</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Projection <?= htmlspecialchars($nextMonthLabel ?: 'mois prochain') ?></span>
                        <span class="kpi-value"><?= number_format($nextMonthProjection, 2, ',', ' ') ?> BIF</span>
                        <span class="kpi-sub">Estimation basée sur la tendance actuelle</span>
                    </div>
                    <div class="kpi-card">
                        <span class="kpi-label">Mois analysés</span>
                        <span class="kpi-value"><?= $n ?></span>
                        <span class="kpi-sub">sur les 6 derniers mois</span>
                    </div>
                </div>

                <!-- Graphique -->
                <div class="page-card">
                    <h2>Évolution et projection</h2>
                    <div class="chart-container">
                        <canvas id="forecastChart"></canvas>
                    </div>
                </div>

                <!-- Tableau des données historiques -->
                <div class="page-card">
                    <h2>Données historiques</h2>
                    <div class="table-responsive">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Mois</th>
                                    <th>Nombre de paiements</th>
                                    <th>Montant total (BIF)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($monthlyData): ?>
                                    <?php foreach ($monthlyData as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['month_label']) ?></td>
                                            <td><?= (int) $row['payment_count'] ?></td>
                                            <td><?= number_format((float) $row['total_amount'], 2, ',', ' ') ?> BIF</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align: center; padding: 20px;">Aucune donnée disponible.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('forecastChart').getContext('2d');
    
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartReal = <?= json_encode($chartRealData) ?>;
    const chartProjected = <?= json_encode($chartProjectedData) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Réel',
                    data: chartReal,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#2563eb'
                },
                {
                    label: 'Projeté',
                    data: chartProjected,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.1)',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    fill: false,
                    tension: 0.3,
                    pointRadius: 5,
                    pointBackgroundColor: '#dc2626'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                title: { 
                    display: true, 
                    text: 'Évolution des paiements et projection',
                    font: { size: 16, weight: 'bold' }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('fr-FR') + ' BIF';
                        }
                    }
                }
            }
        }
    });
});
</script>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
