<?php 
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['imsaid'] ?? '') == 0) {
  header('location:logout.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Système de Gestion d'Inventaire || Voir l'Inventaire des Produits</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <strong>Voir l'Inventaire des Produits</strong>
    </div>
    <h1>Voir l'Inventaire des Produits</h1>
  </div>
  <div class="container-fluid">
    <hr>
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Inventaire des Produits</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom du Produit</th>
                  <th>Catégorie</th>
                  <th>Modèle</th>
                  <th>Stock Restant</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sql = "
                  SELECT
                    p.ID as pid,
                    p.ProductName,
                    p.ModelNumber,
                    p.Stock,
                    p.Status,
                    c.CategoryName,
                    SUM(cart.ProductQty) as selledqty
                  FROM tblproducts p
                  LEFT JOIN tblcategory c ON c.ID = p.CatID
                  LEFT JOIN tblcart cart ON cart.ProductId = p.ID
                  GROUP BY p.ID
                  ORDER BY p.ID DESC
                ";
                $ret = mysqli_query($con, $sql);
                $num = mysqli_num_rows($ret);
                if ($num > 0) {
                  $cnt = 1;
                  while ($row = mysqli_fetch_assoc($ret)) {
                    $qtySold = $row['selledqty'] ?? 0;
                    $stockRemain = $row['Stock'] - $qtySold;
                    $catName = $row['CategoryName'] ?: "N/A";
                    ?>
                    <tr class="gradeX">
                      <td><?= $cnt ?></td>
                      <td><?= htmlspecialchars($row['ProductName']) ?></td>
                      <td><?= htmlspecialchars($catName) ?></td>
                      <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                      <td>
                        <?= ($stockRemain <= 0) ? '<span class="text-danger">Vide</span>' : $stockRemain ?>
                      </td>
                      <td>
                        <?= ($row['Status'] == "1") ? 'Actif' : 'Inactif' ?>
                      </td>
                    </tr>
                    <?php
                    $cnt++;
                  }
                } else {
                  ?>
                  <tr>
                    <td colspan="6" class="text-center">Aucun enregistrement trouvé.</td>
                  </tr>
                  <?php
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once('includes/footer.php'); ?>

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
