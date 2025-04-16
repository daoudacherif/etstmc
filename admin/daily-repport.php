<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Inclure dompdf (ajuste le chemin selon ton organisation)
// Par exemple si tu as un dossier "dompdf" dans le même répertoire :
require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid'] == 0)) {
  header('location:logout.php');
  exit;
}

// --- 1) Récupérer dates de filtrage ---
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
$end   = isset($_GET['end'])   ? $_GET['end']   : date('Y-m-d');

// On formate pour un BETWEEN en SQL (inclusif sur la journée)
$startDateTime = $start . " 00:00:00";
$endDateTime   = $end   . " 23:59:59";

// --- 2) Calculer les totaux (Ventes, Dépôts, Retraits, Retours) ---

// Ventes
$sqlSales = "
  SELECT COALESCE(SUM(c.ProductQty * p.Price), 0) AS totalSales
  FROM tblcart c
  JOIN tblproducts p ON p.ID = c.ProductId
  WHERE c.IsCheckOut='1'
    AND c.CartDate BETWEEN '$startDateTime' AND '$endDateTime'
";
$resSales = mysqli_query($con, $sqlSales);
$rowSales = mysqli_fetch_assoc($resSales);
$totalSales = $rowSales['totalSales'];

// Dépôts/Retraits
$sqlTransactions = "
  SELECT
    COALESCE(SUM(CASE WHEN TransType='IN' THEN Amount ELSE 0 END), 0) AS totalDeposits,
    COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS totalWithdrawals
  FROM tblcashtransactions
  WHERE TransDate BETWEEN '$startDateTime' AND '$endDateTime'
";
$resTransactions = mysqli_query($con, $sqlTransactions);
$rowTransactions = mysqli_fetch_assoc($resTransactions);
$totalDeposits    = $rowTransactions['totalDeposits'];
$totalWithdrawals = $rowTransactions['totalWithdrawals'];

// Retours
$sqlReturns = "
  SELECT COALESCE(SUM(r.Quantity * p.Price), 0) AS totalReturns
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE r.ReturnDate BETWEEN '$start' AND '$end'
    /* ReturnDate est un champ DATE, donc BETWEEN 'YYYY-MM-DD' AND 'YYYY-MM-DD' suffit */
";
$resReturns = mysqli_query($con, $sqlReturns);
$rowReturns = mysqli_fetch_assoc($resReturns);
$totalReturns = $rowReturns['totalReturns'];

// Solde final
$netBalance = ($totalSales + $totalDeposits) - ($totalWithdrawals + $totalReturns);

// --- 3) Récupérer la liste unifiée pour l'affichage / export ---
$sqlList = "
  SELECT 'Vente' AS Type, (c.ProductQty * p.Price) AS Amount,
       c.CartDate AS Date, p.ProductName AS Comment
  FROM tblcart c
  JOIN tblproducts p ON p.ID = c.ProductId
  WHERE c.IsCheckOut='1'
    AND c.CartDate BETWEEN '$startDateTime' AND '$endDateTime'
  
  UNION ALL
  
  SELECT TransType AS Type, Amount, TransDate AS Date, Comments AS Comment
  FROM tblcashtransactions
  WHERE TransDate BETWEEN '$startDateTime' AND '$endDateTime'
  
  UNION ALL
  
  SELECT 'Retour' AS Type, (r.Quantity * p.Price) AS Amount,
       r.ReturnDate AS Date, r.Reason AS Comment
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE r.ReturnDate BETWEEN '$start' AND '$end'
  
  ORDER BY Date DESC
";
$resList = mysqli_query($con, $sqlList);

// --- 4) Export PDF ou Excel ? ---
$export = isset($_GET['export']) ? $_GET['export'] : '';

// ========== A) Export PDF via dompdf ==========
if ($export === 'pdf') {
  // 1) Créer une instance Dompdf
  $dompdf = new Dompdf();

  // 2) Construire le HTML minimal à exporter
  ob_start();
  ?>
  <h2>Rapport du <?php echo $start; ?> au <?php echo $end; ?></h2>
  <p><strong>Ventes:</strong> <?php echo number_format($totalSales,2); ?><br>
     <strong>Dépôts:</strong> <?php echo number_format($totalDeposits,2); ?><br>
     <strong>Retraits:</strong> <?php echo number_format($totalWithdrawals,2); ?><br>
     <strong>Retours:</strong> <?php echo number_format($totalReturns,2); ?><br>
     <strong>Solde Final:</strong> <?php echo number_format($netBalance,2); ?></p>

  <table border="1" cellspacing="0" cellpadding="5">
    <tr>
    <th>#</th>
    <th>Type</th>
    <th>Montant</th>
    <th>Date</th>
    <th>Commentaire</th>
    </tr>
    <?php
    $cnt=1;
    mysqli_data_seek($resList, 0); // reset pointer
    while($row = mysqli_fetch_assoc($resList)) {
    ?>
    <tr>
      <td><?php echo $cnt++; ?></td>
      <td><?php echo $row['Type']; ?></td>
      <td><?php echo number_format($row['Amount'],2); ?></td>
      <td><?php echo $row['Date']; ?></td>
      <td><?php echo $row['Comment']; ?></td>
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
  $dompdf->stream("rapport_".date('Ymd').".pdf", array("Attachment" => true));
  exit;
}

// ========== B) Export Excel ==========
if ($export === 'excel') {
  // 1) Nom du fichier
  $filename = "rapport_".date('Ymd').".xls";

  // 2) Headers HTTP pour l'export Excel
  header("Content-Type: application/vnd.ms-excel");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  header("Cache-Control: max-age=0");

  // 3) Construire un tableau HTML
  echo "<h2>Rapport du $start au $end</h2>";
  echo "<p><strong>Ventes:</strong> ".number_format($totalSales,2)."<br>";
  echo "<strong>Dépôts:</strong> ".number_format($totalDeposits,2)."<br>";
  echo "<strong>Retraits:</strong> ".number_format($totalWithdrawals,2)."<br>";
  echo "<strong>Retours:</strong> ".number_format($totalReturns,2)."<br>";
  echo "<strong>Solde Final:</strong> ".number_format($netBalance,2)."</p>";

  echo "<table border='1'>";
  echo "<tr><th>#</th><th>Type</th><th>Montant</th><th>Date</th><th>Commentaire</th></tr>";
  $cnt=1;
  mysqli_data_seek($resList, 0); // reset pointer
  while($row = mysqli_fetch_assoc($resList)) {
    echo "<tr>";
    echo "<td>".$cnt++."</td>";
    echo "<td>".$row['Type']."</td>";
    echo "<td>".number_format($row['Amount'],2)."</td>";
    echo "<td>".$row['Date']."</td>";
    echo "<td>".$row['Comment']."</td>";
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
  <title>Rapport global</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
  <h1>Rapport Global</h1>
  </div>

  <div class="container-fluid">
  <hr>

  <!-- Formulaire de filtre par dates -->
  <form method="get" class="form-inline" action="report.php">
    <label>Du :</label>
    <input type="date" name="start" value="<?php echo $start; ?>">
    <label>Au :</label>
    <input type="date" name="end" value="<?php echo $end; ?>">
    <button type="submit" class="btn btn-primary">Filtrer</button>

    <!-- Boutons Export -->
    <a href="report.php?start=<?php echo $start; ?>&end=<?php echo $end; ?>&export=pdf" class="btn btn-info" target="_blank">Exporter PDF</a>
    <a href="report.php?start=<?php echo $start; ?>&end=<?php echo $end; ?>&export=excel" class="btn btn-success">Exporter Excel</a>
  </form>
  <hr>

  <!-- Tableau récap -->
  <div class="row-fluid">
    <div class="span12">
    <h3>Résumé du <?php echo $start; ?> au <?php echo $end; ?></h3>
    <table class="table table-bordered">
      <tr><th>Ventes</th><td><?php echo number_format($totalSales,2); ?></td></tr>
      <tr><th>Dépôts</th><td><?php echo number_format($totalDeposits,2); ?></td></tr>
      <tr><th>Retraits</th><td><?php echo number_format($totalWithdrawals,2); ?></td></tr>
      <tr><th>Retours</th><td><?php echo number_format($totalReturns,2); ?></td></tr>
      <tr>
      <th>Solde Final</th>
      <td><strong><?php echo number_format($netBalance,2); ?></strong></td>
      </tr>
    </table>
    </div>
  </div>

  <!-- Transactions détaillées -->
  <div class="row-fluid">
    <div class="span12">
    <div class="widget-box">
      <div class="widget-title">
      <h5>Transactions détaillées</h5>
      </div>
      <div class="widget-content nopadding">
      <table class="table table-bordered data-table">
        <thead>
        <tr>
          <th>#</th>
          <th>Type</th>
          <th>Montant</th>
          <th>Date</th>
          <th>Commentaire</th>
        </tr>
        </thead>
        <tbody>
        <?php
        // On réinitialise le curseur
        mysqli_data_seek($resList, 0);
        $cnt = 1;
        while($row = mysqli_fetch_assoc($resList)) {
          ?>
          <tr>
          <td><?php echo $cnt++; ?></td>
          <td><?php echo $row['Type']; ?></td>
          <td><?php echo number_format($row['Amount'],2); ?></td>
          <td><?php echo $row['Date']; ?></td>
          <td><?php echo $row['Comment']; ?></td>
          </tr>
          <?php
        }
        ?>
        </tbody>
      </table>
      </div>
    </div><!-- widget-box -->
    </div>
  </div><!-- row-fluid -->

  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.tables.js"></script>
</body>
</html>
