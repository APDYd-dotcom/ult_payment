
<style>
	h1{
		color:red;
		font-size:30px;
		border:green 2px solid;
		border-radius:25px/25px;
		margin-top:250px;
		margin-left:400px;
		padding:10px;
		width:400px;
	}
</style>
<?php
session_start();

$admin = "admin";
$motdepasse = "1234";

if (isset($_POST["connexion"])) {

    $username = $_POST["username"];
    $password = $_POST["password"];

    if ($username == $admin && $password == $motdepasse) {

        $_SESSION["admin"] = $username;

        header("Location: dashboard.php");
        exit();

    } else {
?>
<h1><?php
        echo "Nom d'utilisateur ou mot de passe incorrect.";
		header("Refresh:5 ; url=connexion.php");
		?>
</h1>
<?php
    }

}
?>