<?php

    define('REQUIRED_ROLE', 'admin');
    require '../auth_check.php'; 

   // --- Fetch data from the view ---
    $stmtStudents = $bdd->query("SELECT * FROM vw_students_with_department ORDER BY student_name ASC");
    $stmtPayements = $bdd->query("SELECT * FROM vw_payment_details");
    $stmtPenalites = $bdd->query("SELECT * FROM vw_penalites");
    $stmtPartialPayments = $bdd->query("SELECT * FROM vw_partial_payments");
    
    
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
    $payements = $stmtPayements->fetchAll(PDO::FETCH_ASSOC);
    $penalites = $stmtPenalites->fetchAll(PDO::FETCH_ASSOC);
    $partialPayments = $stmtPartialPayments->fetchAll(PDO::FETCH_ASSOC);

    $totalStudents = count($students);
    $totalPayments = count($payements);
    $totalPenalites = count($penalites);
    $totalPartialPayments = count($partialPayments);    
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
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section id="dashboard" class="page active">

            <h1 class="page-title">Dashboard</h1>

            <div class="cards">
                <div class="card">
                    <h3>Total Students</h3>
                    <p><?= $totalStudents ?></p>  
                </div>
                <div class="card">
                    <h3>Total Payments</h3>
                    <p><?= $totalPayments ?></p>   
                </div>
                <div class="card">
                    <h3>Partial Payments</h3>
                    <p><?= $totalPartialPayments ?></p>
                </div>
                <div class="card">
                    <h3>Penalties</h3>
                    <p><?= $totalPenalites ?></p>
                </div>
            </div>

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
                                    <td><?= htmlspecialchars($row['matricule']) ?></td>
                                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                                    <td><?= $row['age'] ?></td>
                                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                                    <td><?= number_format($row['minerval_total'], 2) ?> BIF</td>
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