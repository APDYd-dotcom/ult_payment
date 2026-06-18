<?php 
    define("REQUIRE_LORE","admin");
    require "auth_check.php";  
   
    $stmtMailing = $bdd->query("SELECT * FROM mailing_list ORDER BY id");

    $mailinglist = $stmtMailing ->fetchAll(PDO::ASSOC);

?>

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
    <section id="mailing" class="page active">

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
              <?php if ($mailinglist): ?>
                    <?php foreach($mailinglist as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row["id"]) ?></td>
                            <td><?= htmlspecialchars($row["full_name"]) ?></td>
                            <td><?= htmlspecialchars($row["email"]) ?></td>
                            <td><?= htmlspecialchars($row["status"]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No subscriber found.</td></tr>
              <?php endif; ?> 
            </table>

        </div>

    </div>

</section>

  </main>

</div>


</body>
</html>




