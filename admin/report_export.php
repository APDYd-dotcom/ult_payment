<?php
declare(strict_types=1);

define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/report_helpers.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$format = strtolower(trim($_GET['format'] ?? ''));
if (!in_array($format, ['pdf', 'xlsx'], true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Format invalide.';
    exit();
}

$filters = getReportFilters();
$rows = getPaymentReport($bdd, $filters);
$rowCount = count($rows);
$periodLabel = reportPeriodLabel($filters);

// Log de l'exportation
logActivity(
    $bdd,
    $_SESSION['userId'] ?? null,
    $_SESSION['fullname'] ?? '',
    $_SESSION['email'] ?? '',
    'EXPORT_REPORT',
    'format=' . $format . ' rows=' . $rowCount
);

$adminName = htmlspecialchars($_SESSION['fullname'] ?? 'Administrateur', ENT_QUOTES, 'UTF-8');
$generatedAt = date('d/m/Y H:i:s');
$fileStamp = date('Y-m-d_His');

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'Ref.',
        'Etudiant',
        'Matricule',
        'Departement',
        'Tranche',
        'Montant',
        'Mode',
        'Numero transaction',
        'Date',
    ];

    // Ligne d'en-tete (grasse)
    $col = 'A';
    foreach ($headers as $header) {
        $cell = $col . '1';
        $sheet->setCellValue($cell, $header);
        $sheet->getStyle($cell)->getFont()->setBold(true);
        $sheet->getStyle($cell)->getFont()->setName('DejaVu Sans');
        $sheet->getStyle($cell)->getFont()->setSize(11);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $col++;
    }

    // Lignes de donnees
    $rowNum = 2;
    foreach ($rows as $row) {
        $sheet->setCellValue('A' . $rowNum, htmlspecialchars_decode($row['payment_reference'] ?? '', ENT_QUOTES));
        $sheet->setCellValue('B' . $rowNum, htmlspecialchars_decode($row['student_name'] ?? '', ENT_QUOTES));
        $sheet->setCellValue('C' . $rowNum, htmlspecialchars_decode($row['matricule'] ?? '', ENT_QUOTES));
        $sheet->setCellValue('D' . $rowNum, htmlspecialchars_decode($row['department_name'] ?? '', ENT_QUOTES));
        $sheet->setCellValue('E' . $rowNum, htmlspecialchars_decode($row['tranche_name'] ?? '', ENT_QUOTES));
        $amountCell = 'F' . $rowNum;
        $sheet->setCellValue($amountCell, (float) ($row['amount'] ?? 0));
        $sheet->getStyle($amountCell)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue('G' . $rowNum, htmlspecialchars_decode($row['payment_method'] ?? '', ENT_QUOTES));
        $sheet->setCellValue('H' . $rowNum, htmlspecialchars_decode($row['reference_number'] ?? '', ENT_QUOTES));
        $sheet->setCellValue('I' . $rowNum, date('Y-m-d H:i', strtotime($row['payment_date'])));
        $rowNum++;
    }

    // Ligne TOTAL avec formule SOMME
    $lastRow = $rowNum - 1;
    if ($lastRow >= 2) {
        $sheet->setCellValue('A' . $rowNum, 'TOTAL');
        $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
        $totalCell = 'F' . $rowNum;
        $sheet->setCellValue($totalCell, '=SUM(F2:F' . $lastRow . ')');
        $sheet->getStyle($totalCell)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle($totalCell)->getFont()->setBold(true);
        $range = 'A' . $rowNum . ':I' . $rowNum;
        $sheet->getStyle($range)->getFont()->setBold(true);
    }

    // Mise en forme des colonnes
    foreach (range('A', 'I') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    $sheet->getStyle('A1:I' . max(1, $lastRow))->getFont()->setName('DejaVu Sans');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="rapport_paiements_' . $fileStamp . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new XlsxWriter($spreadsheet);
    $writer->save('php://output');
    exit();
}

if ($format === 'pdf') {
    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsHtml5ParserEnabled(true);

    $dompdf = new Dompdf($options);

    $escAdmin = $adminName;
    $escPeriod = htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8');

    $tableRows = '';
    $total = 0.0;
    foreach ($rows as $row) {
        $amount = (float) ($row['amount'] ?? 0);
        $total += $amount;
        $tableRows .= '<tr>'
            . '<td>' . htmlspecialchars($row['payment_reference'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($row['student_name'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($row['matricule'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($row['department_name'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($row['tranche_name'] ?? '') . '</td>'
            . '<td style="text-align:right;">' . number_format($amount, 2, ',', ' ') . '</td>'
            . '<td>' . htmlspecialchars($row['payment_method'] ?? '') . '</td>'
            . '<td>' . htmlspecialchars($row['reference_number'] ?? '') . '</td>'
            . '<td>' . date('Y-m-d H:i', strtotime($row['payment_date'])) . '</td>'
            . '</tr>';
    }

    $totalFormatted = number_format($total, 2, ',', ' ');

    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 1.2cm 1cm;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            color: #111;
        }
        h1 {
            font-size: 15pt;
            color: #1e3a8a;
            margin: 0 0 4px 0;
        }
        .meta {
            font-size: 8.5pt;
            color: #475569;
            margin-bottom: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 0.6pt solid #94a3b8;
            padding: 4px 6px;
            text-align: left;
            font-size: 8.5pt;
        }
        th {
            background: #1e3a8a;
            color: #fff;
        }
        td.total {
            font-weight: bold;
            background: #e0ecff;
        }
        .total-row td:first-child {
            font-weight: bold;
        }
        .footer {
            margin-top: 14px;
            font-size: 7.5pt;
            color: #64748b;
            border-top: 0.6pt solid #cbd5e1;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    <h1>Universite du Lac Tanganyika - Rapport des paiements</h1>
    <div class="meta">
        Genere le <strong>' . $generatedAt . '</strong> par <strong>' . $escAdmin . '</strong> | Periode: <strong>' . $escPeriod . '</strong> | Nombre de paiements: <strong>' . $rowCount . '</strong>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:10%;">Ref.</th>
                <th style="width:18%;">Etudiant</th>
                <th style="width:10%;">Matricule</th>
                <th style="width:14%;">Departement</th>
                <th style="width:14%;">Tranche</th>
                <th style="width:10%; text-align:right;">Montant</th>
                <th style="width:9%;">Mode</th>
                <th style="width:11%;">Numero transaction</th>
                <th style="width:14%;">Date</th>
            </tr>
        </thead>
        <tbody>
            ' . $tableRows . '
        </tbody>
    </table>
    <table style="margin-top:8px;">
        <tr>
            <td colspan="5" style="text-align:right; font-weight:bold;">Montant total :</td>
            <td style="text-align:right;" class="total">' . $totalFormatted . ' BIF</td>
            <td colspan="3"></td>
        </tr>
    </table>
    <div class="footer">
        ULT Payment System - Rapport genere a partir de vw_payment_details. Tous les montants sont en BIF.
    </div>
</body>
</html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="rapport_paiements_' . $fileStamp . '.pdf"');
    header('Cache-Control: max-age=0');
    echo $dompdf->output();
    exit();
}
