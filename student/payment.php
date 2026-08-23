<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../functions.php';

$error = '';
$success = false;
$filterDepartment = trim($_GET['department'] ?? '');
$filterTranche = trim($_GET['tranche'] ?? '');
$filterDateStart = trim($_GET['date_start'] ?? '');
$filterDateEnd = trim($_GET['date_end'] ?? '');
$activeFilterCount = count(array_filter([
    $filterDepartment,
    $filterTranche,
    $filterDateStart,
    $filterDateEnd,
], fn($value) => $value !== ''));

function translateStudentPaymentError(string $message): string
{
    $translations = [
        'Étudiant introuvable.' => 'Votre dossier etudiant est introuvable.',
        'Tranche introuvable pour ce département.' => 'La tranche specifiee n existe pas pour votre departement.',
        'Impossible de créer le paiement.' => 'Une erreur est survenue lors de la creation du paiement.',
    ];

    foreach ($translations as $key => $value) {
        if (strpos($message, $key) !== false) {
            return $value;
        }
    }

    return 'X' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
}

function studentPaymentCanChange(PDO $bdd, int $paymentId, int $studentId): bool
{
    $stmt = $bdd->prepare("
        SELECT COUNT(*)
        FROM payment p
        LEFT JOIN penalite pe ON pe.payment_id = p.id
        WHERE p.id = ?
          AND p.student_id = ?
          AND pe.id IS NULL
          AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$paymentId, $studentId]);

    return (int) $stmt->fetchColumn() === 1;
}

function studentPaymentGetTranche(PDO $bdd, int $trancheId, int $departmentId): ?array
{
    $stmt = $bdd->prepare('SELECT id, name FROM tranche WHERE id = ? AND department_id = ? LIMIT 1');
    $stmt->execute([$trancheId, $departmentId]);
    $tranche = $stmt->fetch(PDO::FETCH_ASSOC);

    return $tranche ?: null;
}

if (isset($_GET['delete'])) {
    $paymentId = (int) $_GET['delete'];

    try {
        if ($paymentId <= 0 || !studentPaymentCanChange($bdd, $paymentId, $studentId)) {
            throw new RuntimeException('Ce paiement ne peut pas etre supprime.');
        }

        $bdd->beginTransaction();

        $partialTableStmt = $bdd->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'partial_payment'
        ");
        $partialTableStmt->execute();
        if ((int) $partialTableStmt->fetchColumn() > 0) {
            $partialDelete = $bdd->prepare('DELETE FROM partial_payment WHERE payment_id = ? AND student_id = ?');
            $partialDelete->execute([$paymentId, $studentId]);
        }

        $stmt = $bdd->prepare('DELETE FROM payment WHERE id = ? AND student_id = ?');
        $stmt->execute([$paymentId, $studentId]);

        $bdd->commit();

        logActivity(
            $bdd,
            $_SESSION['userId'] ?? null,
            $_SESSION['fullname'] ?? '',
            $_SESSION['email'] ?? '',
            'student_payment_deleted',
            "Payment ID: {$paymentId}"
        );

        header('Location: payment.php?success=3');
        exit();
    } catch (Throwable $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        $error = translateStudentPaymentError($e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float) ($_POST['amount'] ?? 0);
    $trancheId = (int) ($_POST['tranche_id'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $reference = trim($_POST['reference_number'] ?? '');

    if ($amount <= 0 || $trancheId <= 0 || $paymentMethod === '' || $reference === '') {
        $error = 'Tous les champs sont obligatoires et le montant doit etre > 0.';
    } else {
        try {
            $tranche = studentPaymentGetTranche($bdd, $trancheId, (int) $currentStudent['department_id']);
            if (!$tranche) {
                throw new RuntimeException('Tranche introuvable pour ce département.');
            }

            if (isset($_POST['Create'])) {
                // Meme logique que l'admin : creation via la procedure stockee.
                // Le matricule et le departement viennent du dossier etudiant connecte.
                $stmt = $bdd->prepare("CALL sp_payment_create_full(?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $currentStudent['matricule'],
                    $amount,
                    $currentStudent['department_name'],
                    $tranche['name'],
                    $paymentMethod,
                    $reference,
                ]);
                $stmt->closeCursor();

                logActivity(
                    $bdd,
                    $_SESSION['userId'] ?? null,
                    $_SESSION['fullname'] ?? '',
                    $_SESSION['email'] ?? '',
                    'student_payment_created',
                    "Matricule: {$currentStudent['matricule']}, Montant: {$amount}, Tranche: {$tranche['name']}"
                );

                try {
                    sendPaymentCreatedNotification($bdd, $currentStudent['matricule'], $reference);
                    createPaymentInAppNotification($bdd, $currentStudent['matricule'], $reference);
                } catch (Throwable $notificationError) {
                    error_log('Student payment notification error: ' . $notificationError->getMessage());
                }

                header('Location: payment.php?success=1');
                exit();
            }

            if (isset($_POST['Update'])) {
                $paymentId = (int) ($_POST['payment_id'] ?? 0);
                if ($paymentId <= 0 || !studentPaymentCanChange($bdd, $paymentId, $studentId)) {
                    throw new RuntimeException('Ce paiement ne peut pas etre modifie.');
                }

                $stmt = $bdd->prepare("
                    UPDATE payment
                    SET tranche_id = ?, amount = ?, payment_method = ?, reference_number = ?
                    WHERE id = ? AND student_id = ?
                ");
                $stmt->execute([$trancheId, $amount, $paymentMethod, $reference, $paymentId, $studentId]);

                logActivity(
                    $bdd,
                    $_SESSION['userId'] ?? null,
                    $_SESSION['fullname'] ?? '',
                    $_SESSION['email'] ?? '',
                    'student_payment_updated',
                    "Payment ID: {$paymentId}, Montant: {$amount}, Tranche: {$tranche['name']}"
                );

                header('Location: payment.php?success=2');
                exit();
            }
        } catch (Throwable $e) {
            $error = translateStudentPaymentError($e->getMessage());
        }
    }
}

try {
    $departments = [[
        'id' => (int) $currentStudent['department_id'],
        'name' => (string) $currentStudent['department_name'],
    ]];

    $trancheStmt = $bdd->prepare('SELECT id, name, department_id FROM tranche WHERE department_id = ? ORDER BY name');
    $trancheStmt->execute([(int) $currentStudent['department_id']]);
    $tranches = $trancheStmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "
        SELECT p.id,
               CONCAT('PAY-', LPAD(p.id, 5, '0')) AS payment_reference,
               s.name AS student_name,
               s.matricule,
               d.name AS department_name,
               t.name AS tranche_name,
               p.tranche_id,
               p.amount,
               p.payment_method,
               p.reference_number,
               p.created_at AS payment_date,
               COUNT(pe.id) AS penalty_count
        FROM payment p
        JOIN student s ON s.id = p.student_id
        LEFT JOIN tranche t ON t.id = p.tranche_id
        LEFT JOIN department d ON d.id = t.department_id
        LEFT JOIN penalite pe ON pe.payment_id = p.id
        WHERE p.student_id = ?
    ";
    $params = [$studentId];

    if ($filterDepartment !== '') {
        $sql .= ' AND d.name = ?';
        $params[] = $filterDepartment;
    }

    if ($filterTranche !== '') {
        $sql .= ' AND t.name = ?';
        $params[] = $filterTranche;
    }

    if ($filterDateStart !== '') {
        $sql .= ' AND p.created_at >= ?';
        $params[] = $filterDateStart . ' 00:00:00';
    }

    if ($filterDateEnd !== '') {
        $sql .= ' AND p.created_at <= ?';
        $params[] = $filterDateEnd . ' 23:59:59';
    }

    $sql .= "
        GROUP BY p.id, s.name, s.matricule, d.name, t.name, p.tranche_id,
                 p.amount, p.payment_method, p.reference_number, p.created_at
        ORDER BY p.created_at DESC, p.id DESC
    ";

    $stmtPayments = $bdd->prepare($sql);
    $stmtPayments->execute($params);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des paiements : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$paymentMethods = [];
try {
    $stmt = $bdd->query("SHOW COLUMNS FROM payment LIKE 'payment_method'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $enumStr = substr((string) $row['Type'], 5, -1);
        $paymentMethods = array_map(static function ($value) {
            return trim($value, "'");
        }, explode(',', $enumStr));
    }
} catch (PDOException $e) {
    $paymentMethods = [];
}

if (!$paymentMethods) {
    $paymentMethods = ['IBBM+', 'BANKOBU', 'CASH', 'VIREMENT'];
}

if (isset($_GET['success'])) {
    $success = true;
}

$deptMap = [];
foreach ($departments as $d) {
    $deptMap[$d['name']] = $d['id'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css?v=1.3">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .message { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .message-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message-icon { font-size: 1.4rem; }
        .form-section select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; }
        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .danger-link { display: inline-block; padding: 10px; border-radius: 6px; background: #dc2626; color: #fff; text-decoration: none; }
        .danger-link:hover { background: #b91c1c; }
        .edit-panel { display: none; background: #f8fafc; }
        .edit-panel form { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end; }
        .edit-panel label { margin-top: 0; }
        .muted { color: #64748b; font-size: 0.9rem; }
        .readonly-input { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>

<button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open'); document.querySelector('.overlay').classList.toggle('active')">☰</button>
<div class="overlay" onclick="document.querySelector('.sidebar').classList.remove('open'); document.querySelector('.overlay').classList.remove('active')"></div>

<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section id="payment" class="page active">
            <h1 class="page-title">Payments</h1>

            <?php if ($success): ?>
                <div class="message message-success">
                    <span class="message-icon">✓</span>
                    <span>
                        <?php
                        if ($_GET['success'] == 1) {
                            echo 'Paiement cree avec succes.';
                        } elseif ($_GET['success'] == 2) {
                            echo 'Paiement mis a jour avec succes.';
                        } elseif ($_GET['success'] == 3) {
                            echo 'Paiement supprime avec succes.';
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="message message-error">
                    <span class="message-icon">⚠</span>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form class="advanced-filters" method="GET" action="payment.php">
                <div class="filters-header">
                    <div>
                        <h2>Filtres avances</h2>
                        <p><?= count($payments) ?> paiement<?= count($payments) > 1 ? 's' : '' ?> trouve<?= count($payments) > 1 ? 's' : '' ?></p>
                    </div>
                    <?php if ($activeFilterCount > 0): ?>
                        <span class="filters-badge"><?= $activeFilterCount ?> filtre<?= $activeFilterCount > 1 ? 's' : '' ?> actif<?= $activeFilterCount > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </div>

                <div class="filter-field">
                    <label for="filter_department">Departement</label>
                    <select id="filter_department" name="department">
                        <option value="">Tous les departements</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $filterDepartment === $dept['name'] ? 'selected' : '' ?>>
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
                            <option value="<?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>" data-dept="<?= (int) $t['department_id'] ?>" <?= $filterTranche === $t['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="date_start">Date de debut</label>
                    <input id="date_start" type="date" name="date_start" value="<?= htmlspecialchars($filterDateStart, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="filter-field">
                    <label for="date_end">Date de fin</label>
                    <input id="date_end" type="date" name="date_end" value="<?= htmlspecialchars($filterDateEnd, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Appliquer</button>
                    <a href="payment.php" class="btn btn-secondary">Reinitialiser</a>
                </div>
            </form>

            <div class="crud-container">
                <div class="table-section">
                    <div class="search-container">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input id="payment-search" type="text" placeholder="Search by matricule, name, or department..." aria-label="Search payments">
                            <button type="button" id="clear-payment-search" class="clear-btn" aria-label="Clear search">
                                <span class="clear-icon">✕</span>
                            </button>
                        </div>
                        <div class="search-results-counter" id="search-counter">
                            Found <strong id="counter-match">0</strong> of <span id="counter-total">0</span> payments
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th data-sort-index="0" data-sort-type="text">Reference</th>
                                <th data-sort-index="1" data-sort-type="text">Etudiant</th>
                                <th data-sort-index="2" data-sort-type="text">Matricule</th>
                                <th data-sort-index="3" data-sort-type="text">Departement</th>
                                <th data-sort-index="4" data-sort-type="text">Tranche</th>
                                <th data-sort-index="5" data-sort-type="number">Montant</th>
                                <th data-sort-index="6" data-sort-type="text">Methode</th>
                                <th data-sort-index="7" data-sort-type="text">Ref. externe</th>
                                <th data-sort-index="8" data-sort-type="date">Date</th>
                            </tr>
                        </thead>
                        <tbody id="payment-table-body">
                            <?php if ($payments): ?>
                                <?php foreach ($payments as $row): ?>
                                    <?php
                                    $canChange = (int) $row['penalty_count'] === 0 && strtotime((string) $row['payment_date']) >= strtotime('-30 days');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['payment_reference'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['student_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['matricule'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['tranche_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= number_format((float) $row['amount'], 2) ?> BIF</td>
                                        <td><?= htmlspecialchars($row['payment_method'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($row['reference_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['payment_date'])) ?></td>
                                    
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10">Aucun paiement trouve.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="pagination-controls hidden" id="payment-pagination">
                        <button type="button" id="payment-prev">Previous</button>
                        <span class="pagination-info" id="payment-page-info">Page 1 of 1</span>
                        <button type="button" id="payment-next">Next</button>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Ajouter un paiement</h3>
                    <form method="POST" action="payment.php">
                        <label for="matricule">Matricule etudiant</label>
                        <input id="matricule" class="readonly-input" type="text" value="<?= htmlspecialchars($currentStudent['matricule'], ENT_QUOTES, 'UTF-8') ?>" readonly>

                        <label for="amount">Montant</label>
                        <input id="amount" type="number" step="0.01" name="amount" placeholder="0.00" required>

                        <label for="department">Departement</label>
                        <select id="department" disabled>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="tranche_id">Tranche</label>
                        <select id="tranche_id" name="tranche_id" required>
                            <option value="">-- Selectionner --</option>
                            <?php foreach ($tranches as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" data-dept="<?= (int) $t['department_id'] ?>">
                                    <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="payment_method">Mode de paiement</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">-- Selectionner --</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="reference_number">Reference du paiement</label>
                        <input id="reference_number" type="text" name="reference_number" placeholder="Ex: TRX-12345" required>

                        <div class="buttons">
                            <button type="submit" name="Create">Creer</button>
                            <button type="reset">Effacer</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deptMap = <?= json_encode($deptMap) ?>;

    function setupTrancheFilter(departmentSelectId, trancheSelectId, showAllWhenNoDepartment) {
        const deptSelect = document.getElementById(departmentSelectId);
        const trancheSelect = document.getElementById(trancheSelectId);

        if (!deptSelect || !trancheSelect) {
            return;
        }

        const trancheOptions = Array.from(trancheSelect.querySelectorAll('option[data-dept]'));

        function updateTranches() {
            const selectedDept = deptSelect.value;
            const deptId = deptMap[selectedDept] || null;

            trancheOptions.forEach(opt => {
                const optDept = parseInt(opt.dataset.dept, 10);
                opt.style.display = ((showAllWhenNoDepartment && !deptId) || optDept === deptId) ? '' : 'none';
            });

            const selectedOption = trancheSelect.selectedOptions[0];
            if (selectedOption && selectedOption.dataset.dept && selectedOption.style.display === 'none') {
                trancheSelect.value = '';
            }
        }

        deptSelect.addEventListener('change', updateTranches);
        updateTranches();
    }

    setupTrancheFilter('filter_department', 'filter_tranche', true);
});

function toggleEdit(id) {
    const row = document.getElementById('edit-' + id);
    if (row) {
        row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
    }
}
</script>

<script src="../admin/table-sort.js?v=1.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('payment-search');
    const clearButton = document.getElementById('clear-payment-search');
    const tableBody = document.getElementById('payment-table-body');
    const paginationContainer = document.getElementById('payment-pagination');
    const prevButton = document.getElementById('payment-prev');
    const nextButton = document.getElementById('payment-next');
    const pageInfo = document.getElementById('payment-page-info');

    const rowsPerPage = 10;
    let currentPage = 1;

    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    const dataRows = allRows.filter(row => row.querySelectorAll('td').length >= 4 && !row.classList.contains('edit-panel'));
    const tableSorter = window.createTableSorter
        ? window.createTableSorter(tableBody.closest('table'), dataRows, function () {
            currentPage = 1;
            renderPage();
        })
        : { apply: rows => rows };

    function getFilteredRows() {
        const query = searchInput.value.trim().toLowerCase();

        const filteredRows = dataRows.filter(row => {
            const cells = row.cells;
            const studentName = cells[1]?.textContent.trim().toLowerCase() || '';
            const matricule = cells[2]?.textContent.trim().toLowerCase() || '';
            const department = cells[3]?.textContent.trim().toLowerCase() || '';
            const tranche = cells[4]?.textContent.trim().toLowerCase() || '';
            const matches = query === '' ||
                studentName.includes(query) ||
                matricule.includes(query) ||
                department.includes(query) ||
                tranche.includes(query);

            row.dataset.matchesFilter = matches ? '1' : '0';
            return matches;
        });

        return tableSorter.apply(filteredRows);
    }

    const totalCounter = document.getElementById('counter-total');
    if (totalCounter) {
        totalCounter.textContent = dataRows.length;
    }

    function hideAllEditPanels() {
        document.querySelectorAll('.edit-panel').forEach(row => {
            row.style.display = 'none';
        });
    }

    function renderPage() {
        const visibleRows = getFilteredRows();
        const totalRows = visibleRows.length;
        const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        const showPagination = totalRows > rowsPerPage;
        paginationContainer.classList.toggle('hidden', !showPagination);

        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
        prevButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= totalPages;

        const matchCounter = document.getElementById('counter-match');
        if (matchCounter) {
            matchCounter.textContent = totalRows;
        }

        dataRows.forEach(row => {
            row.style.display = 'none';
        });
        hideAllEditPanels();

        visibleRows.forEach((row, index) => {
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            row.style.display = index >= start && index < end ? '' : 'none';
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

    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage -= 1;
            renderPage();
            document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth' });
        }
    });

    nextButton.addEventListener('click', function () {
        const visibleRows = getFilteredRows();
        const totalPages = Math.max(1, Math.ceil(visibleRows.length / rowsPerPage));
        if (currentPage < totalPages) {
            currentPage += 1;
            renderPage();
            document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth' });
        }
    });

    renderPage();
});
</script>

</body>
</html>
