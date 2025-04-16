<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
  } else{
    
  ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de Gestion d'Inventaire || Ajouter une Marque</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>
<!--Header-part-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="stock-report.php" class="tip-bottom">Rapports Entre Dates</a></div>
  <h1>Rapports Entre Dates</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-align-justify"></i> </span>
          <h5>Rapport de Stock Entre Dates</h5>
        </div>
        <div class="widget-content nopadding">
          <form method="post" class="form-horizontal" action="bwdates-stock-reports.php">
           
            <div class="control-group">
              <label class="control-label">De Date :</label>
              <div class="controls">
                <input type="date" class="span11" name="fromdate" id="fromdate" value="" required='true' />
              </div>
            </div>
         <div class="control-group">
              <label class="control-label">À Date :</label>
              <div class="controls">
                <input type="date" class="span11" name="todate" id="todate" value="" required='true' />
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-success" name="submit">Soumettre</button>
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