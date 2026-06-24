<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';   // $bdd est défini ici

$message = '';
$messageType = ''; // success / error

// --- Traduction personnalisée des erreurs pour les départements ---
function translateDepartmentError($message) {
    $translations = [
        'Duplicate entry' => ' Ce nom de département existe déjà. Veuillez en choisir un autre.',
        'Cannot add or update a child row' => ' Ce département est référencé par d\'autres tables et ne peut pas être modifié.',
        'foreign key constraint fails' => ' Impossible de supprimer ce département car il contient des étudiants ou des transactions enregistrés.',
        'You have an error in your SQL syntax' => ' Erreur de syntaxe SQL. Vérifiez les données saisies.',
    ];
    foreach ($translations as $key => $value) {
        if (strpos($message, $key) !== false) {
            return $value;
        }
    }
    // Si aucun message connu, on retourne le message original avec une icône
    return '❌ ' . htmlspecialchars($message);
}

// --- Gestion du formulaire (INSERT, UPDATE, DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['Create'])) {
        $Name     = trim($_POST['Name'] ?? '');
        $minerval = floatval($_POST['minerval'] ?? 0);

        // Validation basique
        if (empty($Name)) {
            $message = '⚠ Le nom du département est obligatoire.';
            $messageType = 'error';
        } elseif ($minerval <= 0) {
            $message = '⚠ Le montant du minerval doit être supérieur à 0.';
            $messageType = 'error';
        } else {
            try {
                // Insertion directe
                $stmt = $bdd->prepare("INSERT INTO department (name, minerval_total) VALUES (?, ?)");
                $stmt->execute([$Name, $minerval]);

                // Succès → redirection avec message
                header('Location: departement.php?success=1');
                exit();
            } catch (PDOException $e) {
                // Traduire le message d'erreur
                $message = translateDepartmentError($e->getMessage());
                $messageType = 'error';
            }
        }
    }

    if (isset($_POST['Update'])) {
        $id       = intval($_POST['id'] ?? 0);
        $Name     = trim($_POST['Name'] ?? '');
        $minerval = floatval($_POST['minerval'] ?? 0);

        if (empty($id) || empty($Name) || $minerval <= 0) {
            $message = '⚠️ Tous les champs sont obligatoires et doivent être valides.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $bdd->prepare("UPDATE department SET name = ?, minerval_total = ? WHERE id = ?");
                $stmt->execute([$Name, $minerval, $id]);
                header('Location: departement.php?success=2');
                exit();
            } catch (PDOException $e) {
                $message = translateDepartmentError($e->getMessage());
                $messageType = 'error';
            }
        }
    }

    if (isset($_POST['DeleteBulk'])) {
        $ids = $_POST['ids'] ?? [];
        if (empty($ids)) {
            $message = '⚠️ Aucun département sélectionné.';
            $messageType = 'error';
        } else {
            try {
                $bdd->beginTransaction();
                $stmt = $bdd->prepare("DELETE FROM department WHERE id = ?");
                foreach ($ids as $id) {
                    $stmt->execute([$id]);
                }
                $bdd->commit();
                header('Location: departement.php?success=3');
                exit();
            } catch (PDOException $e) {
                $bdd->rollBack();
                $message = translateDepartmentError($e->getMessage());
                $messageType = 'error';
            }
        }
    }
}

// --- Affichage d'un message de succès si redirigé ---
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $message = ' Département créé avec succès.';
        $messageType = 'success';
    } elseif ($_GET['success'] == 2) {
        $message = ' Département mis à jour avec succès.';
        $messageType = 'success';
    } elseif ($_GET['success'] == 3) {
        $message = ' Département(s) supprimé(s) avec succès.';
        $messageType = 'success';
    }
}

// --- Récupération des départements ---
try {
    $stmtDepartments = $bdd->query("SELECT * FROM department ORDER BY name ASC");
    $departments = $stmtDepartments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors de la récupération des départements : ' . $e->getMessage());
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
<section id="department" class="page active">
<h1 class="page-title">Départements</h1>

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
                                placeholder="Search departments..."
                                aria-label="Search departments"
                            />
                            <button type="button" id="clear-payment-search" class="clear-btn" aria-label="Clear search">
                                <span class="clear-icon">✕</span>
                            </button>
                        </div>
                        <div class="search-results-counter" id="search-counter">
                            Found <strong id="counter-match">0</strong> of <span id="counter-total">0</span> departments
                        </div>
                    </div>
<table>
<thead>
<tr>
<th><input type="checkbox" id="select-all-checkbox"></th>
<th>Nom du département</th>
<th>Minerval total</th>
<th></th>
</tr>
</thead>
<tbody>
<?php if ($departments): ?>
<?php foreach ($departments as $row): ?>
<tr data-id="<?= htmlspecialchars($row['id']) ?>" 
    data-name="<?= htmlspecialchars($row['name']) ?>" 
    data-minerval="<?= htmlspecialchars($row['minerval_total']) ?>">
<td><input type="checkbox" class="row-checkbox" value="<?= htmlspecialchars($row['id']) ?>"></td>
<td class="student-name-cell">
    <span class="student-name-text"><?= htmlspecialchars($row['name'] ?? '') ?></span>
    <div class="row-quick-actions">
        <button type="button" class="quick-action-btn quick-edit-btn" title="Modifier">✏️</button>
        <button type="button" class="quick-action-btn quick-delete-btn" title="Supprimer">🗑️</button>
    </div>
</td>
<td><?= number_format($row['minerval_total'], 2) ?> BIF</td>
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
<tr><td colspan="4">Aucun département trouvé.</td></tr>
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
<h3>Ajouter un département</h3>
<form method="POST" action="departement.php">
<label for="Name">Nom du département</label>
<input id="Name" type="text" name="Name" required placeholder="e.g. Informatique">

<label for="minerval">Minerval total</label>
<input id="minerval" type="number" step="0.01" name="minerval" required placeholder="e.g. 785000">

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

<!-- Floating Action Bar (YouTube Studio style) -->
<div class="floating-bulk-bar" id="bulk-action-bar">
    <span class="selection-count"><span id="selected-count-badge">0</span> selected</span>
    <div class="bulk-actions">
        <button type="button" class="bulk-btn" id="bulk-edit-btn">✏️ Edit</button>
        <button type="button" class="bulk-btn bulk-btn-danger" id="bulk-delete-btn">🗑️ Delete</button>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal-overlay" id="edit-dept-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Modifier le Département</h3>
            <button type="button" class="modal-close-btn" id="close-edit-modal">&times;</button>
        </div>
        <form method="POST" action="departement.php">
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                
                <label for="edit-name">Nom du département</label>
                <input id="edit-name" type="text" name="Name" required>

                <label for="edit-minerval">Minerval total</label>
                <input id="edit-minerval" type="number" step="0.01" name="minerval" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-cancel-btn" id="cancel-edit-modal">Cancel</button>
                <button type="submit" name="Update" class="modal-btn modal-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="delete-dept-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Confirm Deletion</h3>
            <button type="button" class="modal-close-btn" id="close-delete-modal">&times;</button>
        </div>
        <form method="POST" action="departement.php">
            <div class="modal-body">
                <p id="delete-confirm-text">Are you sure you want to delete the selected department?</p>
                <div id="delete-ids-hidden-container"></div>
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

    // ==========================================
    // YouTube Studio Interactive Features JS
    // ==========================================

    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkActionBar = document.getElementById('bulk-action-bar');
    const selectedCountBadge = document.getElementById('selected-count-badge');
    const bulkEditBtn = document.getElementById('bulk-edit-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    // Modals
    const editModal = document.getElementById('edit-dept-modal');
    const deleteModal = document.getElementById('delete-dept-modal');

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

    // Edit department handler
    function openEditModalForDept(tr) {
        const id = tr.dataset.id;
        const name = tr.dataset.name;
        const minerval = tr.dataset.minerval;

        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-minerval').value = minerval;

        editModal.classList.add('open');
    }

    // Delete department handler
    function openDeleteModalForDepts(depts) {
        const hiddenContainer = document.getElementById('delete-ids-hidden-container');
        hiddenContainer.innerHTML = ''; // clear

        depts.forEach(dept => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = dept.id;
            hiddenContainer.appendChild(input);
        });

        const confirmText = document.getElementById('delete-confirm-text');
        if (depts.length === 1) {
            confirmText.textContent = `Are you sure you want to delete the department "${depts[0].name}"?`;
        } else {
            confirmText.textContent = `Are you sure you want to delete the ${depts.length} selected departments?`;
        }

        deleteModal.classList.add('open');
    }

    // Bind inline and context menu events
    tableBody.addEventListener('click', function (e) {
        const tr = e.target.closest('tr');
        if (!tr) return;

        if (e.target.classList.contains('quick-edit-btn') || e.target.classList.contains('dropdown-edit-btn')) {
            openEditModalForDept(tr);
        }

        if (e.target.classList.contains('quick-delete-btn') || e.target.classList.contains('dropdown-delete-btn')) {
            openDeleteModalForDepts([{
                id: tr.dataset.id,
                name: tr.dataset.name
            }]);
        }
    });

    // Bind Bulk action buttons
    bulkEditBtn.addEventListener('click', function () {
        const checkedBox = document.querySelector('.row-checkbox:checked');
        if (checkedBox) {
            const tr = checkedBox.closest('tr');
            openEditModalForDept(tr);
        }
    });

    bulkDeleteBtn.addEventListener('click', function () {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const depts = [];
        checkedBoxes.forEach(cb => {
            const tr = cb.closest('tr');
            depts.push({
                id: cb.value,
                name: tr.dataset.name
            });
        });
        if (depts.length > 0) {
            openDeleteModalForDepts(depts);
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
