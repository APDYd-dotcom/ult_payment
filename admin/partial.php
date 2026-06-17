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

        <div class="form-section">

            <h3>Partial Payment Form</h3>

            <form method="POST" action="partial.php">

                <label for="reference">Partial Payment</label>
                <input id="reference" type="text" name="reference">

                <label for="fullName">Student Name</label>
                <input id="fullName" type="text" name="fullName">

                <label for="Amount">Amount Paid</label>
                <input id="Amount" type="number" name="Amount">

                <label for="reason">Remaining Balance</label>
                <input id="reason" type="number" name="reason">

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

</body>
</html>




