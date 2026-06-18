<?php 
    define('REQUIRED_ROLE', 'student');
    require_once __DIR__ . '/../auth_check.php';
?>

<div class="logo">
    <h2>ULT PAYMENT</h2>
</div>

<ul>
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="student.php">Students</a></li>
    <li><a href="payment.php">Payments</a></li>
    <li><a href="partial.php">Partial Payments</a></li>
    <li><a href="penalty.php">Penalties</a></li>

    <li>
        <form method="POST" action="/payment/logout.php">
            <input type="hidden" name="logout_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit">Logout</button>
        </form>
    </li>
</ul>
