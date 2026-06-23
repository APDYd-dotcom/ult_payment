<?php 
ini_set("display_error", 1);
ini_set("display_setup_error", 1);
error_reporting(E_ALL);

?>

<html>
<head>
<title>ULT Payment System</title>
<link rel="stylesheet" href="./styles.css">
</head>
<body>
<?php
    if(isset($_POST["Create"])){
        $getreference = $_POST["reference"];
        $getfullName = $_POST["fullName"];
        $getAmount = $_POST["Amount"];
        $getreason = $_POST["reason"];
    }
?>

<div class="container">

  <aside id="sidebar" class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <main id="main-content" class="main-content">
    <section id="partial" class="page active">

    <h1 class="page-title">Partial Payments</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>

                <tr>
                    <th>Partial Payment</th>
                    <th>Student</th>
                    <th>Amount Paid</th>
                    <th>Balance</th>
                </tr>

                <tr>
                    <td><?php echo $getreference ?></td>
                    <td><?php echo $getfullName ?></td>
                    <td><?php echo $getAmount ?></td>
                    <td><?php echo $getreason ?></td>
                </tr>

                <tr>
                    <td>PP002</td>
                    <td>Irakoze Yvan</td>
                    <td>40000</td>
                    <td>10000</td>
                </tr>

            </table>

        </div>


    </div>

</section>

  </main>

</div>

</body>
</html>




