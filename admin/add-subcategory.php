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
<title>Système de Gestion des Inventaires || Ajouter une Sous-Catégorie</title>
<?php include_once('includes/cs.php');
include('includes/dbconnection.php');
?>
<?php include_once('includes/responsive.php'); ?>

<!--Partie En-Tête-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Retour à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="add-subcategory.php" class="tip-bottom">Ajouter une Sous-Catégorie</a></div>
  <h1>Ajouter une Sous-Catégorie</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>Ajouter une Sous-Catégorie</h5>
        </div>
        <div class="widget-content nopadding">
          <form method="post" class="form-horizontal">
           
            <div class="control-group">
              <label class="control-label">Nom de la Catégorie :</label>
              <div class="controls">
                <select type="text" class="span11" name="catid" id="catid" value="" required='true' />
                  <option value="">Choisir une Catégorie</option>
                  <?php
$ret=mysqli_query($con,"select * from tblcategory");
$cnt=1;
while ($row=mysqli_fetch_array($ret)) {

?>
                  <option value="<?php  echo $row['ID'];?>"><?php  echo $row['CategoryName'];?></option>
                  <?php 
$cnt=$cnt+1;
}?> 
                </select>
              </div>
            </div>
            <div class="control-group">
              <label class="control-label">Nom de la Sous-Catégorie :</label>
              <div class="controls">
                <input type="text" class="span11" name="subcategory" id="subcategory" value="" required='true' />
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
