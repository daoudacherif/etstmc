<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// Inclure dompdf (si nécessaire)
if (file_exists('dompdf/autoload.inc.php')) {
    require_once 'dompdf/autoload.inc.php';
    use Dompdf\Dompdf;
    $dompdf_available = true;
} else {
    $dompdf_available = false;
}

// --- 1) Récupérer dates de filtrage ---
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-30 days'));
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

// On formate pour un BETWEEN en SQL (inclusif sur la journée)
$startDateTime = $start . " 00:00:00";
$endDateTime = $end . " 23:59:59";

// --- 2) Traitement des exports si demandés ---
$export = isset($_GET['export']) ? $_GET['export'] : '';

// Récupération des données (utilisé pour tous les cas : affichage et exports)
// --- Calculer les totaux (Ventes, Dépôts, Retraits, Retours) ---

// Ventes
$sqlSales = "
  SELECT COALESCE(SUM(c.ProductQty * p.Price), 0) AS totalSales
  FROM tblcart c
  JOIN tblproducts p ON p.ID = c.ProductId
  WHERE c.IsCheckOut='1'
    AND c.CartDate BETWEEN ? AND ?
";
$stmtSales = $con->prepare($sqlSales);
$stmtSales->bind_param('ss', $startDateTime, $endDateTime);
$stmtSales->execute();
$resultSales = $stmtSales->get_result();
$rowSales = $resultSales->fetch_assoc();
$totalSales = $rowSales['totalSales'];
$stmtSales->close();

// Dépôts/Retraits
$sqlTransactions = "
  SELECT
    COALESCE(SUM(CASE WHEN TransType='IN' THEN Amount ELSE 0 END), 0) AS totalDeposits,
    COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS totalWithdrawals
  FROM tblcashtransactions
  WHERE TransDate BETWEEN ? AND ?
";
$stmtTransactions = $con->prepare($sqlTransactions);
$stmtTransactions->bind_param('ss', $startDateTime, $endDateTime);
$stmtTransactions->execute();
$resultTransactions = $stmtTransactions->get_result();
$rowTransactions = $resultTransactions->fetch_assoc();
$totalDeposits = $rowTransactions['totalDeposits'];
$totalWithdrawals = $rowTransactions['totalWithdrawals'];
$stmtTransactions->close();

// Retours
$sqlReturns = "
  SELECT COALESCE(SUM(r.Quantity * p.Price), 0) AS totalReturns
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE r.ReturnDate BETWEEN ? AND ?
";
$stmtReturns = $con->prepare($sqlReturns);
$stmtReturns->bind_param('ss', $start, $end);
$stmtReturns->execute();
$resultReturns = $stmtReturns->get_result();
$rowReturns = $resultReturns->fetch_assoc();
$totalReturns = $rowReturns['totalReturns'];
$stmtReturns->close();

// Solde final
$netBalance = ($totalSales + $totalDeposits) - ($totalWithdrawals + $totalReturns);

// --- 3) Récupérer la liste unifiée pour l'affichage / export ---
$sqlList = "
  SELECT 'Vente' AS Type, (c.ProductQty * p.Price) AS Amount,
       c.CartDate AS Date, p.ProductName AS Comment
  FROM tblcart c
  JOIN tblproducts p ON p.ID = c.ProductId
  WHERE c.IsCheckOut='1'
    AND c.CartDate BETWEEN ? AND ?
  
  UNION ALL
  
  SELECT 
    CASE 
      WHEN TransType='IN' THEN 'Dépôt' 
      WHEN TransType='OUT' THEN 'Retrait'
      ELSE TransType 
    END AS Type, 
    Amount, TransDate AS Date, Comments AS Comment
  FROM tblcashtransactions
  WHERE TransDate BETWEEN ? AND ?
  
  UNION ALL
  
  SELECT 'Retour' AS Type, (r.Quantity * p.Price) AS Amount,
       r.ReturnDate AS Date, r.Reason AS Comment
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE r.ReturnDate BETWEEN ? AND ?
  
  ORDER BY Date DESC
";
$stmtList = $con->prepare($sqlList);
$stmtList->bind_param('ssssss', $startDateTime, $endDateTime, $startDateTime, $endDateTime, $start, $end);
$stmtList->execute();
$resultList = $stmtList->get_result();

// --- 4) Statistiques par produit ---
$sqlProducts = "
  SELECT 
    p.ID,
    p.ProductName,
    COALESCE(c.CategoryName, 'N/A') AS CategoryName,
    p.BrandName,
    p.Stock AS initial_stock,
    COALESCE(SUM(cart.ProductQty), 0) AS sold_qty,
    COALESCE(
      (SELECT SUM(Quantity) FROM tblreturns WHERE ProductID = p.ID AND 
      ReturnDate BETWEEN ? AND ?),
      0
    ) AS returned_qty,
    p.Price,
    (COALESCE(SUM(cart.ProductQty), 0) * p.Price) AS total_sales
  FROM tblproducts p
  LEFT JOIN tblcategory c ON c.ID = p.CatID
  LEFT JOIN tblcart cart ON cart.ProductId = p.ID AND cart.IsCheckOut = 1
    AND cart.CartDate BETWEEN ? AND ?
  GROUP BY p.ID
  ORDER BY total_sales DESC
  LIMIT 10
";
$stmtProducts = $con->prepare($sqlProducts);
$stmtProducts->bind_param('ssss', $start, $end, $startDateTime, $endDateTime);
$stmtProducts->execute();
$resultProducts = $stmtProducts->get_result();

// ========== A) Export PDF via dompdf ==========
if ($export === 'pdf' && $dompdf_available) {
  // 1) Créer une instance Dompdf
  $dompdf = new Dompdf();

  // 2) Construire le HTML minimal à exporter
  ob_start();
  ?>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    h2 { color: #333; }
    .text-right { text-align: right; }
    .summary { margin-bottom: 20px; }
  </style>
  <h2>Rapport Financier du <?php echo date('d/m/Y', strtotime($start)); ?> au <?php echo date('d/m/Y', strtotime($end)); ?></h2>
  
  <div class="summary">
    <h3>Résumé</h3>
    <table>
      <tr><th>Ventes</th><td class="text-right"><?php echo number_format($totalSales, 2); ?></td></tr>
      <tr><th>Dépôts</th><td class="text-right"><?php echo number_format($totalDeposits, 2); ?></td></tr>
      <tr><th>Retraits</th><td class="text-right"><?php echo number_format($totalWithdrawals, 2); ?></td></tr>
      <tr><th>Retours</th><td class="text-right"><?php echo number_format($totalReturns, 2); ?></td></tr>
      <tr><th>Solde Final</th><td class="text-right"><strong><?php echo number_format($netBalance, 2); ?></strong></td></tr>
    </table>
  </div>

  <h3>Transactions détaillées</h3>
  <table>
    <tr>
      <th>#</th>
      <th>Type</th>
      <th>Montant</th>
      <th>Date</th>
      <th>Commentaire</th>
    </tr>
    <?php
    $cnt=1;
    $resultList->data_seek(0); // reset pointer
    while($row = $resultList->fetch_assoc()) {
    ?>
    <tr>
      <td><?php echo $cnt++; ?></td>
      <td><?php echo $row['Type']; ?></td>
      <td class="text-right"><?php echo number_format($row['Amount'], 2); ?></td>
      <td><?php echo date('d/m/Y H:i', strtotime($row['Date'])); ?></td>
      <td><?php echo htmlspecialchars($row['Comment']); ?></td>
    </tr>
    <?php
    }
    ?>
  </table>
  
  <h3>Top Produits Vendus</h3>
  <table>
    <tr>
      <th>#</th>
      <th>Produit</th>
      <th>Catégorie</th>
      <th>Quantité Vendue</th>
      <th>Retours</th>
      <th>Ventes Totales</th>
    </tr>
    <?php
    $cnt=1;
    $resultProducts->data_seek(0); // reset pointer
    while($row = $resultProducts->fetch_assoc()) {
      $sold = (int)$row['sold_qty'];
      $returned = (int)$row['returned_qty'];
      $net_sold = $sold - $returned;
    ?>
    <tr>
      <td><?php echo $cnt++; ?></td>
      <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
      <td><?php echo htmlspecialchars($row['CategoryName']); ?></td>
      <td><?php echo $sold; ?></td>
      <td><?php echo $returned; ?></td>
      <td class="text-right"><?php echo number_format($row['total_sales'], 2); ?></td>
    </tr>
    <?php
    }
    ?>
  </table>
  <?php
  $html = ob_get_clean();

  // 3) Passer le HTML à dompdf
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  // 4) Output PDF
  $dompdf->stream("rapport_financier_".date('Ymd').".pdf", array("Attachment" => true));
  exit;
}

// ========== B) Export Excel ==========
if ($export === 'excel') {
  // 1) Nom du fichier
  $filename = "rapport_financier_".date('Ymd').".xls";

  // 2) Headers HTTP pour l'export Excel
  header("Content-Type: application/vnd.ms-excel");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  header("Cache-Control: max-age=0");

  // 3) Construire un tableau HTML
  echo '
  <style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid black; padding: 5px; }
    th { background-color: #f2f2f2; }
    .text-right { text-align: right; }
  </style>';
  
  echo "<h2>Rapport Financier du ".date('d/m/Y', strtotime($start))." au ".date('d/m/Y', strtotime($end))."</h2>";
  
  echo "<h3>Résumé</h3>";
  echo "<table border='1'>";
  echo "<tr><th>Ventes</th><td class='text-right'>".number_format($totalSales, 2)."</td></tr>";
  echo "<tr><th>Dépôts</th><td class='text-right'>".number_format($totalDeposits, 2)."</td></tr>";
  echo "<tr><th>Retraits</th><td class='text-right'>".number_format($totalWithdrawals, 2)."</td></tr>";
  echo "<tr><th>Retours</th><td class='text-right'>".number_format($totalReturns, 2)."</td></tr>";
  echo "<tr><th>Solde Final</th><td class='text-right'><strong>".number_format($netBalance, 2)."</strong></td></tr>";
  echo "</table>";
  
  echo "<h3>Transactions détaillées</h3>";
  echo "<table border='1'>";
  echo "<tr><th>#</th><th>Type</th><th>Montant</th><th>Date</th><th>Commentaire</th></tr>";
  $cnt=1;
  $resultList->data_seek(0); // reset pointer
  while($row = $resultList->fetch_assoc()) {
    echo "<tr>";
    echo "<td>".$cnt++."</td>";
    echo "<td>".$row['Type']."</td>";
    echo "<td class='text-right'>".number_format($row['Amount'], 2)."</td>";
    echo "<td>".date('d/m/Y H:i', strtotime($row['Date']))."</td>";
    echo "<td>".htmlspecialchars($row['Comment'])."</td>";
    echo "</tr>";
  }
  echo "</table>";
  
  echo "<h3>Top Produits Vendus</h3>";
  echo "<table border='1'>";
  echo "<tr><th>#</th><th>Produit</th><th>Catégorie</th><th>Quantité Vendue</th><th>Retours</th><th>Ventes Totales</th></tr>";
  $cnt=1;
  $resultProducts->data_seek(0); // reset pointer
  while($row = $resultProducts->fetch_assoc()) {
    $sold = (int)$row['sold_qty'];
    $returned = (int)$row['returned_qty'];
    
    echo "<tr>";
    echo "<td>".$cnt++."</td>";
    echo "<td>".htmlspecialchars($row['ProductName'])."</td>";
    echo "<td>".htmlspecialchars($row['CategoryName'])."</td>";
    echo "<td>".$sold."</td>";
    echo "<td>".$returned."</td>";
    echo "<td class='text-right'>".number_format($row['total_sales'], 2)."</td>";
    echo "</tr>";
  }
  echo "</table>";
  exit;
}

// --- 5) Sinon, on affiche la page HTML classique ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rapport Financier</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
      <a href="report.php" class="current">Rapport Financier</a>
    </div>
    <h1>Rapport Financier</h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- Formulaire de filtre par dates -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-calendar"></i></span>
            <h5>Sélectionner la période du rapport</h5>
          </div>
          <div class="widget-content nopadding">
            <form method="get" class="form-horizontal" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
              <div class="control-group">
                <label class="control-label">De Date :</label>
                <div class="controls">
                  <input type="date" class="span11" name="start" id="start" value="<?php echo $start; ?>" required="true">
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">À Date :</label>
                <div class="controls">
                  <input type="date" class="span11" name="end" id="end" value="<?php echo $end; ?>" required="true">
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn-success"><i class="icon-search"></i> Afficher le Rapport</button>
                
                <!-- Boutons Export -->
                <div class="pull-right">
                  <?php if ($dompdf_available): ?>
                  <a href="report.php?start=<?php echo $start; ?>&end=<?php echo $end; ?>&export=pdf" class="btn btn-danger">
                    <i class="icon-file"></i> Exporter PDF
                  </a>
                  <?php endif; ?>
                  <a href="report.php?start=<?php echo $start; ?>&end=<?php echo $end; ?>&export=excel" class="btn btn-primary">
                    <i class="icon-table"></i> Exporter Excel
                  </a>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Tableau récapitulatif -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-signal"></i></span>
            <h5>Résumé du <?php echo date('d/m/Y', strtotime($start)); ?> au <?php echo date('d/m/Y', strtotime($end)); ?></h5>
            <span class="label label-info">Période: <?php echo round((strtotime($end) - strtotime($start)) / 86400); ?> jours</span>
          </div>
          <div class="widget-content">
            <div class="row-fluid">
              <div class="span12">
                <ul class="stat-boxes">
                  <li class="popover-visits">
                    <div class="left peity_bar_good"><span>2,4,9,7,12,10,12</span>+10%</div>
                    <div class="right">
                      <strong><?php echo number_format($totalSales, 2); ?></strong>
                      Ventes
                    </div>
                  </li>
                  <li class="popover-users">
                    <div class="left peity_line_neutral"><span>20,15,18,14,25,16,22</span>0%</div>
                    <div class="right">
                      <strong><?php echo number_format($totalDeposits, 2); ?></strong>
                      Dépôts
                    </div>
                  </li>
                  <li class="popover-orders">
                    <div class="left peity_bar_bad"><span>3,5,9,7,12,20,10</span>-50%</div>
                    <div class="right">
                      <strong><?php echo number_format($totalWithdrawals, 2); ?></strong>
                      Retraits
                    </div>
                  </li>
                  <li class="popover-tickets">
                    <div class="left peity_line_bad"><span>12,6,9,13,5,7,10</span>-25%</div>
                    <div class="right">
                      <strong><?php echo number_format($totalReturns, 2); ?></strong>
                      Retours
                    </div>
                  </li>
                  <li class="popover-tickets">
                    <div class="left peity_line_good"><span>5,9,16,14,25,33,28</span>+20%</div>
                    <div class="right">
                      <strong><?php echo number_format($netBalance, 2); ?></strong>
                      Solde Final
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top Produits Vendus -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-star"></i></span>
            <h5>Top Produits Vendus</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Produit</th>
                  <th>Catégorie</th>
                  <th>Quantité Vendue</th>
                  <th>Retours</th>
                  <th>Prix Unitaire</th>
                  <th>Ventes Totales</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $resultProducts->data_seek(0);
                $cnt = 1;
                while($row = $resultProducts->fetch_assoc()) {
                  $sold = (int)$row['sold_qty'];
                  $returned = (int)$row['returned_qty'];
                  $net_sold = $sold - $returned;
                ?>
                <tr>
                  <td><?php echo $cnt; ?></td>
                  <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                  <td><?php echo htmlspecialchars($row['CategoryName']); ?></td>
                  <td><?php echo $sold; ?></td>
                  <td><?php echo $returned; ?></td>
                  <td class="text-right"><?php echo number_format($row['Price'], 2); ?></td>
                  <td class="text-right"><?php echo number_format($row['total_sales'], 2); ?></td>
                </tr>
                <?php
                  $cnt++;
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Transactions détaillées -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Transactions détaillées</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Type</th>
                  <th>Montant</th>
                  <th>Date</th>
                  <th>Commentaire</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $resultList->data_seek(0);
                $cnt = 1;
                while($row = $resultList->fetch_assoc()) {
                  // Définir la classe CSS pour le type de transaction
                  $typeClass = '';
                  switch($row['Type']) {
                    case 'Vente': $typeClass = 'label-success'; break;
                    case 'Dépôt': $typeClass = 'label-info'; break;
                    case 'Retrait': $typeClass = 'label-warning'; break;
                    case 'Retour': $typeClass = 'label-important'; break;
                    default: $typeClass = '';
                  }
                ?>
                <tr>
                  <td><?php echo $cnt; ?></td>
                  <td><span class="label <?php echo $typeClass; ?>"><?php echo $row['Type']; ?></span></td>
                  <td class="text-right"><?php echo number_format($row['Amount'], 2); ?></td>
                  <td><?php echo date('d/m/Y H:i', strtotime($row['Date'])); ?></td>
                  <td><?php echo htmlspecialchars($row['Comment']); ?></td>
                </tr>
                <?php
                  $cnt++;
                }
                // Fermer les requêtes préparées
                $stmtList->close();
                $stmtProducts->close();
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

<!-- Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.tables.js"></script>
<script>
  // Validation JS: assure start <= end
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form && form.addEventListener('submit', function(e) {
      const startDate = new Date(document.getElementById('start').value);
      const endDate = new Date(document.getElementById('end').value);
      if (startDate > endDate) {
        alert('La date de début ne peut pas être après la date de fin.');
        e.preventDefault();
      }
    });
  });
</script>

</body>
</html>