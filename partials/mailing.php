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
    <section id="mailing" class="page">

    <h1 class="page-title">Mailing List</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>

                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Status</th>
                </tr>

                <tr>
                    <td>1</td>
                    <td>Arakaza Prince</td>
                    <td>prince@gmail.com</td>
                    <td>Subscribed</td>
                </tr>

                <tr>
                    <td>2</td>
                    <td>Irakoze Yvan</td>
                    <td>yvan@gmail.com</td>
                    <td>Subscribed</td>
                </tr>

            </table>

        </div>

        <div class="form-section">

            <h3>Mailing List Form</h3>

            <form>

                <label>Subscriber ID</label>
                <input type="text">

                <label>Full Name</label>
                <input type="text">

                <label>Email Address</label>
                <input type="email">

                <label>Status</label>
                <input type="text" placeholder="Subscribed">

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




