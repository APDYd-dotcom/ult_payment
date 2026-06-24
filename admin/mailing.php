<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');

require __DIR__ . '/../auth_check.php';  

// --- Récupération des données ---
try {
    $stmtMailing = $bdd->query("SELECT * FROM mailing_list ORDER BY student_name ASC");
    $mailinglist = $stmtMailing->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération de la mailing list : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.1">
</head>
<body>

<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include  'sidebar.php'; ?>  
    </aside>

    <main id="main-content" class="main-content">
        <section id="mailing" class="page active">
            <h1 class="page-title">Mailing List</h1>

            <div class="crud-container">
                <div class="table-section">
                    <div class="search-container">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input
                                id="payment-search"
                                type="text"
                                placeholder="Search mailing list..."
                                aria-label="Search mailing list"
                            />
                            <button type="button" id="clear-payment-search" class="clear-btn" aria-label="Clear search">
                                <span class="clear-icon">✕</span>
                            </button>
                        </div>
                        <div class="search-results-counter" id="search-counter">
                            Found <strong id="counter-match">0</strong> of <span id="counter-total">0</span> subscribers
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student Name</th>
                                <th>Email Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mailinglist): ?>
                                <?php foreach ($mailinglist as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td><?= htmlspecialchars($row['email_status']) ?></td>
                                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4">Aucun abonné trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="pagination-controls hidden" id="payment-pagination">
                        <button type="button" id="payment-prev">Previous</button>
                        <span class="pagination-info" id="payment-page-info">Page 1 of 1</span>
                        <button type="button" id="payment-next">Next</button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
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
                document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth' });
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
                document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth' });
            }
        });
    }

    renderPage();
});
</script>
</body>
</html>