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
    <section id="payment" class="page">

    <h1 class="page-title">Payments</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>

                <tr>
                    <th>Payment ID</th>
                    <th>Student</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>

                <tr>
                    <td>P001</td>
                    <td>Arakaza Prince</td>
                    <td>50000</td>
                    <td>2026-07-15</td>
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

            <form>

                <label>Payment ID</label>
                <input type="text" placeholder="P001">

                <label>Student Matricule</label>
                <input type="text" placeholder="S001">

                <label>Amount</label>
                <input type="number">

                <label>Payment Date</label>
                <input type="date">

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




