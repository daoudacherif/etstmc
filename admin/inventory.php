<?php 
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Inventaire des Articles</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .stock-critical { color: #c62828; font-weight: bold; }
    .stock-low { color: #ef6c00; font-weight: bold; }
    .stock-good { color: #2e7d32; }
  </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <strong>Voir l'Inventaire des Articles</strong>
    </div>
    <h1>Inventaire des Articles</h1>
  </div>
  <div class="container-fluid">
    <hr>
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Inventaire des Articles</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom du Article</th>
                  <th>Catégorie</th>
                  <th>Marque</th>
                  <th>Modèle</th>
                  <th>Stock Initial</th>
                  <th>Vendus</th>
                  <th>Retournés</th>
                  <th>Stock Actuel</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Requête pour récupérer l'inventaire
                $sql = "
                  SELECT 
                    p.ID            AS pid,
                    p.ProductName,
                    COALESCE(c.CategoryName, 'N/A') AS CategoryName,
                    p.BrandName,
                    p.ModelNumber,
                    p.Stock         AS current_stock,
                    COALESCE(SUM(cart.ProductQty), 0) AS sold_qty,
                    COALESCE(
                      (SELECT SUM(Quantity) FROM tblreturns WHERE ProductID = p.ID),
                      0
                    ) AS returned_qty,
                    p.Status
                  FROM tblproducts p
                  LEFT JOIN tblcategory c 
                    ON c.ID = p.CatID
                  LEFT JOIN tblcart cart 
                    ON cart.ProductId = p.ID 
                   AND cart.IsCheckOut = 1
                  GROUP BY p.ID
                  ORDER BY p.Stock ASC, p.ID DESC
                ";
                $ret = mysqli_query($con, $sql) 
                  or die('Erreur SQL : ' . mysqli_error($con));

                if (mysqli_num_rows($ret) > 0) {
                  $cnt = 1;
                  while ($row = mysqli_fetch_assoc($ret)) {
                    // Le stock actuel est déjà dans la base de données
                    $current_stock = intval($row['current_stock']);
                    $sold = intval($row['sold_qty']);
                    $returned = intval($row['returned_qty']);
                    
                    // Calcul du stock initial = stock actuel + vendu - retourné
                    $initial_stock = $current_stock + $sold - $returned;
                    
                    // Déterminer la classe CSS pour le niveau de stock
                    $stockClass = '';
                    if ($current_stock == 0) {
                        $stockClass = 'stock-critical';
                    } elseif ($current_stock <= 5) {
                        $stockClass = 'stock-low';
                    } else {
                        $stockClass = 'stock-good';
                    }
                    ?>
                    <tr>
                      <td><?= $cnt ?></td>
                      <td><?= htmlspecialchars($row['ProductName']) ?></td>
                      <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                      <td><?= htmlspecialchars($row['BrandName']) ?></td>
                      <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                      <td><?= $initial_stock ?></td>
                      <td><?= $sold ?></td>
                      <td><?= $returned ?></td>
                      <td class="<?= $stockClass ?>">
                        <?= $current_stock === 0 ? 'Épuisé' : $current_stock ?>
                      </td>
                      <td><?= $row['Status'] == 1 ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                    <?php
                    $cnt++;
                  }
                } else {
                  echo '<tr><td colspan="10" class="text-center">Aucun Article trouvé</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div><!-- widget-content -->
        </div><!-- widget-box -->
      </div><!-- span12 -->
    </div><!-- row-fluid -->
  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>

<!-- scripts pour DataTable si nécessaire -->
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