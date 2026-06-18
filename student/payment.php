<html>
<head>
  <title>ULT Payment System</title>
  <link rel="stylesheet" href="./styles.css">
</head>

<body>

<?php
    if (isset($_POST["Create"])) {
        $getreference = $_POST["reference"];
        $getfullName = $_POST["fullName"] ;
        $getamount = $_POST["amount"];
        $getdate = $_POST["date"];
    }
?>

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
                    <td><?php echo $getreference ?></td>
                    <td><?php echo $getfullName ?></td>
                    <td><?php echo $getamount ?></td>
                    <td><?php echo $getdate ?></td>
                </tr>

                <tr>
                    <td>P002</td>
                    <td>Irakoze Yvan</td>
                    <td>70000</td>
                    <td>2026-07-16</td>
                </tr>

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

</body>
</html>




