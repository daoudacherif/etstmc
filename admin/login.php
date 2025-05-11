<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Code de connexion modifié pour gérer spécialement les utilisateurs "saler" et "admin"
if(isset($_POST['login']))
{
    $adminuser=$_POST['username'];
    $password=md5($_POST['password']);
    
    // Vérifie si le nom d'utilisateur est "saler" ou "admin"
    if($adminuser == 'saler' || $adminuser == 'admin') {
        // Pour les utilisateurs saler ou admin, on vérifie seulement le mot de passe
        $query=mysqli_query($con, "SELECT ID FROM tbladmin WHERE UserName='$adminuser' AND Password='$password' LIMIT 1");
        $ret=mysqli_fetch_array($query);
        if($ret>0){
            $_SESSION['imsaid']=$ret['ID'];
            header('location:dashboard.php');
        } else {
            echo '<script>alert("Mot de passe incorrect pour l\'utilisateur '.$adminuser.'.")</script>';
        }
    } else {
        // Pour les autres utilisateurs, comportement normal
        $query=mysqli_query($con, "SELECT ID FROM tbladmin WHERE UserName='$adminuser' AND Password='$password'");
        $ret=mysqli_fetch_array($query);
        if($ret>0){
            $_SESSION['imsaid']=$ret['ID'];
            header('location:dashboard.php');
        } else {
            echo '<script>alert("Détails invalides.")</script>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
        
<head>
    <title>Système de gestion d'inventaire || Page de connexion</title>
    <meta charset="UTF-8" />
            
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="css/matrix-login.css" />
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,800' rel='stylesheet' type='text/css'>
    <?php include_once('includes/responsive.php'); ?>
<body>
    <div id="loginbox">            
        <form id="loginform" class="form-vertical" method="post">
            <div class="control-group normal_text"> <h3>Inventaire</strong> <strong style="color: orange">Système</strong></h3></div>
            <div class="control-group">
                <div class="controls">
                    <div class="main_input_box">
                        <span class="add-on bg_lg"><i class="icon-user"> </i></span><input type="text" placeholder="Nom d'utilisateur" name="username" required="true" />
                    </div>
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    <div class="main_input_box">
                        <span class="add-on bg_ly"><i class="icon-lock"></i></span><input type="password" placeholder="Mot de passe" name="password" required="true"/>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <span class="pull-right"><input type="submit" class="btn btn-success" name="login" value="Se connecter"></span>
            </div>
        </form>
        <div style="padding-left: 180px;">
            <a href="../index.php" class="flip-link btn btn-info"><i class="icon-home"></i> Retour à l'accueil</a>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>  
    <script src="js/matrix.login.js"></script> 
</body>
</html>