<html>
<head>
<title>ULT Payment System</title>
<link rel="stylesheet" href="./styles.css">
</head>

<body>
    <?php
        if(isset($_POST["Create"])){
            $getId = $_POST["ID"];
            $getFullName = $_POST["fullName"];
            $getreason = $_POST["reason"];
            $getAmount = $_POST["amount"];

        }
    ?>

<div class="container">

  <aside id="sidebar" class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <main id="main-content" class="main-content">
    <section id="penalty" class="page active">

    <h1 class="page-title">Penalties</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>

                <tr>
                    <th>Penalty Reference</th>
                    <th>Student</th>
                    <th>Reason</th>
                    <th>Amount</th>
                </tr>

                <tr>
                    <td><?php echo $getId; ?></td>
                    <td><?php echo $getFullName; ?></td>
                    <td><?php echo $getreason; ?></td>
                    <td><?php echo $getAmount; ?></td>
                </tr>

                <tr>
                    <td>PN002</td>
                    <td>Irakoze Yvan</td>
                    <td>Missing Document</td>
                    <td>5000</td>
                </tr>

            </table>

        </div>

    </div>

</section>

  </main>

</div>

</body>
</html>




