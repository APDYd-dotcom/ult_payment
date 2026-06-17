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

        <div class="form-section">

            <h3>Penalty Form</h3>

            <form method="POST" action="penalty.php">

                <label for="ID">Penalty Reference</label>
                <input id="ID" type="text" name="ID">

                <label for="fullName">Student Name</label>
                <input id="fullName" type="text" name="fullName">

                <label for="reason">Reason</label>
                <input id="reason" type="text" name="reason">

                <label for="amount" >Penalty Amount</label>
                <input id="amount" type="number" name="amount">

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




