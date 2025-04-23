<?php 
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (empty($_SESSION['imsaid'])) {
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
                  <th>Marque</th>
                  <th>Modèle</th>
                  <th>Stock Initial</th>
                  <th>Vendus</th>
                  <th>Stock Restant</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sql = "SELECT 
                        p.ID as pid,
                        p.ProductName,
                        p.BrandName,
                        p.ModelNumber,
                        p.Stock,
                        p.Status,
                        c.CategoryName,
                        COALESCE(SUM(cart.ProductQty), 0) as sold_qty
                      FROM tblproducts p
                      LEFT JOIN tblcategory c ON c.ID = p.CatID
                      LEFT JOIN tblcart cart ON cart.ProductId = p.ID 
                        AND cart.OrderStatus = 'Completed' /* Only count completed orders */
                      GROUP BY p.ID
                      ORDER BY p.ID DESC";
                
                $ret = mysqli_query($con, $sql);
                if(mysqli_num_rows($ret) > 0) {
                  $cnt = 1;
                  while($row = mysqli_fetch_assoc($ret)) {
                    $remaining_stock = $row['Stock'] - $row['sold_qty'];
                    ?>
                    <tr>
                      <td><?= $cnt ?></td>
                      <td><?= htmlspecialchars($row['ProductName']) ?></td>
                      <td><?= htmlspecialchars($row['CategoryName'] ?? 'N/A') ?></td>
                      <td><?= htmlspecialchars($row['BrandName']) ?></td>
                      <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                      <td><?= $row['Stock'] ?></td>
                      <td><?= $row['sold_qty'] ?></td>
                      <td class="<?= ($remaining_stock <= 0) ? 'text-error' : '' ?>">
                        <?= ($remaining_stock <= 0) ? 'Épuisé' : $remaining_stock ?>
                      </td>
                      <td><?= ($row['Status'] == 1) ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                    <?php
                    $cnt++;
                  }
                } else {
                  echo '<tr><td colspan="9" class="text-center">Aucun produit trouvé</td></tr>';
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
</body>
</html>