<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');
ini_set('memory_limit', '256M');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

// ==========================================
// 1. RÉCUPÉRATION DES PÉRIODES À COMPARER
// ==========================================
// Période A (par défaut : mois dernier)
$startA = $_GET['start_a'] ?? date('Y-m-01', strtotime('-1 month'));
$endA   = $_GET['end_a']   ?? date('Y-m-t', strtotime('-1 month'));

// Période B (par défaut : mois courant)
$startB = $_GET['start_b'] ?? date('Y-m-01');
$endB   = $_GET['end_b']   ?? date('Y-m-t');

// ==========================================
// 2. CALCUL DES STATISTIQUES POUR CHAQUE PÉRIODE
// ==========================================
function getPeriodStats($bdd, $start, $end) {
    try {
        // Total des paiements
        $stmt = $bdd->prepare("SELECT COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total FROM payment WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
        $payments = $stmt->fetch(PDO::FETCH_ASSOC);

        // Total des pénalités
        $stmtP = $bdd->prepare("SELECT COUNT(*) AS count, COALESCE(SUM(montant_penalite), 0) AS total FROM penalite WHERE created_at BETWEEN ? AND ?");
        $stmtP->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
        $penalties = $stmtP->fetch(PDO::FETCH_ASSOC);

        // Répartition par département
        $stmtD = $bdd->prepare("
            SELECT d.name AS department_name, COUNT(p.id) AS payment_count, COALESCE(SUM(p.amount), 0) AS total_amount
            FROM payment p
            JOIN tranche t ON p.tranche_id = t.id
            JOIN department d ON t.department_id = d.id
            WHERE p.created_at BETWEEN ? AND ?
            GROUP BY d.id, d.name
            ORDER BY total_amount DESC
        ");
        $stmtD->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
        $byDepartment = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        return [
            'payment_count' => $payments['count'],
            'payment_total' => $payments['total'],
            'penalty_count' => $penalties['count'],
            'penalty_total' => $penalties['total'],
            'by_department' => $byDepartment
        ];
    } catch (PDOException $e) {
        return [
            'payment_count' => 0,
            'payment_total' => 0,
            'penalty_count' => 0,
            'penalty_total' => 0,
            'by_department' => [],
            'error' => $e->getMessage()
        ];
    }
}

$statsA = getPeriodStats($bdd, $startA, $endA);
$statsB = getPeriodStats($bdd, $startB, $endB);

// Calcul des variations en pourcentage
function calculateVariation($old, $new) {
    if ($old == 0) return $new > 0 ? 100 : 0;
    return (($new - $old) / $old) * 100;
}

$variationPayments = calculateVariation($statsA['payment_total'], $statsB['payment_total']);
$variationPenalties = calculateVariation($statsA['penalty_total'], $statsB['penalty_total']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Analyse comparative - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .compare-layout { display: flex; flex-direction: column; gap: 24px; }
        .period-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .period-filters .form-group { margin: 0; }
        .period-filters .filters-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        .compare-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 900px) { .compare-grid { grid-template-columns: 1fr; } }

        .compare-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .compare-card h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--gray-600);
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-100);
        }
        .compare-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-50);
        }
        .compare-row:last-child { border-bottom: none; }
        .compare-label { color: var(--gray-600); font-size: 0.9rem; }
        .compare-value { font-weight: 600; color: var(--gray-900); }

        .variation-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) { .variation-grid { grid-template-columns: 1fr; } }

        .variation-card {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            box-shadow: var(--shadow);
        }
        .variation-label { font-size: 0.85rem; color: var(--gray-600); font-weight: 500; }
        .variation-value { font-size: 1.6rem; font-weight: 700; line-height: 1.2; }
        .variation-value.positive { color: var(--success); }
        .variation-value.negative { color: var(--danger); }

        .chart-container {
            position: relative;
            height: 360px;
            width: 100%;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        @media (max-width: 600px) { .chart-container { height: 280px; padding: 12px; } }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Analyse comparative</h1>

            <div class="compare-layout">
                <?php if (isset($statsA['error']) || isset($statsB['error'])): ?>
                    <div class="alert alert-danger">
                        Erreur lors du calcul des statistiques.
                    </div>
                <?php endif; ?>

                <!-- Bloc Filtres de périodes -->
                <div class="page-card">
                    <h2>Périodes à comparer</h2>
                    <form method="GET" action="comparative_analysis.php" class="period-filters">
                        <div class="form-group">
                            <label for="start_a">Période A — Début</label>
                            <input type="date" id="start_a" name="start_a" value="<?= htmlspecialchars($startA) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_a">Période A — Fin</label>
                            <input type="date" id="end_a" name="end_a" value="<?= htmlspecialchars($endA) ?>">
                        </div>
                        <div class="form-group">
                            <label for="start_b">Période B — Début</label>
                            <input type="date" id="start_b" name="start_b" value="<?= htmlspecialchars($startB) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_b">Période B — Fin</label>
                            <input type="date" id="end_b" name="end_b" value="<?= htmlspecialchars($endB) ?>">
                        </div>
                        <div class="filters-actions">
                            <button type="submit" class="btn btn-primary">Comparer</button>
                            <a href="comparative_analysis.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </div>

                <!-- Bloc Détail des périodes -->
                <div class="compare-grid">
                    <!-- Période A -->
                    <div class="compare-card">
                        <h3>Période A — du <?= date('d/m/Y', strtotime($startA)) ?> au <?= date('d/m/Y', strtotime($endA)) ?></h3>
                        <div class="compare-row">
                            <span class="compare-label">Nombre de paiements</span>
                            <span class="compare-value"><?= (int) $statsA['payment_count'] ?></span>
                        </div>
                        <div class="compare-row">
                            <span class="compare-label">Montant total perçu</span>
                            <span class="compare-value"><?= number_format((float) $statsA['payment_total'], 2, ',', ' ') ?> BIF</span>
                        </div>
                        <div class="compare-row">
                            <span class="compare-label">Pénalités</span>
                            <span class="compare-value"><?= number_format((float) $statsA['penalty_total'], 2, ',', ' ') ?> BIF</span>
                        </div>
                        <?php if (!empty($statsA['by_department'])): ?>
                            <?php foreach ($statsA['by_department'] as $dept): ?>
                                <div class="compare-row">
                                    <span class="compare-label"><?= htmlspecialchars($dept['department_name']) ?></span>
                                    <span class="compare-value"><?= number_format((float) $dept['total_amount'], 2, ',', ' ') ?> BIF</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Période B -->
                    <div class="compare-card">
                        <h3>Période B — du <?= date('d/m/Y', strtotime($startB)) ?> au <?= date('d/m/Y', strtotime($endB)) ?></h3>
                        <div class="compare-row">
                            <span class="compare-label">Nombre de paiements</span>
                            <span class="compare-value"><?= (int) $statsB['payment_count'] ?></span>
                        </div>
                        <div class="compare-row">
                            <span class="compare-label">Montant total perçu</span>
                            <span class="compare-value"><?= number_format((float) $statsB['payment_total'], 2, ',', ' ') ?> BIF</span>
                        </div>
                        <div class="compare-row">
                            <span class="compare-label">Pénalités</span>
                            <span class="compare-value"><?= number_format((float) $statsB['penalty_total'], 2, ',', ' ') ?> BIF</span>
                        </div>
                        <?php if (!empty($statsB['by_department'])): ?>
                            <?php foreach ($statsB['by_department'] as $dept): ?>
                                <div class="compare-row">
                                    <span class="compare-label"><?= htmlspecialchars($dept['department_name']) ?></span>
                                    <span class="compare-value"><?= number_format((float) $dept['total_amount'], 2, ',', ' ') ?> BIF</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bloc Variations -->
                <div class="page-card">
                    <h2>Variations entre les périodes</h2>
                    <div class="variation-grid">
                        <div class="variation-card">
                            <span class="variation-label">Variation des paiements</span>
                            <span class="variation-value <?= $variationPayments >= 0 ? 'positive' : 'negative' ?>">
                                <?= ($variationPayments >= 0 ? '+' : '') . number_format($variationPayments, 2) ?> %
                            </span>
                            <span class="compare-label">Période A → Période B</span>
                        </div>
                        <div class="variation-card">
                            <span class="variation-label">Variation des pénalités</span>
                            <span class="variation-value <?= $variationPenalties >= 0 ? 'positive' : 'negative' ?>">
                                <?= ($variationPenalties >= 0 ? '+' : '') . number_format($variationPenalties, 2) ?> %
                            </span>
                            <span class="compare-label">Période A → Période B</span>
                        </div>
                    </div>
                </div>

                <!-- Bloc Graphique -->
                <div class="page-card">
                    <h2>Graphique comparatif</h2>
                    <div class="chart-container">
                        <canvas id="compareChart"></canvas>
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
    const ctx = document.getElementById('compareChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Paiements (BIF)', 'Pénalités (BIF)'],
            datasets: [
                {
                    label: 'Période A',
                    data: [<?= (float) $statsA['payment_total'] ?>, <?= (float) $statsA['penalty_total'] ?>],
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                    borderRadius: 6
                },
                {
                    label: 'Période B',
                    data: [<?= (float) $statsB['payment_total'] ?>, <?= (float) $statsB['penalty_total'] ?>],
                    backgroundColor: 'rgba(100, 116, 139, 0.75)',
                    borderColor: '#64748b',
                    borderWidth: 1,
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Comparaison des périodes', font: { size: 16, weight: 'bold' } }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});
</script>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
