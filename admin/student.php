<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';   // $bdd est défini ici

$message = '';
$messageType = ''; // success / error

// --- Traduction personnalisée des erreurs pour les étudiants ---
function translateStudentError($message) {
    $translations = [
        'Département introuvable.' => ' Le département spécifié n\'existe pas.',
        'Impossible de créer l\'étudiant.' => ' Une erreur est survenue lors de la création de l\'étudiant. Vérifiez les données saisies.',
        'L\'âge de l\'étudiant doit être supérieur à 18.' => ' L\'âge de l\'étudiant doit être supérieur à 18 ans.',
    ];
    foreach ($translations as $key => $value) {
        if (strpos($message, $key) !== false) {
            return $value;
        }
    }
    // Si aucun message connu, on retourne le message original avec une icône
    return '❌ ' . htmlspecialchars($message);
}

// --- Gestion du formulaire (INSERT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Create'])) {
    $fullName  = trim($_POST['fullName'] ?? '');
    $age       = intval($_POST['age'] ?? 0);
    $department= trim($_POST['department'] ?? '');

    // Validation basique
    if (empty($fullName) || empty($age) || empty($department)) {
        $message = ' Tous les champs sont obligatoires.';
        $messageType = 'error';
    } elseif ($age < 1) {
        $message = ' Âge invalide.';
        $messageType = 'error';
    } else {
        try {
            // Appel de la procédure stockée
            $stmt = $bdd->prepare("CALL sp_student_create(?, ?, ?)");
            $stmt->execute([$fullName, $age, $department]);

            // Succès → redirection pour éviter la double soumission
            header('Location: student.php?success=1');
            exit();
        } catch (PDOException $e) {
            // Traduire le message d'erreur
            $message = translateStudentError($e->getMessage());
            $messageType = 'error';
        }
    }
}

// --- Affichage d'un message de succès si redirigé ---
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = ' Étudiant créé avec succès.';
    $messageType = 'success';
}

// --- Récupération des étudiants (via la vue) ---
try {
    $stmtStudents = $bdd->query("SELECT * FROM vw_students_with_department ORDER BY student_name ASC");
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération des étudiants : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
<title>ULT Payment System</title>
<link rel="stylesheet" href="./styles.css?v=1.1">
<style>
/* Styles pour les messages */
.message {
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
.message-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.message-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.message-icon {
    font-size: 1.4rem;
}
</style>
</head>
<body>
<div class="container">
<aside id="sidebar" class="sidebar">
<?php include 'sidebar.php'; ?>
</aside>
<main id="main-content" class="main-content">
<section id="student" class="page active">
<h1 class="page-title">Students</h1>

<!-- Affichage des messages -->
<?php if ($message): ?>
<div class="message <?= $messageType === 'success' ? 'message-success' : 'message-error' ?>">
<span class="message-icon"></span>
<span><?= htmlspecialchars($message) ?></span>
</div>
<?php endif; ?>

<div class="crud-container">
<div class="table-section">
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
<th>Full Name</th>
<th>Age</th>
<th>Department</th>
</tr>
</thead>
<tbody>
<?php if ($students): ?>
<?php foreach ($students as $row): ?>
<tr>
<td><?= htmlspecialchars($row['matricule'] ?? '') ?></td>
<td><?= htmlspecialchars($row['student_name']) ?></td>
<td><?= htmlspecialchars($row['age']) ?></td>
<td><?= htmlspecialchars($row['department_name']) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="4">Aucun étudiant trouvé.</td></tr>
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
<h3>Student Form</h3>
<form method="POST" action="student.php">
<label for="fullName">Full Name</label>
<input id="fullName" type="text" name="fullName" required>

<label for="age">Age</label>
<input id="age" type="number" name="age" required min="1">

<label for="department">Department</label>
<input id="department" type="text" name="department" required placeholder="e.g. Science">

<div class="buttons">
<button type="submit" name="Create">Create</button>
<button type="reset">Clear</button>
</div>
</form>
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
