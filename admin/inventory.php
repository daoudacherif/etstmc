<?php
session_start();
error_reporting(E_ALL);
include_once 'includes/dbconnection.php';

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// Récupération et nettoyage des dates
$fdate = filter_input(INPUT_POST, 'fromdate', FILTER_SANITIZE_STRING);
$tdate = filter_input(INPUT_POST, 'todate', FILTER_SANITIZE_STRING);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Système de Gestion d'Inventaire || Rapport entre Dates</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
      <a href="stock-report.php" class="current">Rapport entre deux dates</a>
    </div>
    <h1>Rapport d'Inventaire</h1>
  </div>
  <div class="container-fluid">
    <hr>

    <?php if ($fdate && $tdate): ?>
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Inventaire du <?= htmlspecialchars($fdate) ?> au <?= htmlspecialchars($tdate) ?></h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom de l'Article</th>
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
                $sql = "
                  SELECT 
                    p.ID AS pid,
                    p.ProductName,
                    COALESCE(c.CategoryName, 'N/A') AS CategoryName,
                    p.BrandName,
                    p.ModelNumber,
                    p.Stock AS initial_stock,
                    COALESCE(SUM(cart.ProductQty), 0) AS sold_qty,
                    p.Status
                  FROM tblproducts p
                  LEFT JOIN tblcategory c ON c.ID = p.CatID
                  LEFT JOIN tblcart cart ON cart.ProductId = p.ID AND cart.IsCheckOut = 1
                  WHERE DATE(p.CreationDate) BETWEEN ? AND ?
                  GROUP BY p.ID
                  ORDER BY p.ID DESC
                ";
                $stmt = mysqli_prepare($con, $sql);
                mysqli_stmt_bind_param($stmt, 'ss', $fdate, $tdate);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) > 0) {
                    $cnt = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        $initial = (int) $row['initial_stock'];
                        $sold = (int) $row['sold_qty'];
                        $remaining = max(0, $initial - $sold);
                        ?>
                        <tr>
                          <td><?= $cnt ?></td>
                          <td><?= htmlspecialchars($row['ProductName']) ?></td>
                          <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                          <td><?= htmlspecialchars($row['BrandName']) ?></td>
                          <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                          <td><?= $initial ?></td>
                          <td><?= $sold ?></td>
                          <td class="<?= $remaining === 0 ? 'text-danger' : '' ?>"><?= $remaining === 0 ? 'Épuisé' : $remaining ?></td>
                          <td><?= $row['Status'] == 1 ? 'Actif' : 'Inactif' ?></td>
                        </tr>
                        <?php
                        $cnt++;
                    }
                } else {
                    echo '<tr><td colspan="9" class="text-center">Aucun Article trouvé pour cette période</td></tr>';
                }
                mysqli_stmt_close($stmt);
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
      <p class="text-center text-error">Veuillez sélectionner une date de début et de fin.</p>
    <?php endif; ?>

  </div>
</div>

<?php include_once('includes/footer.php'); ?>
<!-- scripts DataTables -->
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
