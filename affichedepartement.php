
<html lang="fr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Gestion des Étudiants</title>

<style>

body{
    font-family: Arial, sans-serif;
    background-color:#f4f4f4;
    margin:0;
    padding:0;
}

header{
    background:#003366;
    color:white;
    text-align:center;
    padding:20px;
}

h1{
    margin:0;
}

table{
    width:90%;
    margin:30px auto;
    border-collapse:collapse;
    background:white;
    box-shadow:0px 0px 10px rgba(0,0,0,0.2);
}

th{
    background:#0055aa;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
}

table,th,td{
    border:1px solid #ccc;
}

tr:nth-child(even){
    background:#f9f9f9;
}

tr:hover{
    background:#e6f2ff;
}

a{
    text-decoration:none;
    color:white;
    padding:8px 12px;
    border-radius:5px;
}

.modifier{
    background:black;
}

.supprimer{
    background:red;
}

.ajouter{
    background:green;
}

a:hover{
    opacity:0.8;
}

footer{
    text-align:center;
    padding:15px;
    background:#003366;
    color:white;
    margin-top:20px;
}

</style>

</head>

<body>

<?php
include("menu.php");
?>

<?php
if(isset($_POST["depart"])){
	$recupNomDpartemeent=$_POST["nomd"];
	$recupDescription=$_POST["desc"];
	$insertdep="insert into inscriptions(nom_dept,description) value('$recupNomDpartemeent','$recupDescription')";
	$bdd=exec($insertdep);
	header("location:affichedepartement.php");
}
?>

<header>
    <h1>GESTION DES DEPARTEMENT</h1>
</header>

<table>

<tr></tr>

<caption>
<h2>Liste des Partements</h2>
</caption>

<tr>
    <th>Nom Departement</th>
    <th>Description </th>
    <th colspan="3">Actions</th>
</tr>
<?php
$affichede=$bdd->query("select * from departements order by id_dept desc");
while($datadep=$affichede->fetch()){
?>
<tr>
    <td><?php echo $datadep['nom_dept']; ?></td>
    <td><?php echo $datadep['description']; ?></td>
    <td>
        <a href="modifidepartement.php?modif=<?php echo $datadep['id_dept'];?>" class="modifier">
            Modifier
        </a>
    </td>

    <td>
        <a href="affichedepartement.php?supp=<?php echo $datadep['id_dept']; ?>"  class="supprimer">
            Supprimer
        </a>
    </td>

    <td>
        <a href="ajoutedepartement.php" class="ajouter">
            Ajouter
        </a>
    </td>
</tr>
<?php
}
?>
<?php
if($_GET['supp']){
$suppdep=$bdd->exec("delete  from departements 
where id_dept= ".$_GET['supp']."");
}
?>

</table>

<footer>
    © 2026 Université du Burundi - Système de Gestion Universitaire
</footer>

</body>

</html>

