<?php
declare(strict_types=1);
// admin/report_helpers.php - Helpers for the payment reports feature

const REPORT_PERIODS = ['day', 'week', 'month', 'year'];
const REPORT_METHODS = ['IBBM+', 'BANKOBU', 'OTHER'];

function getReportFilters(): array {
    $f = [
        'period'     => trim($_GET['period'] ?? ''),
        'date_from'  => trim($_GET['date_from'] ?? ''),
        'date_to'    => trim($_GET['date_to'] ?? ''),
        'department' => trim($_GET['department'] ?? ''),
        'tranche'    => trim($_GET['tranche'] ?? ''),
        'method'     => trim($_GET['method'] ?? ''),
        'q'          => trim($_GET['q'] ?? ''),
    ];

    if (!in_array($f['period'], REPORT_PERIODS, true)) {
        $f['period'] = '';
    }

    if (!in_array($f['method'], REPORT_METHODS, true)) {
        $f['method'] = '';
    }

    $hasExplicitDates = $f['date_from'] !== '' && $f['date_to'] !== '';
    if ($hasExplicitDates) {
        if (!isValidReportDate($f['date_from']) || !isValidReportDate($f['date_to'])) {
            $f['date_from'] = '';
            $f['date_to'] = '';
        }
    } elseif ($f['period'] !== '') {
        $computed = computeReportPeriod($f['period']);
        $f['date_from'] = $computed['date_from'];
        $f['date_to']   = $computed['date_to'];
    } else {
        $f['date_from'] = '';
        $f['date_to']   = '';
    }

    return $f;
}

function isValidReportDate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $parts = explode('-', $date);
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
}

function computeReportPeriod(string $period): array {
    $now = new DateTimeImmutable();
    $from = '';
    $to = '';
    if ($period === 'day') {
        $from = $now->format('Y-m-d');
        $to = $now->format('Y-m-d');
    } elseif ($period === 'week') {
        $from = $now->modify('-6 days')->format('Y-m-d');
        $to = (new DateTimeImmutable())->format('Y-m-d');
    } elseif ($period === 'month') {
        $from = $now->modify('first day of this month')->format('Y-m-d');
        $to = $now->modify('last day of this month')->format('Y-m-d');
    } elseif ($period === 'year') {
        $from = $now->modify('first day of January')->format('Y-m-d');
        $to = $now->modify('last day of December')->format('Y-m-d');
    }
    return ['date_from' => $from, 'date_to' => $to];
}

function getPaymentReport(PDO $bdd, array $f): array {
    $sql = "
        SELECT payment_id, payment_reference, student_name, matricule, department_name,
               tranche_name, amount, payment_method, reference_number, payment_date
        FROM vw_payment_details
    ";
    $where = [];
    $params = [];

    if ($f['date_from'] !== '' && $f['date_to'] !== '') {
        $where[] = 'DATE(payment_date) BETWEEN :date_from AND :date_to';
        $params[':date_from'] = $f['date_from'];
        $params[':date_to']   = $f['date_to'];
    }
    if ($f['department'] !== '') {
        $where[] = 'department_name = :department';
        $params[':department'] = $f['department'];
    }
    if ($f['tranche'] !== '') {
        $where[] = 'tranche_name = :tranche';
        $params[':tranche'] = $f['tranche'];
    }
    if ($f['method'] !== '') {
        $where[] = 'payment_method = :method';
        $params[':method'] = $f['method'];
    }
    if ($f['q'] !== '') {
        $where[] = '(student_name LIKE :q OR matricule LIKE :q OR reference_number LIKE :q OR payment_reference LIKE :q)';
        $params[':q'] = '%' . $f['q'] . '%';
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY payment_date DESC';

    $stmt = $bdd->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function summarizeReport(array $rows): array {
    $total = 0.0;
    $byMethod = [];
    foreach ($rows as $row) {
        $amount = (float) ($row['amount'] ?? 0);
        $total += $amount;
        $method = (string) ($row['payment_method'] ?? 'OTHER');
        if (!isset($byMethod[$method])) {
            $byMethod[$method] = ['count' => 0, 'total' => 0.0];
        }
        $byMethod[$method]['count']++;
        $byMethod[$method]['total'] += $amount;
    }
    return [
        'count' => count($rows),
        'total' => $total,
        'by_method' => $byMethod,
    ];
}

function reportPeriodLabel(array $f): string {
    if ($f['date_from'] !== '' && $f['date_to'] !== '') {
        if ($f['date_from'] === $f['date_to']) {
            return 'du ' . $f['date_from'];
        }
        return 'du ' . $f['date_from'] . ' au ' . $f['date_to'];
    }
    return match ($f['period']) {
        'day' => "Aujourd'hui",
        'week' => 'Cette semaine (7 derniers jours)',
        'month' => 'Ce mois-ci',
        'year' => 'Cette annee',
        default => 'Toute la periode',
    };
}
