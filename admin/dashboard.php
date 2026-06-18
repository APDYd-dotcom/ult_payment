<?php

    define('REQUIRED_ROLE', 'admin');
    require '../auth_check.php'; 

   // --- Fetch data from the view ---
    $stmtStudents = $bdd->query("SELECT * FROM vw_students_with_department ORDER BY matricule");
    $stmtPayements = $bdd->query("SELECT * FROM vw_payment_details");
    $stmtPenalites = $bdd->query("SELECT * FROM vw_penalites");
    $stmtPartialPayments = $bdd->query("SELECT * FROM vw_partial_payments");
    
    
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);
    $payements = $stmtPayements->fetchAll(PDO::FETCH_ASSOC);
    $penalites = $stmtPenalites->fetchAll(PDO::FETCH_ASSOC);
    $partialPayments = $stmtPartialPayments->fetchAll(PDO::FETCH_ASSOC);

    $totalStudents = count($students);
    $totalPayments = count($payements);
    $totalPenalites = count($penalites);
    $totalPartialPayments = count($partialPayments);    
?>




<!DOCTYPE html>
<html>
<head>
    <title>ULT Payment System</title>
    <link rel="stylesheet" href="./styles.css">
</head>
<body>

<div class="container">

    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>

    <main id="main-content" class="main-content">
        <section id="dashboard" class="page active">

            <h1 class="page-title">Dashboard</h1>

            <div class="cards">
                <div class="card">
                    <h3>Total Students</h3>
                    <p><?= $totalStudents ?></p>  
                </div>
                <div class="card">
                    <h3>Total Payments</h3>
                    <p><?= $totalPayments ?></p>   
                </div>
                <div class="card">
                    <h3>Partial Payments</h3>
                    <p><?= $totalPartialPayments ?></p>
                </div>
                <div class="card">
                    <h3>Penalties</h3>
                    <p><?= $totalPenalites ?></p>
                </div>
            </div>

            <div class="dashboard-table">
                <h3>All Students</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Department</th>
                            <th>Minerval Total</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['matricule']) ?></td>
                                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                                    <td><?= $row['age'] ?></td>
                                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                                    <td><?= number_format($row['minerval_total'], 2) ?> BIF</td>
                                    <td><?= date('Y-m-d', strtotime($row['student_created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6">No students found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>
    </main>

</div>

</body>
</html>