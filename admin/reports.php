<?php
declare(strict_types=1);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/report_helpers.php';
require_once __DIR__ . '/../functions.php';

$filters = getReportFilters();

// Listes pour les selects de filtre
$departments = $bdd->query("SELECT id, name FROM department ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $bdd->query("SELECT id, name, department_id FROM tranche ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$rows = getPaymentReport($bdd, $filters);
$summary = summarizeReport($rows);
$periodLabel = reportPeriodLabel($filters);

// Chaîne de requête courante (sans format) pour les liens d'exportation
$exportQuery = $_GET;
unset($exportQuery['format']);
$queryString = http_build_query($exportQuery);

$success = isset($_GET['exported']) ? true : false;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Rapports des paiements - ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.1">
    <?php loadTheme($bdd); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .summary-card .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color, #1e3a8a);
        }
        .summary-card .label {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .export-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .method-total {
            margin-top: 8px;
            font-size: 0.9rem;
            color: #475569;
        }
    </style>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css">
</head>
<body>

<button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open'); document.querySelector('.overlay').classList.toggle('active')">☰</button>
<div class="overlay" onclick="document.querySelector('.sidebar').classList.remove('open'); document.querySelector('.overlay').classList.remove('active')"></div>

<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section id="reports" class="page active">
            <h1 class="page-title">Rapports des paiements</h1>

            <?php if ($success): ?>
                <div class="message message-success">
                    <span class="message-icon">&#x2705;</span>
                    <span>Le rapport a ete exporte avec succes.</span>
                </div>
            <?php endif; ?>

            <form class="advanced-filters" method="GET" action="reports.php">
                <div class="filters-header">
                    <div>
                        <h2>Filtres du rapport</h2>
                        <p><?= count($rows) ?> paiement<?= count($rows) > 1 ? 's' : '' ?> trouvé<?= count($rows) > 1 ? 's' : '' ?></p>
                    </div>
                    <?php $activeFilterCount = count(array_filter([
                        $filters['period'],
                        $filters['date_from'],
                        $filters['date_to'],
                        $filters['department'],
                        $filters['tranche'],
                        $filters['method'],
                        $filters['q'],
                    ], fn($value) => $value !== '')); ?>
                    <?php if ($activeFilterCount > 0): ?>
                        <span class="filters-badge"><?= $activeFilterCount ?> filtre<?= $activeFilterCount > 1 ? 's' : '' ?> actif<?= $activeFilterCount > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>

                <div class="filter-field">
                    <label for="filter_period">Periode</label>
                    <select id="filter_period" name="period">
                        <option value="" <?= $filters['period'] === '' ? 'selected' : '' ?>>Aucune (tout)</option>
                        <option value="day" <?= $filters['period'] === 'day' ? 'selected' : '' ?>>Jour</option>
                        <option value="week" <?= $filters['period'] === 'week' ? 'selected' : '' ?>>Semaine</option>
                        <option value="month" <?= $filters['period'] === 'month' ? 'selected' : '' ?>>Mois</option>
                        <option value="year" <?= $filters['period'] === 'year' ? 'selected' : '' ?>>Annee</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="filter_date_from">Date de debut</label>
                    <input id="filter_date_from" type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="filter-field">
                    <label for="filter_date_to">Date de fin</label>
                    <input id="filter_date_to" type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="filter-field">
                    <label for="filter_department">Departement</label>
                    <select id="filter_department" name="department">
                        <option value="">Tous les departements</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $filters['department'] === $dept['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="filter_tranche">Tranche</label>
                    <select id="filter_tranche" name="tranche">
                        <option value="">Toutes les tranches</option>
                        <?php foreach ($tranches as $t): ?>
                            <option value="<?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>" data-dept="<?= (int) $t['department_id'] ?>" <?= $filters['tranche'] === $t['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="filter_method">Mode de paiement</label>
                    <select id="filter_method" name="method">
                        <option value="">Tous les modes</option>
                        <option value="IBBM+" <?= $filters['method'] === 'IBBM+' ? 'selected' : '' ?>>IBBM+</option>
                        <option value="BANKOBU" <?= $filters['method'] === 'BANKOBU' ? 'selected' : '' ?>>BANKOBU</option>
                        <option value="OTHER" <?= $filters['method'] === 'OTHER' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="filter_q">Recherche</label>
                    <input id="filter_q" type="search" name="q" placeholder="Nom, matricule, ref. paiement, ref. transaction" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="reports.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>

            <div class="export-actions">
                <a href="report_export.php?format=pdf&<?= $queryString ?>" class="btn btn-primary">Export PDF</a>
                <a href="report_export.php?format=xlsx&<?= $queryString ?>" class="btn btn-primary">Export Excel</a>
            </div>

            <div class="crud-container">
                <div class="table-section">
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="label">Nombre de paiements</div>
                            <div class="value"><?= (int) $summary['count'] ?></div>
                        </div>
                        <div class="summary-card">
                            <div class="label">Montant total</div>
                            <div class="value"><?= number_format($summary['total'], 2, ',', ' ') ?> BIF</div>
                        </div>
                        <div class="summary-card">
                            <div class="label">Periode</div>
                            <div class="value" style="font-size: 16px;"><?= htmlspecialchars($periodLabel) ?></div>
                        </div>
                    </div>

                    <?php if ($summary['by_method']): ?>
                        <div class="method-total"><strong>Totaux par mode :</strong>
                            <?php foreach ($summary['by_method'] as $method => $data): ?>
                                <span style="margin-right: 16px;"><?= htmlspecialchars($method) ?>: <?= number_format($data['total'], 2, ',', ' ') ?> BIF (<?= (int) $data['count'] ?>)</span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Ref.</th>
                                <th>Etudiant</th>
                                <th>Matricule</th>
                                <th>Departement</th>
                                <th>Tranche</th>
                                <th>Montant</th>
                                <th>Mode</th>
                                <th>N° transaction</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($rows): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['payment_reference']) ?></td>
                                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td><?= htmlspecialchars($row['matricule']) ?></td>
                                        <td><?= htmlspecialchars($row['department_name']) ?></td>
                                        <td><?= htmlspecialchars($row['tranche_name']) ?></td>
                                        <td><?= number_format((float) $row['amount'], 2, ',', ' ') ?> BIF</td>
                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($row['reference_number'] ?? '') ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['payment_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9">Aucun resultat.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

    <script src="/payment/assets/js/responsive.js"></script>
</body>
</html>
