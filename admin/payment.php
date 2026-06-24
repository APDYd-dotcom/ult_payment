<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';

$error = '';
$success = false;

function translatePaymentError($message) {
    $translations = [
        'Étudiant introuvable.' => '❌ L\'étudiant avec ce matricule n\'existe pas.',
        'Tranche introuvable pour ce département.' => '❌ La tranche spécifiée n\'existe pas pour ce département.',
        'Impossible de créer le paiement.' => '❌ Une erreur est survenue lors de la création du paiement.',
    ];
    foreach ($translations as $key => $value) {
        if (strpos($message, $key) !== false) {
            return $value;
        }
    }
    return '❌  ' . htmlspecialchars($message);
}

// --- Gestion du formulaire ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Create'])) {
    $matricule       = trim($_POST['matricule'] ?? '');
    $amount          = floatval($_POST['amount'] ?? 0);
    $department      = trim($_POST['department'] ?? '');
    $tranche         = trim($_POST['tranche'] ?? '');
    $payment_method  = $_POST['payment_method'] ?? '';
    $reference       = trim($_POST['reference_number'] ?? '');

    // Validation
    if (empty($matricule) || $amount <= 0 || empty($department) || empty($tranche) || empty($payment_method) || empty($reference)) {
        $error = '⚠️ Tous les champs sont obligatoires et le montant doit être > 0.';
    } else {
        try {
            $stmt = $bdd->prepare("CALL sp_payment_create_full(?, ?, ?, ?, ?, ?)");
            $stmt->execute([$matricule, $amount, $department, $tranche, $payment_method, $reference]);
            header('Location: payment.php?success=1');
            exit();
        } catch (PDOException $e) {
            $error = translatePaymentError($e->getMessage());
        }
    }
}

// --- Récupération des paiements via la vue ---
try {
    $stmtPayments = $bdd->query("SELECT * FROM vw_payment_details ORDER BY payment_reference DESC");
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des paiements : ' . $e->getMessage());
}

// --- Récupération des données pour les listes déroulantes ---
$departments = $bdd->query("SELECT id, name FROM department ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $bdd->query("SELECT id, name, department_id FROM tranche ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Récupération des méthodes de paiement depuis l'énumération
$paymentMethods = [];
$stmt = $bdd->query("SHOW COLUMNS FROM payment LIKE 'payment_method'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $enumStr = $row['Type']; // ex: "enum('IBBM+','BANKOBU')"
    $enumStr = substr($enumStr, 5, -1); // enlève "enum(" et ")"
    $paymentMethods = array_map(function($val) { return trim($val, "'"); }, explode(',', $enumStr));
}

// Succès après redirection
if (isset($_GET['success'])) {
    $success = true;
}

// Mapping nom de département -> ID pour le JavaScript
$deptMap = [];
foreach ($departments as $d) {
    $deptMap[$d['name']] = $d['id'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Vos styles (ici ou dans styles.css) */
        .message { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .message-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message-icon { font-size: 1.4rem; }
        .form-section select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; }
        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            max-width: 640px;
        }
        .search-wrapper input {
            flex: 1;
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            border: 1px solid #d1d5db;
            border-radius: 0.75rem;
            background: #ffffff;
            color: #0f172a;
        }
        .search-wrapper input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .search-wrapper button {
            border: none;
            background: #e5e7eb;
            color: #1f2937;
            padding: 0.8rem 1rem;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            transition: background 0.2s ease;
        }
        .search-wrapper button:hover {
            background: #d1d5db;
        }
        .pagination-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
        .pagination-controls button {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #1f2937;
            padding: 0.65rem 1rem;
            border-radius: 0.75rem;
            cursor: pointer;
            font-size: 0.95rem;
            transition: background 0.2s ease, border-color 0.2s ease;
        }
        .pagination-controls button:hover:not(:disabled) {
            background: #e2e8f0;
            border-color: #94a3b8;
        }
        .pagination-controls button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .pagination-info {
            font-size: 0.95rem;
            color: #475569;
            white-space: nowrap;
        }
        .pagination-controls.hidden {
            display: none;
        }
        /* Les styles responsive sont dans styles.css */
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
                    <span class="message-icon">✅</span>
                    <span>Paiement créé avec succès.</span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="message message-error">
                    <span class="message-icon">⚠️</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="crud-container">
                <!-- Tableau -->
                <div class="table-section">
                    <div class="search-wrapper">
                        <input
                            id="payment-search"
                            type="search"
                            placeholder="Search by matricule, student name, or department"
                            aria-label="Search payments"
                        />
                        <button type="button" id="clear-payment-search" aria-label="Clear search">×</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Référence</th>
                                <th>Étudiant</th>
                                <th>Matricule</th>
                                <th>Département</th>
                                <th>Tranche</th>
                                <th>Montant</th>
                                <th>Méthode</th>
                                <th>Réf. externe</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="payment-table-body">
                            <?php if ($payments): ?>
                                <?php foreach ($payments as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['payment_reference']) ?></td>
                                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                                        <td><?= htmlspecialchars($row['matricule']) ?></td>
                                        <td><?= htmlspecialchars($row['department_name']) ?></td>
                                        <td><?= htmlspecialchars($row['tranche_name']) ?></td>
                                        <td><?= number_format($row['amount'], 2) ?> BIF</td>
                                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                        <td><?= htmlspecialchars($row['reference_number'] ?? '') ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($row['payment_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="9">Aucun paiement trouvé.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="pagination-controls hidden" id="payment-pagination">
                        <button type="button" id="payment-prev">Previous</button>
                        <span class="pagination-info" id="payment-page-info">Page 1 of 1</span>
                        <button type="button" id="payment-next">Next</button>
                    </div>
                </div>

                <!-- Formulaire -->
                <div class="form-section">
                    <h3>Ajouter un paiement</h3>
                    <form method="POST" action="payment.php">
                        <label for="matricule">Matricule étudiant</label>
                        <input id="matricule" type="text" name="matricule" placeholder="S-001" required>

                        <label for="amount">Montant</label>
                        <input id="amount" type="number" step="0.01" name="amount" placeholder="0.00" required>

                        <label for="department">Département</label>
                        <select id="department" name="department" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="tranche">Tranche</label>
                        <select id="tranche" name="tranche" required>
                            <option value="">-- Sélectionner d'abord un département --</option>
                            <?php foreach ($tranches as $t): ?>
                                <option value="<?= htmlspecialchars($t['name']) ?>" data-dept="<?= $t['department_id'] ?>">
                                    <?= htmlspecialchars($t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="payment_method">Mode de paiement</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="reference_number">Référence du paiement</label>
                        <input id="reference_number" type="text" name="reference_number" placeholder="Ex: TRX-12345" required>

                        <div class="buttons">
                            <button type="submit" name="Create">Créer</button>
                            <button type="reset">Effacer</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- JavaScript pour filtrer les tranches selon le département -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deptSelect = document.getElementById('department');
    const trancheSelect = document.getElementById('tranche');
    const allOptions = Array.from(trancheSelect.querySelectorAll('option[data-dept]'));
    const deptMap = <?= json_encode($deptMap) ?>;

    function updateTranches() {
        const selectedDept = deptSelect.value;
        const deptId = deptMap[selectedDept] || null;

        allOptions.forEach(opt => {
            const optDept = parseInt(opt.dataset.dept, 10);
            if (deptId && optDept === deptId) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        // Réinitialiser la sélection
        trancheSelect.value = '';
    }

    deptSelect.addEventListener('change', updateTranches);
    updateTranches();
});
</script>

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
    const dataRows = allRows.filter(row => row.querySelectorAll('td').length >= 4);

    function getFilteredRows() {
        const query = searchInput.value.trim().toLowerCase();

        return dataRows.filter(row => {
            const cells = row.cells;
            const studentName = cells[1]?.textContent.trim().toLowerCase() || '';
            const matricule = cells[2]?.textContent.trim().toLowerCase() || '';
            const department = cells[3]?.textContent.trim().toLowerCase() || '';
            const matches = query === '' ||
                studentName.includes(query) ||
                matricule.includes(query) ||
                department.includes(query);

            row.dataset.matchesFilter = matches ? '1' : '0';
            return matches;
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
        renderPage();
    }

    searchInput.addEventListener('keyup', updateView);

    clearButton.addEventListener('click', function () {
        searchInput.value = '';
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