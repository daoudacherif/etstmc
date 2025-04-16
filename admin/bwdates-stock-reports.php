<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Check if admin is logged in
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
    exit;
}

// Retrieve the dates from the POST submission
$fdate = isset($_POST['fromdate']) ? mysqli_real_escape_string($con, $_POST['fromdate']) : "";
$tdate = isset($_POST['todate']) ? mysqli_real_escape_string($con, $_POST['todate']) : "";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Système de Gestion des Inventaires || Rapport entre deux dates</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <a href="stock-report.php" class="current">Rapport entre deux dates</a>
    </div>
    <h1>Détails des Produits</h1>
  </div>
  <div class="container-fluid">
    <hr>
    <?php if($fdate && $tdate){ ?>
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5 align="center" style="color:blue">
              Rapport d'inventaire du <?php echo $fdate; ?> au <?php echo $tdate; ?>
            </h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom du Produit</th>
                  <th>Nom de la Catégorie</th>
                  <th>Nom de la Sous-Catégorie</th>
                  <th>Nom de la Marque</th>
                  <th>Numéro de Modèle</th>
                  <th>Stock</th>
                  <th>Stock Restant</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Query: select products whose CreationDate is between fdate and tdate.
                // Calculate remaining stock as: Stock - COALESCE(SUM(tblcart.ProductQty), 0)
                $ret = mysqli_query($con, "
                  SELECT 
                    tblproducts.ID AS pid,
                    tblproducts.ProductName,
                    tblcategory.CategoryName,
                    tblsubcategory.SubCategoryname AS subcat,
                    tblproducts.BrandName,
                    tblproducts.ModelNumber,
                    tblproducts.Stock,
                    tblproducts.Status,
                    tblproducts.CreationDate,
                    COALESCE(SUM(tblcart.ProductQty), 0) AS selledqty
                  FROM tblproducts
                  INNER JOIN tblcategory ON tblcategory.ID = tblproducts.CatID
                  INNER JOIN tblsubcategory ON tblsubcategory.ID = tblproducts.SubcatID
                  LEFT JOIN tblcart ON tblproducts.ID = tblcart.ProductId
                  WHERE DATE(tblproducts.CreationDate) BETWEEN '$fdate' AND '$tdate'
                  GROUP BY tblproducts.ID
                  ORDER BY tblproducts.ID DESC
                ");
                $num = mysqli_num_rows($ret);
                if ($num > 0) {
                    $cnt = 1;
                    while ($row = mysqli_fetch_array($ret)) {
                        $qtySold = $row['selledqty'];
                        // If no sold quantity is returned, default to 0
                        if (!$qtySold) {
                            $qtySold = 0;
                        }
                        $stockRemain = $row['Stock'] - $qtySold;
                        ?>
                        <tr class="gradeX">
                          <td><?php echo $cnt; ?></td>
                          <td><?php echo $row['ProductName']; ?></td>
                          <td><?php echo $row['CategoryName']; ?></td>
                          <td><?php echo $row['subcat']; ?></td>
                          <td><?php echo $row['BrandName']; ?></td>
                          <td><?php echo $row['ModelNumber']; ?></td>
                          <td><?php echo $row['Stock']; ?></td>
                          <td><?php echo $stockRemain; ?></td>
                          <td><?php echo ($row['Status'] == "1") ? "Actif" : "Inactif"; ?></td>
                        </tr>
                        <?php
                        $cnt++;
                    }
                } else {
                    ?>
                    <tr>
                      <td colspan="9" style="text-align:center;">Aucun enregistrement trouvé.</td>
                    </tr>
                    <?php
                }
                ?>
              </tbody>
            </table>
          </div><!-- widget-content nopadding -->
        </div><!-- widget-box -->
      </div><!-- span12 -->
    </div><!-- row-fluid -->
    <?php } else { ?>
      <p style="text-align:center; color:red;">Veuillez saisir les dates de début et de fin.</p>
    <?php } ?>
  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>
<!-- Fin Pied de Page -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.tables.js"></script>
</body>
</html>
