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
    <section id="penalty" class="page">

    <h1 class="page-title">Penalties</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>

                <tr>
                    <th>Penalty ID</th>
                    <th>Student</th>
                    <th>Reason</th>
                    <th>Amount</th>
                </tr>

                <tr>
                    <td>PN001</td>
                    <td>Arakaza Prince</td>
                    <td>Late Payment</td>
                    <td>10000</td>
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

            <form>

                <label>Penalty ID</label>
                <input type="text">

                <label>Student Matricule</label>
                <input type="text">

                <label>Reason</label>
                <input type="text">

                <label>Penalty Amount</label>
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




