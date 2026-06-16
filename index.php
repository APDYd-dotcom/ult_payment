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
    <?php
        $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

        switch ($page) {
            case 'dashboard':
                include 'partials/dashboard.php';
                break;
            case 'student':
                include 'partials/student.php';
                break;
            case 'payment':
                include 'partials/payment.php';
                break;
            case 'partial':
                include 'partials/partial.php';
                break;
            case 'penalty':
                include 'partials/penalty.php';
                break;
            case 'mailing':
                include 'partials/mailing.php';
                break;
            default:
                echo "<h1>Page not found</h1>";
        }
    ?>
  </main>

</div>


</body>
</html>
