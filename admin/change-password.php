<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
  } else{
    if(isset($_POST['submit']))
{
$adminid=$_SESSION['imsaid'];
$cpassword=md5($_POST['currentpassword']);
$newpassword=md5($_POST['newpassword']);
$query=mysqli_query($con,"select ID from tbladmin where ID='$adminid' and   Password='$cpassword'");
$row=mysqli_fetch_array($query);
if($row>0){
$ret=mysqli_query($con,"update tbladmin set Password='$newpassword' where ID='$adminid'");

echo '<script>alert("Votre mot de passe a été changé avec succès.")</script>';
} else {

echo '<script>alert("Votre mot de passe actuel est incorrect.")</script>';
}

}
  ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de gestion d'inventaire || Changer le mot de passe</title>
<?php include_once('includes/cs.php');?>
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
<!--Header-part-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="change-password.php" class="tip-bottom">Changer le mot de passe</a></div>
  <h1>Changer le mot de passe</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>Changer le mot de passe</h5>
        </div>
        <div class="widget-content nopadding">
          <form method="post" class="form-horizontal" name="changepassword" onsubmit="return checkpass();">
            
            <div class="control-group">
              <label class="control-label">Mot de passe actuel :</label>
              <div class="controls">
                <input type="password" class="span11" name="currentpassword" id="currentpassword" value="" required='true' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Nouveau mot de passe :</label>
              <div class="controls">
                <input type="password" class="span11" name="newpassword" id="newpassword" value="" required='true' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Confirmer le mot de passe</label>
              <div class="controls">
                <input type="password"  class="span11" name="confirmpassword" id="confirmpassword" value=""/>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-success" name="submit">Mettre à jour</button>
            </div>
          </form>
        </div>
      </div>
    
    </div>
  </div>
 </div>
</div>
<?php include_once('includes/footer.php');?>
<?php include_once('includes/js.php');?>
</body>
</html>
<?php } ?>