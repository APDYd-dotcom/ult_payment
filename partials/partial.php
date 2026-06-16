<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ULT Payment System</title>
  <link rel="stylesheet" href="styles.css">
</head>

<body>

<div class="container">

  <aside id="sidebar" class="sidebar">
    <?php include 'partials/sidebar.php'; ?>
  </aside>

  <main id="main-content" class="main-content">
    <section id="partial" class="page">

    <h1 class="page-title">Partial Payments</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>

                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Amount Paid</th>
                    <th>Balance</th>
                </tr>

                <tr>
                    <td>PP001</td>
                    <td>Arakaza Prince</td>
                    <td>30000</td>
                    <td>20000</td>
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

            <form>

                <label>Partial Payment ID</label>
                <input type="text">

                <label>Student Matricule</label>
                <input type="text">

                <label>Amount Paid</label>
                <input type="number">

                <label>Remaining Balance</label>
                <input type="number">

                <div class="buttons">
                    <button type="button">Create</button>
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




