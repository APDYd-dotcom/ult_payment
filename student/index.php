<html>
<head>
<title>ULT Payment System</title>
  <style>
    .flex {
         flex:1;
    }
  </style>
</head>

<body>

<div class="container">
    <main id="main-content" class="flex">
        <?php
            $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

            switch ($page) {
                case 'dashboard':
                    include 'dashboard.php';
                    break;
                case 'student':
                    include 'student.php';
                    break;
                case 'payment':
                    include 'payment.php';
                    break;
                case 'partial':
                    include 'partial.php';
                    break;
                case 'penalty':
                    include 'penalty.php';
                    break;
                case 'mailing':
                    include 'mailing.php';
                    break;
                default:
                    echo "<h1>Page not found</h1>";
            }
        ?>

    </main>

</div>


</body>
</html>
