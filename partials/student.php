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
    <section id="student" class="page">

    <h1 class="page-title">Students</h1>

    <div class="crud-container">

        <div class="table-section">

            <table>
                <tr>
                    <th>Matricule</th>
                    <th>Name</th>
                    <th>Department</th>
                </tr>

                <tr>
                    <td>S001</td>
                    <td>Arakaza Prince</td>
                    <td>GL</td>
                </tr>

                <tr>
                    <td>S002</td>
                    <td>Irakoze Yvan</td>
                    <td>IR</td>
                </tr>
		<tr>
		   <td>S003</td>
		   <td>Ndikumana Desin</td>
		   <td>GL</td>
		</tr>
            </table>

        </div>

        <div class="form-section">

            <h3>Student Form</h3>

            <form>

                <label>Matricule</label>
                <input type="text">

                <label>Full Name</label>
                <input type="text">

                <label>Department</label>
                <input type="text">

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


