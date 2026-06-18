<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
    define('REQUIRED_ROLE', 'admin');
    require __DIR__ . '/../auth_check.php';  
   

    $stmtPayments = $bdd->query("SELECT * FROM vw_payment_details ORDER BY payment_reference");

    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

?>


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
    <section id="payment" class="page active">

    <h1 class="page-title">Payments</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>

                <tr>
                    <th>Payment Reference</th>
                    <th>Student Name</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>

                <tr>
                    
                </tr>
                <?php if ($payments): ?>
                    <?php foreach($payments as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row["payment_reference"]) ?></td>
                            <td><?= htmlspecialchars($row["student_name"]) ?></td>
                            <td><?= htmlspecialchars($row["amount"]) ?></td>
                            <td><?= htmlspecialchars($row["payment_date"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No payment found.</td></tr>
                <?php endif; ?>

            </table>

        </div>

        <div class="form-section">

            <h3>Payment Form</h3>

            <form method="POST" action="payment.php">

                <label for="reference">Payment Reference</label>
                <input id="reference" type="text" placeholder="P001" name="reference">

                <label for="fullName">Student Name</label>
                <input id="fullName" type="text" placeholder="Your Name" name="fullName">

                <label for="amount">Amount</label>
                <input id="amount" type="number" name="amount">

                <label for="date">Payment Date</label>
                <input id="date" type="date" name="date">

                <div class="buttons">
                    <button type="submit" name="Create">Create</button>
                    <button type="button">Update</button>
                    <button type="button">Delete</button>
                    <button type="reset">Clear</button>
                </div>

            </form>

        </div>

    </div>

</section>

  </main>

</div>


<?php
// if(isset($_POST["Create"])){
// 	 $getreference = $_POST["reference"];
//         $getfullName = $_POST["fullName"] ;
//         $getamount = $_POST["amount"];
//         $getdate = $_POST["date"];
// 	$insertProf = "insert into professeurs(nom,prenom,Specialite,email) 
// value('$recupNom','$recupPrenom','$recupSpecialiste','$recupEmail')";
// $bdd->exec($insertProf);
// header("location:afficheprofesseur.php");
// }
?>

</body>
</html>




