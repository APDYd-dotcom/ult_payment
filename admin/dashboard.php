<?php
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

// Petit helper pour executer des requetes preparees et garder le haut de page lisible.
function fetchPrepared(PDO $bdd, string $sql, array $params = []): array
{
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchPreparedValue(PDO $bdd, string $sql, array $params = [], mixed $default = 0): mixed
{
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? $default : $value;
}

try {
    // =========================
    // 1. Indicateurs principaux
    // =========================
    $totalStudents = (int) fetchPreparedValue($bdd, 'SELECT COUNT(*) FROM student');
    $totalPayments = (int) fetchPreparedValue($bdd, 'SELECT COUNT(*) FROM payment');
    $totalAmount = (float) fetchPreparedValue($bdd, 'SELECT COALESCE(SUM(amount), 0) FROM payment');

    // Nombre d'etudiants distincts ayant au moins une penalite.
    $latePayments = (int) fetchPreparedValue(
        $bdd,
        'SELECT COUNT(DISTINCT matricule) FROM vw_penalites'
    );

    // ==========================================
    // 2. Donnees du tableau des derniers etudiants
    // ==========================================
    $students = fetchPrepared(
        $bdd,
        'SELECT matricule, student_name, age, department_name, minerval_total, student_created_at
         FROM vw_students_with_department
         ORDER BY student_name ASC'
    );

    // =====================================================
    // 3. Graphique barres : etudiants par departement
    // =====================================================
    $studentsByDepartment = fetchPrepared(
        $bdd,
        'SELECT d.name AS department_name, COUNT(s.id) AS total_students
         FROM department d
         LEFT JOIN student s ON s.department_id = d.id
         GROUP BY d.id, d.name
         ORDER BY d.name ASC'
    );

    $departmentLabels = array_column($studentsByDepartment, 'department_name');
    $departmentTotals = array_map('intval', array_column($studentsByDepartment, 'total_students'));

    // =========================================================
    // 4. Graphique courbe : paiements des 6 derniers mois
    // =========================================================
    $monthlyRows = fetchPrepared(
        $bdd,
        "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_key,
                DATE_FORMAT(payment_date, '%b %Y') AS month_label,
                COALESCE(SUM(amount), 0) AS monthly_total
         FROM vw_payment_details
         WHERE payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
         GROUP BY month_key, month_label
         ORDER BY month_key ASC"
    );

    // On prepare les 6 mois cote PHP pour afficher aussi les mois sans paiement.
    $monthlyMap = [];
    foreach ($monthlyRows as $row) {
        $monthlyMap[$row['month_key']] = (float) $row['monthly_total'];
    }

    $paymentMonthLabels = [];
    $paymentMonthTotals = [];
    $currentMonth = new DateTimeImmutable('first day of this month');
    for ($i = 5; $i >= 0; $i--) {
        $date = $currentMonth->modify("-{$i} months");
        $key = $date->format('Y-m');
        $paymentMonthLabels[] = $date->format('M Y');
        $paymentMonthTotals[] = $monthlyMap[$key] ?? 0;
    }

    // ==================================================
    // 5. Graphique circulaire : paiements par tranche
    // ==================================================
    $paymentsByTranche = fetchPrepared(
        $bdd,
        "SELECT COALESCE(tranche_name, 'Non défini') AS tranche_name, COUNT(*) AS total_payments
         FROM vw_payment_details
         GROUP BY COALESCE(tranche_name, 'Non défini')
         ORDER BY tranche_name ASC"
    );

    $trancheLabels = array_column($paymentsByTranche, 'tranche_name');
    $trancheTotals = array_map('intval', array_column($paymentsByTranche, 'total_payments'));
} catch (PDOException $e) {
    die('Erreur lors du chargement du dashboard : ' . $e->getMessage());
}

// Donnees encodees en JSON pour Chart.js.
$chartData = [
    'departments' => [
        'labels' => $departmentLabels,
        'values' => $departmentTotals,
    ],
    'paymentsByMonth' => [
        'labels' => $paymentMonthLabels,
        'values' => $paymentMonthTotals,
    ],
    'paymentsByTranche' => [
        'labels' => $trancheLabels,
        'values' => $trancheTotals,
    ],
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.2">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section id="dashboard" class="page active">
            <h1 class="page-title">Dashboard</h1>

            <!-- Indicateurs KPI -->
            <div class="cards kpi-cards">
                <div class="card kpi-card">
                    <h3>Total étudiants</h3>
                    <p><?= number_format($totalStudents, 0, ',', ' ') ?></p>
                </div>
                <div class="card kpi-card">
                    <h3>Total paiements</h3>
                    <p><?= number_format($totalPayments, 0, ',', ' ') ?></p>
                </div>
                <div class="card kpi-card">
                    <h3>Montant total perçu</h3>
                    <p><?= number_format($totalAmount, 2, ',', ' ') ?> BIF</p>
                </div>
                <div class="card kpi-card">
                    <h3>Paiements en retard</h3>
                    <p><?= number_format($latePayments, 0, ',', ' ') ?></p>
                </div>
            </div>

            <!-- Graphiques dynamiques -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Étudiants par département</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Paiements sur 6 mois</h3>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="paymentEvolutionChart"></canvas>
                    </div>
                </div>

                <div class="chart-card chart-card-wide">
                    <div class="chart-header">
                        <h3>Répartition par tranche</h3>
                    </div>
                    <div class="chart-wrapper chart-wrapper-small">
                        <canvas id="trancheChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tableau existant des etudiants -->
            <div class="dashboard-table">
                <h3>All Students</h3>
                <div class="search-container">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input
                            id="payment-search"
                            type="text"
                            placeholder="Search by name, matricule, department..."
                            aria-label="Search students"
                        />
                        <button type="button" id="clear-payment-search" class="clear-btn" aria-label="Clear search">
                            <span class="clear-icon">✕</span>
                        </button>
                    </div>
                    <div class="search-results-counter" id="search-counter">
                        Found <strong id="counter-match">0</strong> of <span id="counter-total">0</span> students
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Department</th>
                            <th>Minerval Total</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['matricule'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['age'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= number_format((float) $row['minerval_total'], 2, ',', ' ') ?> BIF</td>
                                    <td><?= date('Y-m-d', strtotime($row['student_created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination-controls hidden" id="payment-pagination">
                    <button type="button" id="payment-prev">Previous</button>
                    <span class="pagination-info" id="payment-page-info">Page 1 of 1</span>
                    <button type="button" id="payment-next">Next</button>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
// Donnees PHP transmises au JavaScript pour Chart.js.
const dashboardChartData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const chartColors = {
        blue: '#2563eb',
        navy: '#1e3a8a',
        green: '#16a34a',
        amber: '#f59e0b',
        red: '#dc2626',
        teal: '#0891b2',
        violet: '#7c3aed',
        grid: '#e5e7eb',
        text: '#475569'
    };

    // Options communes pour harmoniser les graphiques avec le design existant.
    Chart.defaults.font.family = "'Segoe UI', sans-serif";
    Chart.defaults.color = chartColors.text;

    new Chart(document.getElementById('departmentChart'), {
        type: 'bar',
        data: {
            labels: dashboardChartData.departments.labels,
            datasets: [{
                label: 'Étudiants',
                data: dashboardChartData.departments.values,
                backgroundColor: chartColors.blue,
                borderColor: chartColors.navy,
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: chartColors.grid }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    new Chart(document.getElementById('paymentEvolutionChart'), {
        type: 'line',
        data: {
            labels: dashboardChartData.paymentsByMonth.labels,
            datasets: [{
                label: 'Montant perçu (BIF)',
                data: dashboardChartData.paymentsByMonth.values,
                borderColor: chartColors.green,
                backgroundColor: 'rgba(22, 163, 74, 0.12)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: chartColors.green,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' BIF';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: chartColors.grid },
                    ticks: {
                        callback: function (value) {
                            return new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(value);
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    new Chart(document.getElementById('trancheChart'), {
        type: 'pie',
        data: {
            labels: dashboardChartData.paymentsByTranche.labels,
            datasets: [{
                data: dashboardChartData.paymentsByTranche.values,
                backgroundColor: [
                    chartColors.blue,
                    chartColors.green,
                    chartColors.amber,
                    chartColors.red,
                    chartColors.teal,
                    chartColors.violet
                ],
                borderColor: '#ffffff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Recherche + pagination du tableau des etudiants.
    const searchInput = document.getElementById('payment-search');
    const clearButton = document.getElementById('clear-payment-search');
    const tableBody = document.querySelector('table tbody');
    const paginationContainer = document.getElementById('payment-pagination');
    const prevButton = document.getElementById('payment-prev');
    const nextButton = document.getElementById('payment-next');
    const pageInfo = document.getElementById('payment-page-info');

    const rowsPerPage = 10;
    let currentPage = 1;

    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    const dataRows = allRows.filter(row => {
        const cells = row.querySelectorAll('td');
        return cells.length > 0 && cells[0].getAttribute('colspan') === null;
    });

    function getFilteredRows() {
        const query = searchInput.value.trim().toLowerCase();

        return dataRows.filter(row => {
            const cells = Array.from(row.cells);
            const textContent = cells.map(cell => cell.textContent.trim().toLowerCase()).join(' ');
            return query === '' || textContent.includes(query);
        });
    }

    const totalCounter = document.getElementById('counter-total');
    if (totalCounter) {
        totalCounter.textContent = dataRows.length;
    }

    function renderPage() {
        const visibleRows = getFilteredRows();
        const totalRows = visibleRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const showPagination = totalRows > rowsPerPage;
        if (paginationContainer) {
            paginationContainer.classList.toggle('hidden', !showPagination);
        }

        if (pageInfo) {
            pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        }
        if (prevButton) prevButton.disabled = currentPage <= 1;
        if (nextButton) nextButton.disabled = currentPage >= totalPages;

        const matchCounter = document.getElementById('counter-match');
        if (matchCounter) {
            matchCounter.textContent = totalRows;
        }

        dataRows.forEach(row => {
            row.style.display = 'none';
        });

        visibleRows.forEach((row, index) => {
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            row.style.display = index >= start && index < end ? '' : 'none';
        });
    }

    function updateView() {
        currentPage = 1;
        if (clearButton) {
            clearButton.classList.toggle('visible', searchInput.value.trim().length > 0);
        }
        renderPage();
    }

    if (searchInput) {
        searchInput.addEventListener('input', updateView);
        searchInput.addEventListener('keyup', updateView);
    }

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            searchInput.value = '';
            clearButton.classList.remove('visible');
            searchInput.focus();
            updateView();
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage -= 1;
                renderPage();
                document.querySelector('.dashboard-table').scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            const visibleRows = getFilteredRows();
            const totalPages = Math.max(1, Math.ceil(visibleRows.length / rowsPerPage));
            if (currentPage < totalPages) {
                currentPage += 1;
                renderPage();
                document.querySelector('.dashboard-table').scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    renderPage();
});
</script>
</body>
</html>
