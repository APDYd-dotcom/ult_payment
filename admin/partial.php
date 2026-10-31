<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

// --- Récupération des paiements partiels (sans vue) ---
try {
    $stmt = $bdd->query("
        SELECT 
            pp.id,
            CONCAT('PP-', LPAD(pp.id, 4, '0')) AS partial_payment_reference,
            CONCAT('P-', LPAD(p.id, 4, '0')) AS payment_reference,
            s.matricule,
            s.name AS student_name,
            pp.paid_amount,
            pp.expected_amount,
            pp.missing_amount,
            pp.created_at
        FROM partial_payment pp
        JOIN payment p ON pp.payment_id = p.id
        JOIN student s ON pp.student_id = s.id
        ORDER BY pp.created_at DESC
    ");
    $partialPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération des paiements partiels : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Paiements partiels - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .partial-layout { display: flex; flex-direction: column; gap: 24px; }

        .search-container { margin-bottom: 16px; }
        .search-bar {
            position: relative; display: flex; align-items: center;
            background: #fff; border: 1px solid var(--gray-200);
            border-radius: 10px; max-width: 420px; height: 44px;
        }
        .search-bar .search-icon { position: absolute; left: 14px; color: #64748b; pointer-events: none; }
        .search-bar input {
            width: 100%; height: 100%; border: none; outline: none;
            padding: 0 40px 0 42px; font-size: 0.95rem; background: transparent; border-radius: 10px;
        }
        .search-bar input::placeholder { color: #94a3b8; }
        .search-clear {
            position: absolute; right: 10px; background: none; border: none;
            cursor: pointer; color: #64748b; display: none; padding: 4px;
        }
        .search-clear.visible { display: block; }
        .search-results-counter { font-size: 0.85rem; color: var(--gray-600); margin-top: 8px; }

        .amount-missing { color: var(--danger); font-weight: 600; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section id="partial" class="page active">
            <h1 class="page-title">Paiements partiels</h1>

            <div class="partial-layout">
                <div class="page-card">
                    <h2>Liste des paiements partiels</h2>

                    <div class="search-container">
                        <div class="search-bar">
                            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input id="partial-search" type="text" placeholder="Rechercher par nom, matricule ou référence...">
                            <button type="button" id="clear-partial-search" class="search-clear">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                        <div class="search-results-counter">
                            <strong id="counter-match">0</strong> sur <span id="counter-total">0</span> paiements partiels
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern" id="partial-table">
                            <thead>
                                <tr>
                                    <th data-sort-index="0" data-sort-type="text">Réf. Partielle</th>
                                    <th data-sort-index="1" data-sort-type="text">Réf. Paiement</th>
                                    <th data-sort-index="2" data-sort-type="text">Matricule</th>
                                    <th data-sort-index="3" data-sort-type="text">Étudiant</th>
                                    <th data-sort-index="4" data-sort-type="number">Montant Payé</th>
                                    <th data-sort-index="5" data-sort-type="number">Montant Attendu</th>
                                    <th data-sort-index="6" data-sort-type="number">Reste à Payer</th>
                                    <th data-sort-index="7" data-sort-type="date">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($partialPayments): ?>
                                    <?php foreach ($partialPayments as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['partial_payment_reference']) ?></td>
                                            <td><?= htmlspecialchars($row['payment_reference']) ?></td>
                                            <td><?= htmlspecialchars($row['matricule']) ?></td>
                                            <td><?= htmlspecialchars($row['student_name']) ?></td>
                                            <td><?= number_format($row['paid_amount'], 2) ?> BIF</td>
                                            <td><?= number_format($row['expected_amount'], 2) ?> BIF</td>
                                            <td class="amount-missing"><?= number_format($row['missing_amount'], 2) ?> BIF</td>
                                            <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 20px;">Aucun paiement partiel trouvé.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container hidden" id="partial-pagination" style="margin-top:16px; display:flex; justify-content:center; gap:10px;">
                        <button type="button" id="partial-prev" class="btn btn-secondary">Précédent</button>
                        <span id="partial-page-info" style="padding:10px; color:var(--gray-600);">Page 1 sur 1</span>
                        <button type="button" id="partial-next" class="btn btn-secondary">Suivant</button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="./table-sort.js?v=1.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('partial-search');
    const clearButton = document.getElementById('clear-partial-search');
    const tableBody = document.querySelector('#partial-table tbody');
    const paginationContainer = document.getElementById('partial-pagination');
    const prevButton = document.getElementById('partial-prev');
    const nextButton = document.getElementById('partial-next');
    const pageInfo = document.getElementById('partial-page-info');

    const rowsPerPage = 10;
    let currentPage = 1;

    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    const dataRows = allRows.filter(row => {
        const cells = row.querySelectorAll('td');
        return cells.length > 0 && cells[0].getAttribute('colspan') === null;
    });

    const tableSorter = window.createTableSorter
        ? window.createTableSorter(document.getElementById('partial-table'), dataRows, function () {
            currentPage = 1; renderPage();
        })
        : { apply: r => r };

    function getFilteredRows() {
        const query = searchInput.value.trim().toLowerCase();
        const filtered = dataRows.filter(row => {
            const text = Array.from(row.cells).map(c => c.textContent.trim().toLowerCase()).join(' ');
            return query === '' || text.includes(query);
        });
        return tableSorter.apply(filtered);
    }

    document.getElementById('counter-total').textContent = dataRows.length;

    function renderPage() {
        const visibleRows = getFilteredRows();
        const totalRows = visibleRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;

        paginationContainer.classList.toggle('hidden', totalRows <= rowsPerPage);
        pageInfo.textContent = `Page ${currentPage} sur ${totalPages}`;
        prevButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= totalPages;
        document.getElementById('counter-match').textContent = totalRows;

        dataRows.forEach(r => r.style.display = 'none');
        visibleRows.forEach((row, i) => {
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            row.style.display = (i >= start && i < end) ? '' : 'none';
        });
    }

    function updateView() {
        currentPage = 1;
        clearButton.classList.toggle('visible', searchInput.value.trim().length > 0);
        renderPage();
    }

    searchInput.addEventListener('input', updateView);
    searchInput.addEventListener('keyup', updateView);

    clearButton.addEventListener('click', function () {
        searchInput.value = '';
        clearButton.classList.remove('visible');
        searchInput.focus();
        updateView();
    });

    prevButton.addEventListener('click', () => { if (currentPage > 1) { currentPage--; renderPage(); } });
    nextButton.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(getFilteredRows().length / rowsPerPage));
        if (currentPage < totalPages) { currentPage++; renderPage(); }
    });

    renderPage();
});
</script>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
