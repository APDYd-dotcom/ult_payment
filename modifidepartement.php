
<html lang="fr">

<head>
<meta charset="UTF-8">
<title>Modifier Département</title>

<style>

body{
    background:#dff5df;
    font-family:Arial,sans-serif;


}
.content{
	display:flex;
    justify-content:center;
	margin-top:40px;
}
table{
    background:white;
    padding:20px;
    border-radius:10px;
}

td{
    padding:8px;
}

input{
    width:220px;
    padding:8px;

    border:none;
    outline:none;

    background:#f5f5f5;
    border-radius:5px;
}

h2{
    text-align:center;
    color:green;
}

.btn1{
    background:black;
    color:white;

    border:none;

    padding:8px 15px;

    border-radius:5px;

    cursor:pointer;
}

.btn2{
    background:red;
    color:white;

    border:none;

    padding:8px 15px;

    border-radius:5px;

    cursor:pointer;
}

</style>

</head>

<body>
<?php
include("menu.php");
$affichede=$bdd->query("select * from departements where id_dept=" .$_GET['modif']." ");
$datadep=$affichede->fetch();
?>
<div class="content">
<form method="POST">

<table>

<tr>
<td colspan="2">
<h2>Modifier Un Département</h2>
</td>
</tr>

<tr>
<td>Nom Departement </td>
<td><input type="text" value="<?php echo $datadep['nom_dept']; ?>" name="nomdep"></td>
</tr>

<tr>
<td>Description</td>
<td><input type="text" value="<?php echo $datadep['description']; ?>" name="descrip"></td>
</tr>

<tr>

<td colspan="2" align="center">

<input type="submit"
value="Modifier" name="modifie"
class="btn1">

<input type="reset"
value="Annuler"
class="btn2">

</td>

</tr>

</table>

</form>
<?php
if(isset($_POST['modifie'])){
	$recupNomDpartemeent=$_POST["nomdep"];
	$recupDescription=$_POST["descrip"];
	$modifdart=$bdd->exec("update departements set 
	nom_dept='".$recupNomDpartemeent."',description='".$recupDescription."'
	where id_dept='".$_GET['modif']."' ");
	header("location:affichedepartement.php");
}
?>
</div>
</body>

</html>

