<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$message = '';
$messageType = '';

function translateDepartmentError($message)
{
    if (strpos($message, "for key 'name'") !== false && strpos($message, 'department') !== false) {
        return 'Ce nom de département existe déjà. Veuillez en choisir un autre.';
    }
    if (strpos($message, 'Duplicate entry') !== false) {
        return 'Une entrée en double a été détectée. Vérifiez les données saisies.';
    }
    return 'Une erreur est survenue : ' . htmlspecialchars($message);
}

// --- CRÉATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Create'])) {
    $Name = trim($_POST['Name'] ?? '');
    $minerval = floatval($_POST['minerval'] ?? 0);

    if (empty($Name)) {
        $message = 'Le nom du département est obligatoire.';
        $messageType = 'error';
    } elseif ($minerval <= 0) {
        $message = 'Le montant du minerval doit être supérieur à 0.';
        $messageType = 'error';
    } else {
        $checkStmt = $bdd->prepare("SELECT COUNT(*) FROM department WHERE name = ?");
        $checkStmt->execute([$Name]);
        if ($checkStmt->fetchColumn() > 0) {
            $message = "Ce nom de département existe déjà. Veuillez en choisir un autre.";
            $messageType = "error";
        } else {
            try {
                $bdd->beginTransaction();

                $stmt = $bdd->prepare("INSERT INTO department (name, minerval_total) VALUES (?, ?)");
                $stmt->execute([$Name, $minerval]);
                $departmentId = $bdd->lastInsertId();

                $currentYear = date('Y');
                $t1_start = getSystemSetting($bdd, 'tranche_1_derogation_deadline') ?: "$currentYear-03-25";
                $t1_end   = getSystemSetting($bdd, 'tranche_1_due_date') ?: "$currentYear-04-10";
                $t2_start = getSystemSetting($bdd, 'tranche_2_derogation_deadline') ?: "$currentYear-06-15";
                $t2_end   = getSystemSetting($bdd, 'tranche_2_due_date') ?: "$currentYear-06-30";
                $t3_start = getSystemSetting($bdd, 'tranche_3_derogation_deadline') ?: "$currentYear-08-31";
                $t3_end   = getSystemSetting($bdd, 'tranche_3_due_date') ?: "$currentYear-09-15";
                $t4_start = getSystemSetting($bdd, 'tranche_4_derogation_deadline') ?: "$currentYear-11-10";
                $t4_end   = getSystemSetting($bdd, 'tranche_4_due_date') ?: "$currentYear-11-15";

                $trancheDates = [
                    ['date_debut' => $t1_start, 'date_fin' => $t1_end],
                    ['date_debut' => $t2_start, 'date_fin' => $t2_end],
                    ['date_debut' => $t3_start, 'date_fin' => $t3_end],
                    ['date_debut' => $t4_start, 'date_fin' => $t4_end],
                ];

                $trancheStmt = $bdd->prepare("INSERT INTO tranche (department_id, name, date_debut, date_fin, due_date, derogation_deadline) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($trancheDates as $idx => $dates) {
                    $trancheName = $Name . " - Tranche " . ($idx + 1);
                    $trancheStmt->execute([$departmentId, $trancheName, $dates['date_debut'], $dates['date_fin'], $dates['date_fin'], $dates['date_debut']]);
                }

                logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'department_created', "Nom: $Name, Minerval: $minerval");

                $bdd->commit();
                header('Location: departement.php?success=1');
                exit();
            } catch (PDOException $e) {
                if ($bdd->inTransaction()) {
                    $bdd->rollBack();
                }
                $message = translateDepartmentError($e->getMessage());
                $messageType = 'error';
            }
        }
    }
}

// --- MISE À JOUR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Update'])) {
    $id = intval($_POST['id'] ?? 0);
    $Name = trim($_POST['Name'] ?? '');
    $minerval = floatval($_POST['minerval'] ?? 0);

    if (empty($id) || empty($Name) || $minerval <= 0) {
        $message = 'Tous les champs sont obligatoires et doivent être valides.';
        $messageType = 'error';
    } else {
        try {
            $stmt = $bdd->prepare("UPDATE department SET name = ?, minerval_total = ? WHERE id = ?");
            $stmt->execute([$Name, $minerval, $id]);
            logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'department_updated', "ID: $id, Nouveau nom: $Name");
            header('Location: departement.php?success=2');
            exit();
        } catch (PDOException $e) {
            $message = translateDepartmentError($e->getMessage());
            $messageType = 'error';
        }
    }
}

// --- SUPPRESSION EN MASSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['DeleteBulk'])) {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        $message = 'Aucun département sélectionné.';
        $messageType = 'error';
    } else {
        try {
            $bdd->beginTransaction();
            $nameStmt = $bdd->prepare("SELECT name FROM department WHERE id = ?");
            $stmt = $bdd->prepare("DELETE FROM department WHERE id = ?");
            $deletedDepartmentNames = [];

            foreach ($ids as $id) {
                $nameStmt->execute([$id]);
                $deptName = $nameStmt->fetchColumn();
                if ($deptName !== false) {
                    $deletedDepartmentNames[] = $deptName;
                }
                $stmt->execute([$id]);
            }

            logActivity($bdd, $_SESSION['userId'] ?? null, $_SESSION['fullname'] ?? '', $_SESSION['email'] ?? '', 'department_deleted', 'Nom: ' . implode(', ', $deletedDepartmentNames));
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

// --- Messages flash ---
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $message = 'Département créé avec succès.';
        $messageType = 'success';
    } elseif ($_GET['success'] == 2) {
        $message = 'Département mis à jour avec succès.';
        $messageType = 'success';
    } elseif ($_GET['success'] == 3) {
        $message = 'Département(s) supprimé(s) avec succès.';
        $messageType = 'success';
    }
}

// --- RÉCUPÉRATION ---
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Départements - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .dept-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        @media (max-width: 992px) { .dept-layout { grid-template-columns: 1fr; } }

        .search-container { margin-bottom: 16px; }
        .search-bar {
            position: relative;
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            max-width: 420px;
            height: 44px;
        }
        .search-bar .search-icon {
            position: absolute;
            left: 14px;
            color: #64748b;
            pointer-events: none;
        }
        .search-bar input {
            width: 100%;
            height: 100%;
            border: none;
            outline: none;
            padding: 0 40px 0 42px;
            font-size: 0.95rem;
            background: transparent;
            border-radius: 10px;
        }
        .search-bar input::placeholder { color: #94a3b8; }
        .search-clear {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            display: none;
            padding: 4px;
        }
        .search-clear.visible { display: block; }
        .search-results-counter { font-size: 0.85rem; color: var(--gray-600); margin-top: 8px; }

        .options-cell { position: relative; text-align: right; }
        .three-dots-btn {
            background: none; border: none; cursor: pointer;
            color: var(--gray-600); padding: 4px 8px; border-radius: 6px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .three-dots-btn:hover { background: var(--gray-100); }
        .options-menu {
            position: absolute; right: 0; top: 100%; z-index: 10;
            background: #fff; border: 1px solid var(--gray-200);
            border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 140px; padding: 4px;
        }
        .options-menu.dropdown-hidden { display: none; }
        .menu-item {
            display: block; width: 100%; text-align: left;
            padding: 8px 12px; background: none; border: none;
            cursor: pointer; font-size: 0.9rem; color: var(--gray-900); border-radius: 6px;
        }
        .menu-item:hover { background: var(--gray-50); }

        .row-quick-actions { display: none; gap: 4px; }
        .student-name-cell:hover .row-quick-actions { display: flex; }
        .quick-action-btn {
            background: #fff; border: 1px solid var(--gray-200);
            border-radius: 6px; cursor: pointer; padding: 4px;
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-600); transition: all 0.2s;
        }
        .quick-action-btn:hover { background: var(--gray-50); color: var(--primary); }
        .quick-delete-btn:hover { color: var(--danger); border-color: #fca5a5; background: #fef2f2; }

        .floating-bulk-bar {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: var(--gray-900); color: #fff; padding: 12px 20px;
            border-radius: 999px; display: none; align-items: center; gap: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25); z-index: 1000;
        }
        .floating-bulk-bar.visible { display: flex; }
        .selection-count { font-size: 0.9rem; }
        .selection-count span { font-weight: 700; color: #60a5fa; }
        .bulk-actions { display: flex; gap: 8px; }
        .bulk-btn {
            background: rgba(255,255,255,0.1); color: #fff; border: none;
            padding: 8px 14px; border-radius: 999px; cursor: pointer;
            font-size: 0.85rem; font-weight: 600;
        }
        .bulk-btn:hover { background: rgba(255,255,255,0.2); }
        .bulk-btn-danger { background: var(--danger); }
        .bulk-btn-danger:hover { background: #b91c1c; }

        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5);
            z-index: 2000; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-card {
            background: #fff; border-radius: 16px; max-width: 480px; width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 24px; border-bottom: 1px solid var(--gray-100);
        }
        .modal-header h3 { color: var(--primary); font-size: 1.1rem; }
        .modal-close-btn {
            background: none; border: none; font-size: 1.5rem;
            cursor: pointer; color: var(--gray-600); line-height: 1;
        }
        .modal-body { padding: 24px; }
        .modal-body label {
            display: block; font-size: 0.85rem; font-weight: 600;
            color: var(--gray-600); margin-bottom: 6px; margin-top: 12px;
        }
        .modal-body input {
            width: 100%; padding: 10px 12px; border: 1px solid var(--gray-200);
            border-radius: 8px; font-size: 0.95rem;
        }
        .modal-footer {
            display: flex; justify-content: flex-end; gap: 10px;
            padding: 16px 24px; border-top: 1px solid var(--gray-100);
        }
        .modal-btn {
            padding: 9px 18px; border-radius: 8px; font-weight: 600;
            cursor: pointer; border: none; font-size: 0.9rem;
        }
        .modal-cancel-btn { background: var(--gray-200); color: var(--gray-900); }
        .modal-btn-primary { background: var(--primary-light); color: #fff; }
        .modal-btn-danger { background: var(--danger); color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section id="department" class="page active">
            <h1 class="page-title">Départements</h1>

            <?php if ($message): ?>
                <div class="alert <?= $messageType === 'success' ? 'alert-success' : 'alert-danger' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="dept-layout">
                <div class="page-card">
                    <h2>Liste des départements</h2>

                    <div class="search-container">
                        <div class="search-bar">
                            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input id="department-search" type="text" placeholder="Rechercher un département..." aria-label="Search departments">
                            <button type="button" id="clear-department-search" class="search-clear" aria-label="Clear search">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                        <div class="search-results-counter">
                            <strong id="counter-match">0</strong> sur <span id="counter-total">0</span> départements
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern" id="dept-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all-checkbox"></th>
                                    <th data-sort-index="1" data-sort-type="text">Nom du département</th>
                                    <th data-sort-index="2" data-sort-type="number">Minerval total</th>
                                    <th style="width: 50px;"></th>
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
                                                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                                    <span class="student-name-text"><?= htmlspecialchars($row['name'] ?? '') ?></span>
                                                    <div class="row-quick-actions">
                                                        <button type="button" class="quick-action-btn quick-edit-btn" title="Modifier">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                            </svg>
                                                        </button>
                                                        <button type="button" class="quick-action-btn quick-delete-btn" title="Supprimer">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= number_format($row['minerval_total'], 2) ?> BIF</td>
                                            <td class="options-cell">
                                                <button type="button" class="three-dots-btn">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="1"></circle>
                                                        <circle cx="19" cy="12" r="1"></circle>
                                                        <circle cx="5" cy="12" r="1"></circle>
                                                    </svg>
                                                </button>
                                                <div class="options-menu dropdown-hidden">
                                                    <button type="button" class="menu-item dropdown-edit-btn">Modifier</button>
                                                    <button type="button" class="menu-item dropdown-delete-btn">Supprimer</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding: 20px;">Aucun département trouvé.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container hidden" id="department-pagination" style="margin-top:16px; display:flex; justify-content:center; gap:10px;">
                        <button type="button" id="department-prev" class="btn btn-secondary">Précédent</button>
                        <span id="department-page-info" style="padding:10px; color:var(--gray-600);">Page 1 sur 1</span>
                        <button type="button" id="department-next" class="btn btn-secondary">Suivant</button>
                    </div>
                </div>

                <div class="page-card" style="align-self: start;">
                    <h2>Ajouter un département</h2>
                    <form method="POST" action="departement.php" class="form-modern">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="Name">Nom du département</label>
                            <input id="Name" type="text" name="Name" required placeholder="ex: Informatique">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="minerval">Minerval total (BIF)</label>
                            <input id="minerval" type="number" step="0.01" name="minerval" required placeholder="ex: 785000" inputmode="numeric">
                        </div>
                        <div style="grid-column: 1 / -1; display:flex; gap:10px;">
                            <button type="submit" name="Create" class="btn btn-primary" style="flex:1;">Créer</button>
                            <button type="reset" class="btn btn-secondary" style="flex:1;">Effacer</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
</div>

<div class="floating-bulk-bar" id="bulk-action-bar">
    <span class="selection-count"><span id="selected-count-badge">0</span> sélectionné(s)</span>
    <div class="bulk-actions">
        <button type="button" class="bulk-btn" id="bulk-edit-btn">Modifier</button>
        <button type="button" class="bulk-btn bulk-btn-danger" id="bulk-delete-btn">Supprimer</button>
    </div>
</div>

<div class="modal-overlay" id="edit-dept-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Modifier le département</h3>
            <button type="button" class="modal-close-btn" id="close-edit-modal">&times;</button>
        </div>
        <form method="POST" action="departement.php">
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                <label for="edit-name">Nom du département</label>
                <input id="edit-name" type="text" name="Name" required>
                <label for="edit-minerval">Minerval total (BIF)</label>
                <input id="edit-minerval" type="number" step="0.01" name="minerval" required inputmode="numeric">
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-cancel-btn" id="cancel-edit-modal">Annuler</button>
                <button type="submit" name="Update" class="modal-btn modal-btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="delete-dept-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Confirmer la suppression</h3>
            <button type="button" class="modal-close-btn" id="close-delete-modal">&times;</button>
        </div>
        <form method="POST" action="departement.php">
            <div class="modal-body">
                <p id="delete-confirm-text">Êtes-vous sûr de vouloir supprimer ce département ?</p>
                <div id="delete-ids-hidden-container"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-cancel-btn" id="cancel-delete-modal">Annuler</button>
                <button type="submit" name="DeleteBulk" class="modal-btn modal-btn-danger">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script src="./table-sort.js?v=1.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('department-search');
    const clearButton = document.getElementById('clear-department-search');
    const tableBody = document.querySelector('#dept-table tbody');
    const paginationContainer = document.getElementById('department-pagination');
    const prevButton = document.getElementById('department-prev');
    const nextButton = document.getElementById('department-next');
    const pageInfo = document.getElementById('department-page-info');

    const rowsPerPage = 10;
    let currentPage = 1;

    const allRows = Array.from(tableBody.querySelectorAll('tr'));
    const dataRows = allRows.filter(row => {
        const cells = row.querySelectorAll('td');
        return cells.length > 0 && cells[0].getAttribute('colspan') === null;
    });

    const tableSorter = window.createTableSorter
        ? window.createTableSorter(document.getElementById('dept-table'), dataRows, function () {
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

    prevButton.addEventListener('click', () => {
        if (currentPage > 1) { currentPage--; renderPage(); }
    });
    nextButton.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(getFilteredRows().length / rowsPerPage));
        if (currentPage < totalPages) { currentPage++; renderPage(); }
    });

    renderPage();

    // --- Bulk actions ---
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkActionBar = document.getElementById('bulk-action-bar');
    const selectedCountBadge = document.getElementById('selected-count-badge');
    const bulkEditBtn = document.getElementById('bulk-edit-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    const editModal = document.getElementById('edit-dept-modal');
    const deleteModal = document.getElementById('delete-dept-modal');

    function updateBulkBar() {
        const count = document.querySelectorAll('.row-checkbox:checked').length;
        selectedCountBadge.textContent = count;
        bulkActionBar.classList.toggle('visible', count > 0);
        bulkEditBtn.style.display = count === 1 ? 'inline-flex' : 'none';
    }

    selectAllCheckbox.addEventListener('change', function () {
        const isChecked = selectAllCheckbox.checked;
        const visibleRows = getFilteredRows();
        rowCheckboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (visibleRows.includes(tr)) cb.checked = isChecked;
            else cb.checked = false;
        });
        updateBulkBar();
    });

    rowCheckboxes.forEach(cb => cb.addEventListener('change', updateBulkBar));

    document.querySelectorAll('.three-dots-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const menu = btn.nextElementSibling;
            document.querySelectorAll('.options-menu').forEach(m => { if (m !== menu) m.classList.add('dropdown-hidden'); });
            menu.classList.toggle('dropdown-hidden');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('.options-menu').forEach(m => m.classList.add('dropdown-hidden'));
    });

    function openEditModal(tr) {
        document.getElementById('edit-id').value = tr.dataset.id;
        document.getElementById('edit-name').value = tr.dataset.name;
        document.getElementById('edit-minerval').value = tr.dataset.minerval;
        editModal.classList.add('open');
    }

    function openDeleteModal(depts) {
        const container = document.getElementById('delete-ids-hidden-container');
        container.innerHTML = '';
        depts.forEach(d => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'ids[]'; input.value = d.id;
            container.appendChild(input);
        });
        const text = document.getElementById('delete-confirm-text');
        text.textContent = depts.length === 1
            ? `Êtes-vous sûr de vouloir supprimer le département "${depts[0].name}" ?`
            : `Êtes-vous sûr de vouloir supprimer les ${depts.length} départements sélectionnés ?`;
        deleteModal.classList.add('open');
    }

    tableBody.addEventListener('click', function (e) {
        const tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.classList.contains('quick-edit-btn') || e.target.closest('.quick-edit-btn')) {
            openEditModal(tr);
        }
        if (e.target.classList.contains('quick-delete-btn') || e.target.closest('.quick-delete-btn')) {
            openDeleteModal([{ id: tr.dataset.id, name: tr.dataset.name }]);
        }
    });

    bulkEditBtn.addEventListener('click', () => {
        const cb = document.querySelector('.row-checkbox:checked');
        if (cb) openEditModal(cb.closest('tr'));
    });

    bulkDeleteBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        const depts = [];
        checked.forEach(cb => {
            const tr = cb.closest('tr');
            depts.push({ id: cb.value, name: tr.dataset.name });
        });
        if (depts.length > 0) openDeleteModal(depts);
    });

    document.getElementById('close-edit-modal').addEventListener('click', () => editModal.classList.remove('open'));
    document.getElementById('cancel-edit-modal').addEventListener('click', () => editModal.classList.remove('open'));
    document.getElementById('close-delete-modal').addEventListener('click', () => deleteModal.classList.remove('open'));
    document.getElementById('cancel-delete-modal').addEventListener('click', () => deleteModal.classList.remove('open'));
});
</script>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
