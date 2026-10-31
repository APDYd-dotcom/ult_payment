<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$error = '';
$success = false;

// --- Filtres ---
$filterDepartment = trim($_GET['department'] ?? '');
$filterTranche    = trim($_GET['tranche'] ?? '');
$filterDateStart  = trim($_GET['date_start'] ?? '');
$filterDateEnd    = trim($_GET['date_end'] ?? '');
$activeFilterCount = count(array_filter([$filterDepartment, $filterTranche, $filterDateStart, $filterDateEnd], fn($v) => $v !== ''));

// --- CRÉATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Create'])) {
    $matricule      = trim($_POST['matricule'] ?? '');
    $amount         = floatval($_POST['amount'] ?? 0);
    $department     = trim($_POST['department'] ?? '');
    $tranche        = trim($_POST['tranche'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $reference      = trim($_POST['reference_number'] ?? '');

    if (empty($matricule) || $amount <= 0 || empty($department) || empty($tranche) || empty($payment_method) || empty($reference)) {
        $error = 'Tous les champs sont obligatoires et le montant doit être > 0.';
    } else {
        try {
            $stmt = $bdd->prepare("CALL sp_payment_create_full(?, ?, ?, ?, ?, ?)");
            $stmt->execute([$matricule, $amount, $department, $tranche, $payment_method, $reference]);
            $stmt->closeCursor();
            header('Location: payment.php?success=1');
            exit();
        } catch (PDOException $e) {
            $error = translatePaymentError($e->getMessage());
        }
    }
}

// --- MODIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Update'])) {
    $paymentId = intval($_POST['payment_id'] ?? 0);
    $amount    = floatval($_POST['amount'] ?? 0);
    $method    = trim($_POST['payment_method'] ?? '');
    $reference = trim($_POST['reference_number'] ?? '');

    if ($paymentId <= 0 || $amount <= 0 || empty($method)) {
        $error = 'Données invalides pour la modification.';
    } else {
        try {
            $stmt = $bdd->prepare("UPDATE payment SET amount = ?, payment_method = ?, reference_number = ? WHERE id = ?");
            $stmt->execute([$amount, $method, $reference, $paymentId]);
            header('Location: payment.php?success=2');
            exit();
        } catch (PDOException $e) {
            $error = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }
}

// --- SUPPRESSION EN MASSE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['DeleteBulk'])) {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        $error = 'Aucun paiement sélectionné.';
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $bdd->prepare("DELETE FROM payment WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            header('Location: payment.php?success=3');
            exit();
        } catch (PDOException $e) {
            $error = 'Erreur lors de la suppression : ' . $e->getMessage();
        }
    }
}

// --- Messages flash ---
if (isset($_GET['success'])) {
    $success = true;
}

// --- RÉCUPÉRATION DES DONNÉES POUR LES FILTRES ---
$departments = $bdd->query("SELECT id, name FROM department ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $bdd->query("SELECT id, name, department_id FROM tranche ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// --- MÉTHODES DE PAIEMENT ---
$paymentMethods = [];
$stmt = $bdd->query("SHOW COLUMNS FROM payment LIKE 'payment_method'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $enumStr = substr($row['Type'], 5, -1);
    $paymentMethods = array_map(fn($v) => trim($v, "'"), explode(',', $enumStr));
}

// --- RÉCUPÉRATION DES PAIEMENTS (avec filtres) ---
try {
    $sql = "
        SELECT p.id, s.name AS student_name, s.matricule, d.name AS department_name, t.name AS tranche_name,
               p.amount, p.payment_method, p.reference_number, p.created_at AS payment_date
        FROM payment p
        JOIN student s ON p.student_id = s.id
        JOIN tranche t ON p.tranche_id = t.id
        JOIN department d ON t.department_id = d.id
        WHERE 1=1
    ";
    $params = [];
    if ($filterDepartment !== '') { $sql .= " AND d.name = ?"; $params[] = $filterDepartment; }
    if ($filterTranche !== '') { $sql .= " AND t.name = ?"; $params[] = $filterTranche; }
    if ($filterDateStart !== '') { $sql .= " AND p.created_at >= ?"; $params[] = $filterDateStart . ' 00:00:00'; }
    if ($filterDateEnd !== '') { $sql .= " AND p.created_at <= ?"; $params[] = $filterDateEnd . ' 23:59:59'; }
    $sql .= " ORDER BY p.created_at DESC";

    $stmtPayments = $bdd->prepare($sql);
    $stmtPayments->execute($params);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des paiements : ' . $e->getMessage());
}

$deptMap = [];
foreach ($departments as $d) { $deptMap[$d['name']] = $d['id']; }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Paiements - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .payment-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        @media (max-width: 992px) { .payment-layout { grid-template-columns: 1fr; } }

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
        .search-clear { position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; display: none; padding: 4px; }
        .search-clear.visible { display: block; }
        .search-results-counter { font-size: 0.85rem; color: var(--gray-600); margin-top: 8px; }

        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .filters-grid .form-group { margin: 0; }
        .filters-actions { display: flex; gap: 10px; margin-top: 12px; }

        .options-cell { position: relative; text-align: right; }
        .three-dots-btn { background: none; border: none; cursor: pointer; color: var(--gray-600); padding: 4px 8px; border-radius: 6px; }
        .three-dots-btn:hover { background: var(--gray-100); }
        .options-menu { position: absolute; right: 0; top: 100%; z-index: 10; background: #fff; border: 1px solid var(--gray-200); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); min-width: 140px; padding: 4px; }
        .options-menu.dropdown-hidden { display: none; }
        .menu-item { display: block; width: 100%; text-align: left; padding: 8px 12px; background: none; border: none; cursor: pointer; font-size: 0.9rem; color: var(--gray-900); border-radius: 6px; }
        .menu-item:hover { background: var(--gray-50); }

        .row-quick-actions { display: none; gap: 4px; }
        .student-name-cell:hover .row-quick-actions { display: flex; }
        .quick-action-btn { background: #fff; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center; color: var(--gray-600); }
        .quick-action-btn:hover { background: var(--gray-50); color: var(--primary); }
        .quick-delete-btn:hover { color: var(--danger); border-color: #fca5a5; background: #fef2f2; }

        .floating-bulk-bar {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: var(--gray-900); color: #fff; padding: 12px 20px;
            border-radius: 999px; display: none; align-items: center; gap: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25); z-index: 1000;
        }
        .floating-bulk-bar.visible { display: flex; }
        .bulk-actions { display: flex; gap: 8px; }
        .bulk-btn { background: rgba(255,255,255,0.1); color: #fff; border: none; padding: 8px 14px; border-radius: 999px; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
        .bulk-btn:hover { background: rgba(255,255,255,0.2); }
        .bulk-btn-danger { background: var(--danger); }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-card { background: #fff; border-radius: 16px; max-width: 480px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--gray-100); }
        .modal-header h3 { color: var(--primary); font-size: 1.1rem; }
        .modal-close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray-600); }
        .modal-body { padding: 24px; }
        .modal-body label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; margin-top: 12px; }
        .modal-body input, .modal-body select { width: 100%; padding: 10px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 0.95rem; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--gray-100); }
        .modal-btn { padding: 9px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-cancel-btn { background: var(--gray-200); color: var(--gray-900); }
        .modal-btn-primary { background: var(--primary-light); color: #fff; }
        .modal-btn-danger { background: var(--danger); color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section id="payment" class="page active">
            <h1 class="page-title">Paiements</h1>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span>✓</span>
                    <?php
                    if ($_GET['success'] == 1) echo 'Paiement créé avec succès.';
                    elseif ($_GET['success'] == 2) echo 'Paiement mis à jour avec succès.';
                    elseif ($_GET['success'] == 3) echo 'Paiement(s) supprimé(s) avec succès.';
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <span>!</span> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Filtres avancés -->
            <div class="page-card">
                <h2>Filtres avancés <?php if ($activeFilterCount > 0): ?><span class="badge badge-info"><?= $activeFilterCount ?> actif(s)</span><?php endif; ?></h2>
                <form method="GET" action="payment.php" class="filters-grid">
                    <div class="form-group">
                        <label for="filter_department">Département</label>
                        <select id="filter_department" name="department">
                            <option value="">Tous</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['name']) ?>" <?= $filterDepartment === $dept['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter_tranche">Tranche</label>
                        <select id="filter_tranche" name="tranche">
                            <option value="">Toutes</option>
                            <?php foreach ($tranches as $t): ?>
                                <option value="<?= htmlspecialchars($t['name']) ?>" data-dept="<?= $t['department_id'] ?>" <?= $filterTranche === $t['name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_start">Date début</label>
                        <input id="date_start" type="date" name="date_start" value="<?= htmlspecialchars($filterDateStart) ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_end">Date fin</label>
                        <input id="date_end" type="date" name="date_end" value="<?= htmlspecialchars($filterDateEnd) ?>">
                    </div>
                    <div class="filters-actions" style="grid-column: 1 / -1;">
                        <button type="submit" class="btn btn-primary">Appliquer</button>
                        <a href="payment.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <div class="payment-layout">
                <!-- Colonne gauche : liste -->
                <div class="page-card">
                    <h2>Liste des paiements</h2>
                    <div class="search-container">
                        <div class="search-bar">
                            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input id="payment-search" type="text" placeholder="Rechercher par matricule, nom, département...">
                            <button type="button" id="clear-payment-search" class="search-clear">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                        <div class="search-results-counter">
                            <strong id="counter-match">0</strong> sur <span id="counter-total">0</span> paiements
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table-modern" id="payment-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="select-all-checkbox"></th>
                                    <th data-sort-index="1" data-sort-type="text">Référence</th>
                                    <th data-sort-index="2" data-sort-type="text">Étudiant</th>
                                    <th data-sort-index="3" data-sort-type="text">Matricule</th>
                                    <th data-sort-index="4" data-sort-type="text">Département</th>
                                    <th data-sort-index="5" data-sort-type="text">Tranche</th>
                                    <th data-sort-index="6" data-sort-type="number">Montant</th>
                                    <th data-sort-index="7" data-sort-type="text">Méthode</th>
                                    <th data-sort-index="8" data-sort-type="date">Date</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments): ?>
                                    <?php foreach ($payments as $row): ?>
                                        <tr data-id="<?= $row['id'] ?>"
                                            data-amount="<?= $row['amount'] ?>"
                                            data-method="<?= htmlspecialchars($row['payment_method']) ?>"
                                            data-reference="<?= htmlspecialchars($row['reference_number'] ?? '') ?>">
                                            <td><input type="checkbox" class="row-checkbox" value="<?= $row['id'] ?>"></td>
                                            <td>PAY-<?= str_pad($row['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                            <td class="student-name-cell">
                                                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                                    <span><?= htmlspecialchars($row['student_name']) ?></span>
                                                    <div class="row-quick-actions">
                                                        <button type="button" class="quick-action-btn quick-edit-btn" title="Modifier">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                        </button>
                                                        <button type="button" class="quick-action-btn quick-delete-btn" title="Supprimer">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($row['matricule']) ?></td>
                                            <td><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td><?= htmlspecialchars($row['tranche_name']) ?></td>
                                            <td><?= number_format($row['amount'], 2) ?> BIF</td>
                                            <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($row['payment_date'])) ?></td>
                                            <td class="options-cell">
                                                <button type="button" class="three-dots-btn">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                                </button>
                                                <div class="options-menu dropdown-hidden">
                                                    <button type="button" class="menu-item dropdown-edit-btn">Modifier</button>
                                                    <button type="button" class="menu-item dropdown-delete-btn">Supprimer</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" style="text-align: center; padding: 20px;">Aucun paiement trouvé.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-container hidden" id="payment-pagination" style="margin-top:16px; display:flex; justify-content:center; gap:10px;">
                        <button type="button" id="payment-prev" class="btn btn-secondary">Précédent</button>
                        <span id="payment-page-info" style="padding:10px; color:var(--gray-600);">Page 1 sur 1</span>
                        <button type="button" id="payment-next" class="btn btn-secondary">Suivant</button>
                    </div>
                </div>

                <!-- Colonne droite : formulaire -->
                <div class="page-card" style="align-self: start;">
                    <h2>Ajouter un paiement</h2>
                    <form method="POST" action="payment.php" class="form-modern">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="matricule">Matricule étudiant</label>
                            <input id="matricule" type="text" name="matricule" placeholder="S-001" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="amount">Montant (BIF)</label>
                            <input id="amount" type="number" step="0.01" name="amount" placeholder="0.00" required inputmode="numeric">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="department">Département</label>
                            <select id="department" name="department" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="tranche">Tranche</label>
                            <select id="tranche" name="tranche" required>
                                <option value="">-- Sélectionner d'abord un département --</option>
                                <?php foreach ($tranches as $t): ?>
                                    <option value="<?= htmlspecialchars($t['name']) ?>" data-dept="<?= $t['department_id'] ?>">
                                        <?= htmlspecialchars($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="payment_method">Mode de paiement</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="reference_number">Référence</label>
                            <input id="reference_number" type="text" name="reference_number" placeholder="Ex: TRX-12345" required>
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

<!-- Barre d'actions bulk -->
<div class="floating-bulk-bar" id="bulk-action-bar">
    <span><span id="selected-count-badge">0</span> sélectionné(s)</span>
    <div class="bulk-actions">
        <button type="button" class="bulk-btn" id="bulk-edit-btn">Modifier</button>
        <button type="button" class="bulk-btn bulk-btn-danger" id="bulk-delete-btn">Supprimer</button>
    </div>
</div>

<!-- Modal Modification -->
<div class="modal-overlay" id="edit-payment-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Modifier le paiement</h3>
            <button type="button" class="modal-close-btn" id="close-edit-modal">&times;</button>
        </div>
        <form method="POST" action="payment.php">
            <div class="modal-body">
                <input type="hidden" name="payment_id" id="edit-payment-id">
                <label for="edit-amount">Montant (BIF)</label>
                <input id="edit-amount" type="number" step="0.01" name="amount" required>
                <label for="edit-method">Mode de paiement</label>
                <select id="edit-method" name="payment_method" required>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?= htmlspecialchars($method) ?>"><?= htmlspecialchars($method) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="edit-reference">Référence</label>
                <input id="edit-reference" type="text" name="reference_number">
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-cancel-btn" id="cancel-edit-modal">Annuler</button>
                <button type="submit" name="Update" class="modal-btn modal-btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Suppression -->
<div class="modal-overlay" id="delete-payment-modal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Confirmer la suppression</h3>
            <button type="button" class="modal-close-btn" id="close-delete-modal">&times;</button>
        </div>
        <form method="POST" action="payment.php">
            <div class="modal-body">
                <p id="delete-confirm-text">Êtes-vous sûr de vouloir supprimer ce paiement ?</p>
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
    // --- Filtres dynamiques ---
    const deptSelect = document.getElementById('department');
    const trancheSelect = document.getElementById('tranche');
    const allTrancheOptions = Array.from(trancheSelect.querySelectorAll('option[data-dept]'));
    const deptMap = <?= json_encode($deptMap) ?>;

    function updateTranches() {
        const selectedDept = deptSelect.value;
        const deptId = deptMap[selectedDept] || null;
        allTrancheOptions.forEach(opt => {
            const optDept = parseInt(opt.dataset.dept, 10);
            opt.style.display = (deptId && optDept === deptId) ? '' : 'none';
        });
        trancheSelect.value = '';
    }
    deptSelect.addEventListener('change', updateTranches);
    updateTranches();

    // --- Recherche et pagination ---
    const searchInput = document.getElementById('payment-search');
    const clearButton = document.getElementById('clear-payment-search');
    const tableBody = document.querySelector('#payment-table tbody');
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

    const tableSorter = window.createTableSorter
        ? window.createTableSorter(document.getElementById('payment-table'), dataRows, function () { currentPage = 1; renderPage(); })
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

    // --- Bulk actions ---
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkActionBar = document.getElementById('bulk-action-bar');
    const selectedCountBadge = document.getElementById('selected-count-badge');
    const bulkEditBtn = document.getElementById('bulk-edit-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');

    const editModal = document.getElementById('edit-payment-modal');
    const deleteModal = document.getElementById('delete-payment-modal');

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

    // Menus 3 points
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
        document.getElementById('edit-payment-id').value = tr.dataset.id;
        document.getElementById('edit-amount').value = tr.dataset.amount;
        document.getElementById('edit-method').value = tr.dataset.method;
        document.getElementById('edit-reference').value = tr.dataset.reference;
        editModal.classList.add('open');
    }

    function openDeleteModal(payments) {
        const container = document.getElementById('delete-ids-hidden-container');
        container.innerHTML = '';
        payments.forEach(p => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'ids[]'; input.value = p.id;
            container.appendChild(input);
        });
        const text = document.getElementById('delete-confirm-text');
        text.textContent = payments.length === 1
            ? `Êtes-vous sûr de vouloir supprimer ce paiement ?`
            : `Êtes-vous sûr de vouloir supprimer les ${payments.length} paiements sélectionnés ?`;
        deleteModal.classList.add('open');
    }

    tableBody.addEventListener('click', function (e) {
        const tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.closest('.quick-edit-btn') || e.target.closest('.dropdown-edit-btn')) openEditModal(tr);
        if (e.target.closest('.quick-delete-btn') || e.target.closest('.dropdown-delete-btn')) openDeleteModal([{ id: tr.dataset.id }]);
    });

    bulkEditBtn.addEventListener('click', () => {
        const cb = document.querySelector('.row-checkbox:checked');
        if (cb) openEditModal(cb.closest('tr'));
    });

    bulkDeleteBtn.addEventListener('click', () => {
        const checked = document.querySelectorAll('.row-checkbox:checked');
        const payments = [];
        checked.forEach(cb => payments.push({ id: cb.value }));
        if (payments.length > 0) openDeleteModal(payments);
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
