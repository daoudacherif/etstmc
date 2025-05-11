<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if(isset($_POST['login']))
    {
        $adminuser=$_POST['username'];
        $password=($_POST['password']);
        $query=mysqli_query($con,"select ID from tbladmin where  UserName='$adminuser' && Password='$password' ");
        $ret=mysqli_fetch_array($query);
        if($ret>0){
            $_SESSION['imsaid']=$ret['ID'];
         header('location:dashboard.php');
        }
        else{
        
        echo '<script>alert("Détails invalides.")</script>';

        }
    }
    if(isset($_POST['submit']))
    {
        $contactno=$_POST['contactno'];
        $email=$_POST['email'];
$password=md5($_POST['newpassword']);
                $query=mysqli_query($con,"select ID from tbladmin where  Email='$email' and MobileNumber='$contactno' ");
                
        $ret=mysqli_num_rows($query);
        if($ret>0){
            $_SESSION['contactno']=$contactno;
            $_SESSION['email']=$email;
            $query1=mysqli_query($con,"update tbladmin set Password='$password'  where  Email='$email' && MobileNumber='$contactno'");
             if($query1)
     {
echo "<script>alert('Mot de passe changé avec succès');</script>";

     }
         
        }
        else{
        
            echo "<script>alert('Détails invalides. Veuillez réessayer.');</script>";
        }
    }
    ?>
<!DOCTYPE html>
<html lang="fr">
        
<head>
                <title>Système de gestion d'inventaire || Page de connexion</title><meta charset="UTF-8" />
                
        <link rel="stylesheet" href="css/bootstrap.min.css" />
        <link rel="stylesheet" href="css/bootstrap-responsive.min.css" />
                <link rel="stylesheet" href="css/matrix-login.css" />
                <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />
        <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,800' rel='stylesheet' type='text/css'>
<script type="text/javascript">
function checkpass()
{
if(document.changepassword.newpassword.value!=document.changepassword.confirmpassword.value)
{
alert('Le nouveau mot de passe et le champ de confirmation du mot de passe ne correspondent pas');
document.changepassword.confirmpassword.focus();
return false;
}
return true;
} 

</script>
<?php include_once('includes/responsive.php'); ?>
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
                                        <span class="pull-left"><a href="#" class="flip-link btn btn-info" id="to-recover">Mot de passe oublié?</a></span>
                                        <span class="pull-right"><input type="submit" class="btn btn-success" name="login" value="Se connecter"></span>

                                </div>
                        </form>
                        <div style="padding-left: 180px;">
                                        <a href="../index.php" class="flip-link btn btn-info" id="to-recover"><i class="icon-home"></i>  Retour à l'accueil</a>
                                 
                                </div>
                                <br />
                        <form id="recoverform" class="form-vertical" method="post" name="changepassword" onsubmit="return checkpass();">
                <p class="normal_text">Entrez votre adresse e-mail ci-dessous et nous vous enverrons des instructions pour récupérer un mot de passe.</p>
                
                                        <div class="controls">
                                                <div class="main_input_box">
                                                        <span class="add-on bg_lo"><i class="icon-envelope"></i></span><input type="text" placeholder="Adresse e-mail" name="email" required="true" />
                                                </div>
                                        </div>
                                        <br />
                             <div class="controls">
                                                <div class="main_input_box">
                                                        <span class="add-on bg_lo"><i class="icon-phone-sign"></i></span><input type="text" placeholder="Numéro de contact" name="contactno" required="true" />
                                                </div>
                                        </div>
                                        <br />
                                        <div class="controls">
                                                <div class="main_input_box">
                                                        <span class="add-on bg_lo"><i class="icon-lock"></i></span><input type="password" name="newpassword" placeholder="Nouveau mot de passe" required="true" />
                                                </div>
                                        </div>
                                        <br />
                                        <div class="controls">
                                                <div class="main_input_box">
                                                        <span class="add-on bg_lo"><i class="icon-lock"></i></span><input type="password" name="confirmpassword" placeholder="Confirmer le mot de passe" required="true" />
                                                </div>
                                        </div>
                                <div class="form-actions">
                                        <span class="pull-left"><a href="#" class="flip-link btn btn-success" id="to-login">&laquo; Retour à la connexion</a></span>
                                        <span class="pull-right"><input type="submit" class="btn btn-success" name="submit" value="Réinitialiser"></span>

                                </div>
                        </form>
                </div>
                
                <script src="js/jquery.min.js"></script>  
                <script src="js/matrix.login.js"></script> 
        </body>

</html>