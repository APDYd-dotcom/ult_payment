<html>
<head>
  <title>ULT Payment System</title>
  <link rel="stylesheet" href="./styles.css">
</head>
<body>
<?php
$getmatricul = $getName = $getDepartment = '';
if (isset($_POST["Create"])) {
        $getmatricul = trim($_POST['matricul']);
        $getName = $_POST['fullName'];
        $getDepartment = $_POST['department'];
}
?>
<div class="container">
    <aside id="sidebar" class="sidebar">
        <?php include 'sidebar.php'; ?>
    </aside>
    <main id="main-content" class="main-content">
        <section id="student" class="page active">
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
                            <td><?php echo htmlspecialchars($getmatricul); ?></td>
                            <td><?php echo $getName; ?></td>
                            <td><?php echo $getDepartment; ?></td>
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
                    <form method="POST" action="student.php">
                        <label for="matricul">Matricule</label>
                        <input id="matricul" type="text" name="matricul" required>
                        <label for="fullName">Full Name</label>
                        <input id="fullName" type="text" name="fullName" required>
                        <label for="department">Department</label>
                        <input id="department" type="text" name="department" required>
                        <div class="buttons">
                            <button type="submit" value="Create" name="Create">Create</button>
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


