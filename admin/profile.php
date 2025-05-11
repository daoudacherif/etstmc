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
    $aname=$_POST['adminname'];
  $mobno=$_POST['contactnumber'];
  
     $query=mysqli_query($con, "update tbladmin set AdminName ='$aname', MobileNumber='$mobno' where ID='$adminid'");
    if ($query) {
    
    echo '<script>alert("Le profil de l\'administrateur a été mis à jour.")</script>';
  }
  else
    {
      echo '<script>alert("Quelque chose a mal tourné. Veuillez réessayer.")</script>';
    }
  }
  ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de gestion des stocks || Profil</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>

<!--Header-part-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="profile.php" class="tip-bottom">Profil</a></div>
  <h1>Profil</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>Profil</h5>
        </div>
        <div class="widget-content nopadding">
          <form method="post" class="form-horizontal">
            <?php
$adminid=$_SESSION['imsaid'];
$ret=mysqli_query($con,"select * from tbladmin where ID='$adminid'");
$cnt=1;
while ($row=mysqli_fetch_array($ret)) {

?>
            <div class="control-group">
              <label class="control-label">Nom de l'administrateur :</label>
              <div class="controls">
                <input type="text" class="span11" name="adminname" id="adminname" value="<?php  echo $row['AdminName'];?>" required='true' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Nom d'utilisateur :</label>
              <div class="controls">
                <input type="text" class="span11" name="username" id="username" value="<?php  echo $row['UserName'];?>" readonly="true" />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Numéro de contact :</label>
              <div class="controls">
                <input type="text"  class="span11"id="contactnumber" name="contactnumber" value="<?php  echo $row['MobileNumber'];?>" required='true' maxlength='10' patter='[0-9]+'  />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Adresse e-mail :</label>
              <div class="controls">
                <input type="email" class="span11" id="email" name="email" class="form-control1 input-lg" value="<?php  echo $row['Email'];?>" readonly='true' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Date d'inscription :</label>
              <div class="controls">
                <input type="text" class="span11" value="<?php  echo $row['AdminRegdate'];?>" readonly="true" />
                </div>
            </div>
            
            <?php } ?>
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