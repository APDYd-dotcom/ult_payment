<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');
ini_set('memory_limit', '256M');

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

// --- Colonnes disponibles ---
$availableColumns = [
    'payment_reference' => 'Référence',
    'student_name'      => 'Étudiant',
    'matricule'         => 'Matricule',
    'department_name'   => 'Département',
    'tranche_name'      => 'Tranche',
    'amount'            => 'Montant',
    'payment_method'    => 'Méthode',
    'payment_date'      => 'Date',
];

$selectedColumns = $_GET['columns'] ?? array_keys($availableColumns);

// --- Filtres ---
$filterDepartment = $_GET['department'] ?? '';
$filterTranche    = $_GET['tranche'] ?? '';
$filterDateStart  = $_GET['date_start'] ?? '';
$filterDateEnd    = $_GET['date_end'] ?? '';
$filterMethod     = $_GET['payment_method'] ?? '';

// --- Construction de la requête SQL ---
$sql = "
    SELECT 
        CONCAT('P-', LPAD(p.id, 4, '0')) AS payment_reference,
        s.name AS student_name,
        s.matricule,
        d.name AS department_name,
        t.name AS tranche_name,
        p.amount,
        p.payment_method,
        p.created_at AS payment_date
    FROM payment p
    JOIN student s ON p.student_id = s.id
    JOIN tranche t ON p.tranche_id = t.id
    JOIN department d ON t.department_id = d.id
";

$where = [];
$params = [];

if ($filterDepartment !== '') {
    $where[] = 'd.name = ? COLLATE utf8mb4_unicode_ci';
    $params[] = $filterDepartment;
}
if ($filterTranche !== '') {
    $where[] = 't.name = ? COLLATE utf8mb4_unicode_ci';
    $params[] = $filterTranche;
}
if ($filterMethod !== '') {
    $where[] = 'p.payment_method = ?';
    $params[] = $filterMethod;
}
if ($filterDateStart !== '') {
    $where[] = 'p.created_at >= ?';
    $params[] = $filterDateStart . ' 00:00:00';
}
if ($filterDateEnd !== '') {
    $where[] = 'p.created_at <= ?';
    $params[] = $filterDateEnd . ' 23:59:59';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.created_at DESC';

try {
    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    $allPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur lors du chargement des données : ' . $e->getMessage());
}

// --- Listes pour les filtres ---
$departments = $bdd->query("SELECT name FROM department ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$tranches = $bdd->query("SELECT name FROM tranche ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$paymentMethods = ['IBBM+', 'BANKOBU', 'OTHER'];

// --- Gestion des exports (logique inchangée) ---
$exportFormat = $_GET['export'] ?? '';
if ($exportFormat === 'pdf' || $exportFormat === 'excel') {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (ob_get_length()) ob_end_clean();

    if ($exportFormat === 'excel') {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $colLetter = 'A';
        foreach ($selectedColumns as $colKey) {
            $sheet->setCellValue($colLetter . '1', $availableColumns[$colKey]);
            $colLetter++;
        }

        $rowNum = 2;
        foreach ($allPayments as $p) {
            $colLetter = 'A';
            foreach ($selectedColumns as $colKey) {
                $value = $p[$colKey] ?? '';
                if ($colKey === 'payment_date') {
                    $value = date('d/m/Y H:i', strtotime($value));
                }
                $sheet->setCellValue($colLetter . $rowNum, $value);
                $colLetter++;
            }
            $rowNum++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="rapport_personnalise.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit();
    } elseif ($exportFormat === 'pdf') {
        $html = '<h1 style="text-align:center;color:#1e3a8a;">Rapport personnalisé</h1>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0" style="width:100%;border-collapse:collapse;font-family:sans-serif;font-size:12px;">';

        $html .= '<thead><tr style="background:#1e3a8a;color:white;">';
        foreach ($selectedColumns as $colKey) {
            $html .= '<th>' . htmlspecialchars($availableColumns[$colKey]) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($allPayments as $p) {
            $html .= '<tr>';
            foreach ($selectedColumns as $colKey) {
                $value = $p[$colKey] ?? '';
                if ($colKey === 'payment_date') {
                    $value = date('d/m/Y H:i', strtotime($value));
                } elseif ($colKey === 'amount') {
                    $value = number_format($value, 2) . ' BIF';
                }
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('rapport_personnalise.pdf', ['Attachment' => true]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Rapport personnalisé - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .custom-report-layout { display: flex; flex-direction: column; gap: 24px; }
        .columns-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }
        .columns-grid label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
            padding: 8px 12px;
            background: var(--gray-50);
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }
        .columns-grid input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-light);
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .format-options {
            display: flex;
            gap: 20px;
            margin-top: 12px;
        }
        .format-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            cursor: pointer;
        }
        .format-options input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-light);
        }
        .export-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active">
            <h1 class="page-title">Rapport personnalisé</h1>

            <div class="custom-report-layout">
                <!-- Bloc Configuration -->
                <div class="page-card">
                    <h2>Configuration du rapport</h2>
                    <form method="GET" action="custom_reports.php" id="report-form">
                        <h3>Colonnes à inclure</h3>
                        <div class="columns-grid">
                            <?php foreach ($availableColumns as $key => $label): ?>
                                <label>
                                    <input type="checkbox" name="columns[]" value="<?= $key ?>" 
                                        <?= in_array($key, $selectedColumns) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <h3 style="margin-top: 32px;">Filtres</h3>
                        <div class="filters-grid">
                            <div class="form-group">
                                <label for="date_start">Date début</label>
                                <input type="date" id="date_start" name="date_start" value="<?= htmlspecialchars($filterDateStart) ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_end">Date fin</label>
                                <input type="date" id="date_end" name="date_end" value="<?= htmlspecialchars($filterDateEnd) ?>">
                            </div>
                            <div class="form-group">
                                <label for="department">Département</label>
                                <select id="department" name="department">
                                    <option value="">Tous</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= htmlspecialchars($d['name']) ?>" <?= $filterDepartment == $d['name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="tranche">Tranche</label>
                                <select id="tranche" name="tranche">
                                    <option value="">Toutes</option>
                                    <?php foreach ($tranches as $t): ?>
                                        <option value="<?= htmlspecialchars($t['name']) ?>" <?= $filterTranche == $t['name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="payment_method">Mode de paiement</label>
                                <select id="payment_method" name="payment_method">
                                    <option value="">Tous</option>
                                    <?php foreach ($paymentMethods as $m): ?>
                                        <option value="<?= $m ?>" <?= $filterMethod == $m ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h3 style="margin-top: 32px;">Format de sortie</h3>
                        <div class="format-options">
                            <label>
                                <input type="radio" name="export" value="pdf" <?= ($exportFormat === 'pdf' || $exportFormat === '') ? 'checked' : '' ?>>
                                PDF
                            </label>
                            <label>
                                <input type="radio" name="export" value="excel" <?= $exportFormat === 'excel' ? 'checked' : '' ?>>
                                Excel (.xlsx)
                            </label>
                        </div>

                        <div class="export-actions">
                            <button type="submit" class="btn btn-primary">Générer le rapport</button>
                            <a href="custom_reports.php" class="btn btn-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </div>

                <!-- Bloc Aperçu -->
                <div class="page-card">
                    <h2>Aperçu des données</h2>
                    <div class="table-responsive">
                        <table class="table-modern" id="preview-table">
                            <thead>
                                <tr>
                                    <?php foreach ($availableColumns as $colKey => $colLabel): ?>
                                        <th data-col="<?= $colKey ?>" style="<?= in_array($colKey, $selectedColumns) ? '' : 'display:none;' ?>">
                                            <?= htmlspecialchars($colLabel) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($allPayments): ?>
                                    <?php foreach ($allPayments as $p): ?>
                                        <tr>
                                            <?php foreach ($availableColumns as $colKey => $colLabel): ?>
                                                <td data-col="<?= $colKey ?>" style="<?= in_array($colKey, $selectedColumns) ? '' : 'display:none;' ?>">
                                                    <?php 
                                                    $value = $p[$colKey] ?? '';
                                                    if ($colKey === 'payment_date') {
                                                        echo date('d/m/Y H:i', strtotime($value));
                                                    } elseif ($colKey === 'amount') {
                                                        echo number_format($value, 2) . ' BIF';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?= count($availableColumns) ?>" style="text-align:center; padding:20px;">Aucune donnée trouvée avec ces critères.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('input[name="columns[]"]');
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const colKey = this.value;
            const isChecked = this.checked;
            const cells = document.querySelectorAll(`#preview-table [data-col="${colKey}"]`);
            
            cells.forEach(cell => {
                cell.style.display = isChecked ? '' : 'none';
            });
        });
    });
});
</script>
</body>
</html>
