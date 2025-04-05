<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
  } else{
    if(isset($_POST['submit']))
  {
    $brandname=$_POST['brandname'];
    $status=$_POST['status'];
     
    $query=mysqli_query($con, "insert into tblbrand(BrandName,Status) value('$brandname','$status')");
    if ($query) {
   
    echo '<script>alert("La marque a été créée.")</script>';
  }
  else
    {
     echo '<script>alert("Quelque chose s\'est mal passé. Veuillez réessayer")</script>';
    }

  
}
  ?>
<!DOCTYPE html>
<html lang="fr">
<style>
    .control-label {
      font-size: 20px;
      font-weight: bolder;
      color: black;  
    }
  </style>
<head>
<title>Système de Gestion des Stocks || Ajouter une Marque</title>
<?php include_once('includes/cs.php');?>
</head>
<body>

<!--Header-part-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="add-brand.php" class="tip-bottom">Ajouter une Marque</a></div>
  <h1>Ajouter une Marque</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>Ajouter une Marque</h5>
        </div>
        <div class="widget-content nopadding">
          <form method="post" class="form-horizontal">
           
            <div class="control-group">
              <label class="control-label">Nom de la Marque :</label>
              <div class="controls">
                <input type="text" class="span11" name="brandname" id="brandname" value="" required='true' />
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Statut :</label>
              <div class="controls">
                <input type="checkbox"  name="status" id="status" value="1" required="true" />
              </div>
            </div>
            
           
            <div class="form-actions">
              <button type="submit" class="btn btn-success" name="submit">Ajouter</button>
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