<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
} else {
  // Handle user creation
  if(isset($_POST['createUser'])) {
    $adminid = $_SESSION['imsaid'];
    $username = $_POST['username'];
    
    // Check if the username is "saler" as required
    if($username == 'saler') {
      $password = md5($_POST['password']);
      $adminname = $_POST['adminname'];
      $mobileno = $_POST['mobileno'];
      $email = $_POST['email'];
      $regdate = date('Y-m-d H:i:s');
      
      // Check if username already exists
      $check = mysqli_query($con, "SELECT ID FROM tbladmin WHERE UserName='$username'");
      if(mysqli_num_rows($check) > 0) {
        echo '<script>alert("Ce nom d\'utilisateur existe déjà. Veuillez réessayer.")</script>';
      } else {
        $query = mysqli_query($con, "INSERT INTO tbladmin(AdminName, UserName, MobileNumber, Email, Password, AdminRegdate) 
                                     VALUES('$adminname', '$username', '$mobileno', '$email', '$password', '$regdate')");
        if($query) {
          echo '<script>alert("Nouvel utilisateur créé avec succès.")</script>';
        } else {
          echo '<script>alert("Échec de la création. Veuillez réessayer.")</script>';
        }
      }
    } else {
      echo '<script>alert("Vous ne pouvez créer que des utilisateurs avec le nom d\'utilisateur \"saler\".")</script>';
    }
  }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de gestion des stocks || Gestion des utilisateurs</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>

<!--Header-part-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>

<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="manage-users.php" class="tip-bottom">Gestion des utilisateurs</a></div>
  <h1>Gestion des utilisateurs</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>Créer un nouvel utilisateur</h5>
        </div>
        <div class="widget-content nopadding">
          <form method="post" class="form-horizontal">
            <div class="control-group">
              <label class="control-label">Nom d'administrateur :</label>
              <div class="controls">
                <input type="text" class="span11" name="adminname" id="adminname" required='true' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Nom d'utilisateur :</label>
              <div class="controls">
                <input type="text" class="span11" name="username" id="username" value="saler" readonly='true' />
                <span class="help-block">Le nom d'utilisateur doit être "saler"</span>
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Mot de passe :</label>
              <div class="controls">
                <input type="password" class="span11" name="password" id="password" required='true' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Numéro de contact :</label>
              <div class="controls">
                <input type="text" class="span11" name="mobileno" id="mobileno" required='true' maxlength='10' pattern='[0-9]+' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Adresse e-mail :</label>
              <div class="controls">
                <input type="email" class="span11" name="email" id="email" required='true' />
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-success" name="createUser">Créer utilisateur</button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Display existing users -->
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"><i class="icon-th"></i></span>
          <h5>Liste des utilisateurs</h5>
        </div>
        <div class="widget-content nopadding">
          <table class="table table-bordered data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Nom d'utilisateur</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Date d'inscription</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $ret=mysqli_query($con,"SELECT * FROM tbladmin");
              $cnt=1;
              while ($row=mysqli_fetch_array($ret)) {
              ?>
              <tr class="gradeX">
                <td><?php echo $cnt;?></td>
                <td><?php echo $row['AdminName'];?></td>
                <td><?php echo $row['UserName'];?></td>
                <td><?php echo $row['MobileNumber'];?></td>
                <td><?php echo $row['Email'];?></td>
                <td><?php echo $row['AdminRegdate'];?></td>
              </tr>
              <?php 
              $cnt=$cnt+1;
              }?>
            </tbody>
          </table>
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