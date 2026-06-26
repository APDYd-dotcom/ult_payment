<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$message = '';
$messageType = '';

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
   
    return 'X' . htmlspecialchars($message);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['Create'])) {
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
                $stmt->closeCursor();

                logActivity(
                    $bdd,
                    $_SESSION['userId'] ?? null,
                    $_SESSION['fullname'] ?? '',
                    $_SESSION['email'] ?? '',
                    'student_created',
                    "Nom: $fullName, Département: $department"
                );

                header('Location: student.php?success=1');
                exit();
            } catch (PDOException $e) {
                // Traduire le message d'erreur
                $message = translateStudentError($e->getMessage());
                $messageType = 'error';
            }
        }
    }

    if (isset($_POST['Update'])) {
        $oldMatricule = trim($_POST['oldMatricule'] ?? '');
        $newMatricule = trim($_POST['newMatricule'] ?? '');
        $fullName     = trim($_POST['fullName'] ?? '');
        $age          = intval($_POST['age'] ?? 0);
        $department   = trim($_POST['department'] ?? '');

        if (empty($oldMatricule) || empty($newMatricule) || empty($fullName) || empty($age) || empty($department)) {
            $message = 'Tous les champs sont obligatoires.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $bdd->prepare("CALL sp_student_update(?, ?, ?, ?, ?)");
                $stmt->execute([$oldMatricule, $newMatricule, $fullName, $age, $department]);
                $stmt->closeCursor();

                logActivity(
                    $bdd,
                    $_SESSION['userId'] ?? null,
                    $_SESSION['fullname'] ?? '',
                    $_SESSION['email'] ?? '',
                    'student_updated',
                    "Matricule: $newMatricule, Nouveau nom: $fullName"
                );

                header('Location: student.php?success=2');
                exit();
            } catch (PDOException $e) {
                $message = 'X' . htmlspecialchars($e->getMessage());
                $messageType = 'error';
            }
        }
    }

    if (isset($_POST['DeleteBulk'])) {
        $matricules = $_POST['matricules'] ?? [];
        if (empty($matricules)) {
            $message = 'Aucun étudiant sélectionné.';
            $messageType = 'error';
        } else {
            try {
                $bdd->beginTransaction();
                $stmt = $bdd->prepare("CALL sp_student_delete_matricule(?)");
                $deletedMatricules = [];
                foreach ($matricules as $mat) {
                    $stmt->execute([$mat]);
                    $stmt->closeCursor();
                    $deletedMatricules[] = $mat;
                }

                logActivity(
                    $bdd,
                    $_SESSION['userId'] ?? null,
                    $_SESSION['fullname'] ?? '',
                    $_SESSION['email'] ?? '',
                    'student_deleted',
                    'Matricule: ' . implode(', ', $deletedMatricules)
                );

                $bdd->commit();
                header('Location: student.php?success=3');
                exit();
            } catch (PDOException $e) {
                $bdd->rollBack();
                $message = 'X' . htmlspecialchars($e->getMessage());
                $messageType = 'error';
            }
        }
    }
}

// --- Affichage d'un message de succès si redirigé ---
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $message = ' Étudiant créé avec succès.';
        $messageType = 'success';
    } elseif ($_GET['success'] == 2) {
        $message = ' Étudiant mis à jour avec succès.';
        $messageType = 'success';
    } elseif ($_GET['success'] == 3) {
        $message = ' Étudiant(s) supprimé(s) avec succès.';
        $messageType = 'success';
    }
}

// --- Récupération des étudiants
try {
    $stmtStudents = $bdd->query("SELECT * FROM vw_students_with_department ORDER BY student_name ASC");
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération des étudiants : ' . $e->getMessage());
}

// --- Récupération des départements pour les selects ---
try {
    $stmtDepts = $bdd->query("SELECT name FROM department ORDER BY name ASC");
    $departments = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $departments = [];
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
<th><input type="checkbox" id="select-all-checkbox"></th>
<th>Matricule</th>
<th>Full Name</th>
<th>Age</th>
<th>Department</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php if ($students): ?>
<?php foreach ($students as $row): ?>
<tr data-matricule="<?= htmlspecialchars($row['matricule']) ?>" 
    data-name="<?= htmlspecialchars($row['student_name']) ?>" 
    data-age="<?= htmlspecialchars($row['age']) ?>" 
    data-department="<?= htmlspecialchars($row['department_name']) ?>">
<td><input type="checkbox" class="row-checkbox" value="<?= htmlspecialchars($row['matricule']) ?>"></td>
<td><?= htmlspecialchars($row['matricule'] ?? '') ?></td>
<td class="student-name-cell">
    <span class="student-name-text"><?= htmlspecialchars($row['student_name']) ?></span>
    <div class="row-quick-actions">
        <button type="button" class="quick-action-btn quick-edit-btn" title="Modifier">✏️</button>
        <button type="button" class="quick-action-btn quick-delete-btn" title="Supprimer" data-matricule="<?= htmlspecialchars($row['matricule']) ?>" data-name="<?= htmlspecialchars($row['student_name']) ?>">🗑️</button>
    </div>
</td>
<td><?= htmlspecialchars($row['age']) ?></td>
<td><?= htmlspecialchars($row['department_name']) ?></td>
<td class="options-cell">
    <button type="button" class="three-dots-btn">⋮</button>
    <div class="options-menu dropdown-hidden">
        <button type="button" class="menu-item dropdown-edit-btn">Modifier</button>
        <button type="button" class="menu-item dropdown-delete-btn">Supprimer</button>
    </div>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="6">Aucun étudiant trouvé.</td></tr>
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
<select id="department" name="department" required>
    <option value="">Select Department</option>
    <?php foreach ($departments as $dept): ?>
        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
    <?php endforeach; ?>
</select>

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

<!-- Floating Action Bar (YouTube Studio style) -->
<div class="floating-bulk-bar" id="bulk-action-bar">
    <span class="selection-count"><span id="selected-count-badge">0</span> selected</span>
    <div class="bulk-actions">
        <button type="button" class="bulk-btn" id="bulk-edit-btn">✏️ Edit</button>
        <button type="button" class="bulk-btn bulk-btn-danger" id="bulk-delete-btn">🗑️ Delete</button>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="edit-student-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Modifier l'Étudiant</h3>
            <button type="button" class="modal-close-btn" id="close-edit-modal">&times;</button>
        </div>
        <form method="POST" action="student.php">
            <div class="modal-body">
                <input type="hidden" name="oldMatricule" id="edit-old-matricule">
                
                <label for="edit-matricule">Matricule</label>
                <input id="edit-matricule" type="text" name="newMatricule" required>

                <label for="edit-fullName">Full Name</label>
                <input id="edit-fullName" type="text" name="fullName" required>

                <label for="edit-age">Age</label>
                <input id="edit-age" type="number" name="age" required min="1">

                <label for="edit-department">Department</label>
                <select id="edit-department" name="department" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-cancel-btn" id="cancel-edit-modal">Cancel</button>
                <button type="submit" name="Update" class="modal-btn modal-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="delete-student-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Confirm Deletion</h3>
            <button type="button" class="modal-close-btn" id="close-delete-modal">&times;</button>
        </div>
        <form method="POST" action="student.php">
            <div class="modal-body">
                <p id="delete-confirm-text">Are you sure you want to delete the selected student?</p>
                <div id="delete-matricules-hidden-container"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-cancel-btn" id="cancel-delete-modal">Cancel</button>
                <button type="submit" name="DeleteBulk" class="modal-btn modal-btn-danger">Delete</button>
            </div>
        </form>
    </div>
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


    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkActionBar = document.getElementById('bulk-action-bar');
    const selectedCountBadge = document.getElementById('selected-count-badge');
    const bulkEditBtn = document.getElementById('bulk-edit-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    // Modals
    const editModal = document.getElementById('edit-student-modal');
    const deleteModal = document.getElementById('delete-student-modal');

    // Toggle bulk action bar
    function updateBulkBar() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const count = checkedBoxes.length;

        selectedCountBadge.textContent = count;
        if (count > 0) {
            bulkActionBar.classList.add('visible');
        } else {
            bulkActionBar.classList.remove('visible');
        }

        // Edit button in bulk is only enabled if exactly 1 is checked
        if (count === 1) {
            bulkEditBtn.style.display = 'inline-flex';
        } else {
            bulkEditBtn.style.display = 'none';
        }
    }

    // Header select all checkbox
    selectAllCheckbox.addEventListener('change', function () {
        const isChecked = selectAllCheckbox.checked;
        const visibleRows = getFilteredRows();

        rowCheckboxes.forEach(cb => {
            const tr = cb.closest('tr');
            // Only check/uncheck visible filtered rows
            if (visibleRows.includes(tr)) {
                cb.checked = isChecked;
            } else {
                cb.checked = false;
            }
        });
        updateBulkBar();
    });

    // Individual checkboxes
    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            // Uncheck "select all" if any row is unchecked
            if (!cb.checked) {
                selectAllCheckbox.checked = false;
            }
            updateBulkBar();
        });
    });

    // Three dots menus toggle
    document.querySelectorAll('.three-dots-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const currentMenu = btn.nextElementSibling;
            
            // Close other open menus
            document.querySelectorAll('.options-menu').forEach(menu => {
                if (menu !== currentMenu) {
                    menu.classList.add('dropdown-hidden');
                }
            });

            currentMenu.classList.toggle('dropdown-hidden');
        });
    });

    // Close options menu when clicking anywhere else
    document.addEventListener('click', function () {
        document.querySelectorAll('.options-menu').forEach(menu => {
            menu.classList.add('dropdown-hidden');
        });
    });

    // Edit student handler
    function openEditModalForStudent(tr) {
        const matricule = tr.dataset.matricule;
        const name = tr.dataset.name;
        const age = tr.dataset.age;
        const department = tr.dataset.department;

        document.getElementById('edit-old-matricule').value = matricule;
        document.getElementById('edit-matricule').value = matricule;
        document.getElementById('edit-fullName').value = name;
        document.getElementById('edit-age').value = age;
        document.getElementById('edit-department').value = department;

        editModal.classList.add('open');
    }

    // Delete student handler
    function openDeleteModalForStudents(students) {
        const hiddenContainer = document.getElementById('delete-matricules-hidden-container');
        hiddenContainer.innerHTML = ''; // clear

        students.forEach(student => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'matricules[]';
            input.value = student.matricule;
            hiddenContainer.appendChild(input);
        });

        const confirmText = document.getElementById('delete-confirm-text');
        if (students.length === 1) {
            confirmText.textContent = `Are you sure you want to delete the student "${students[0].name}" (Matricule: ${students[0].matricule})?`;
        } else {
            confirmText.textContent = `Are you sure you want to delete the ${students.length} selected students?`;
        }

        deleteModal.classList.add('open');
    }

    // Bind inline and context menu events
    tableBody.addEventListener('click', function (e) {
        const tr = e.target.closest('tr');
        if (!tr) return;

        // Quick edit or Dropdown edit
        if (e.target.classList.contains('quick-edit-btn') || e.target.classList.contains('dropdown-edit-btn')) {
            openEditModalForStudent(tr);
        }

        // Quick delete or Dropdown delete
        if (e.target.classList.contains('quick-delete-btn') || e.target.classList.contains('dropdown-delete-btn')) {
            openDeleteModalForStudents([{
                matricule: tr.dataset.matricule,
                name: tr.dataset.name
            }]);
        }
    });

    // Bind Bulk action buttons
    bulkEditBtn.addEventListener('click', function () {
        const checkedBox = document.querySelector('.row-checkbox:checked');
        if (checkedBox) {
            const tr = checkedBox.closest('tr');
            openEditModalForStudent(tr);
        }
    });

    bulkDeleteBtn.addEventListener('click', function () {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const students = [];
        checkedBoxes.forEach(cb => {
            const tr = cb.closest('tr');
            students.push({
                matricule: cb.value,
                name: tr.dataset.name
            });
        });
        if (students.length > 0) {
            openDeleteModalForStudents(students);
        }
    });

    // Close buttons for modals
    document.getElementById('close-edit-modal').addEventListener('click', () => editModal.classList.remove('open'));
    document.getElementById('cancel-edit-modal').addEventListener('click', () => editModal.classList.remove('open'));
    
    document.getElementById('close-delete-modal').addEventListener('click', () => deleteModal.classList.remove('open'));
    document.getElementById('cancel-delete-modal').addEventListener('click', () => deleteModal.classList.remove('open'));
});
</script>
</body>
</html>
