<html>
<head>
<title>ULT Payment System</title>
<link rel="stylesheet" href="./styles.css">
</head>

<body>

<div class="container">

  <aside id="sidebar" class="sidebar">
    <?php include 'sidebar.php'; ?>
  </aside>

  <main id="main-content" class="main-content">
    <section id="dashboard" class="page active">

    <h1 class="page-title">Dashboard</h1>

    <div class="cards">

        <div class="card">
            <h3>Total Students</h3>
            <p>150</p>
        </div>

        <div class="card">
            <h3>Total Payments</h3>
            <p>120</p>
        </div>

        <div class="card">
            <h3>Partial Payments</h3>
            <p>35</p>
        </div>

        <div class="card">
            <h3>Penalties</h3>
            <p>5</p>
        </div>

    </div>

    <div class="dashboard-table">

        <h3>Recent Payments</h3>

        <table>
            <tr>
                <th>Student</th>
                <th>Amount</th>
                <th>Date</th>
            </tr>

            <tr>
                <td>Prince</td>
                <td>50,000 BIF</td>
                <td>2026-07-15</td>
            </tr>

            <tr>
                <td>Yvan</td>
                <td>70,000 BIF</td>
                <td>2026-07-14</td>
            </tr>
        </table>

    </div>

</section>

  </main>

</div>

</body>
</html>




