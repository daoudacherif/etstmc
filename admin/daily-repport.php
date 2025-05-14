<?php
// Daily Cash Reconciliation Report
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Verify admin login
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// Get date - default to today if not specified
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$startDateTime = $date . " 00:00:00";
$endDateTime = $date . " 23:59:59";

// Get previous day's ending balance to use as today's starting balance
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$prevStartDateTime = $prevDate . " 00:00:00";
$prevEndDateTime = $prevDate . " 23:59:59";

// Calculate previous day's ending balance
$sqlPrevBalance = "
  SELECT 
    (
      (SELECT COALESCE(SUM(c.ProductQty * c.Price), 0) FROM tblcart c WHERE c.IsCheckOut='1' AND c.CartDate < ?) +
      (SELECT COALESCE(SUM(Amount), 0) FROM tblcashtransactions WHERE TransType='IN' AND TransDate < ?)
    ) -
    (
      (SELECT COALESCE(SUM(r.Quantity * p.Price), 0) FROM tblreturns r JOIN tblproducts p ON p.ID = r.ProductID WHERE r.ReturnDate < ?) +
      (SELECT COALESCE(SUM(Amount), 0) FROM tblcashtransactions WHERE TransType='OUT' AND TransDate < ?)
    ) AS balance
";
$stmtPrevBalance = $con->prepare($sqlPrevBalance);
$stmtPrevBalance->bind_param('ssss', $startDateTime, $startDateTime, $date, $startDateTime);
$stmtPrevBalance->execute();
$resultPrevBalance = $stmtPrevBalance->get_result();
$rowPrevBalance = $resultPrevBalance->fetch_assoc();
$startingBalance = $rowPrevBalance['balance'];
$stmtPrevBalance->close();

// --- Calculate today's activity ---

// Cash Sales
$sqlCashSales = "
  SELECT COALESCE(SUM(c.ProductQty * c.Price), 0) AS totalSales
  FROM tblcart c
  WHERE c.IsCheckOut='1' AND c.CartType='regular'
    AND c.CartDate BETWEEN ? AND ?
";
$stmtCashSales = $con->prepare($sqlCashSales);
$stmtCashSales->bind_param('ss', $startDateTime, $endDateTime);
$stmtCashSales->execute();
$resultCashSales = $stmtCashSales->get_result();
$rowCashSales = $resultCashSales->fetch_assoc();
$totalCashSales = $rowCashSales['totalSales'];
$stmtCashSales->close();

// Credit Sales (these don't affect the cash balance)
$sqlCreditSales = "
  SELECT COALESCE(SUM(c.ProductQty * c.Price), 0) AS totalSales
  FROM tblcreditcart c
  WHERE c.IsCheckOut='1'
    AND c.CartDate BETWEEN ? AND ?
";
$stmtCreditSales = $con->prepare($sqlCreditSales);
$stmtCreditSales->bind_param('ss', $startDateTime, $endDateTime);
$stmtCreditSales->execute();
$resultCreditSales = $stmtCreditSales->get_result();
$rowCreditSales = $resultCreditSales->fetch_assoc();
$totalCreditSales = $rowCreditSales['totalSales'];
$stmtCreditSales->close();

// Cash transactions (deposits and withdrawals)
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

// Returns (cash refunded to customers)
$sqlReturns = "
  SELECT COALESCE(SUM(r.ReturnPrice), 0) AS totalReturns
  FROM tblreturns r
  WHERE r.ReturnDate = ?
";
$stmtReturns = $con->prepare($sqlReturns);
$stmtReturns->bind_param('s', $date);
$stmtReturns->execute();
$resultReturns = $stmtReturns->get_result();
$rowReturns = $resultReturns->fetch_assoc();
$totalReturns = $rowReturns['totalReturns'];
$stmtReturns->close();

// Calculate expected ending balance
$expectedEndingBalance = $startingBalance + $totalCashSales + $totalDeposits - $totalWithdrawals - $totalReturns;

// Get detailed transactions for the day
$sqlTransactionsList = "
  -- Cash Sales
  SELECT 'Vente en Espèces' AS Type, (c.ProductQty * c.Price) AS Amount,
       c.CartDate AS Date, CONCAT(p.ProductName, ' (', c.ProductQty, ' unités)') AS Comment
  FROM tblcart c
  JOIN tblproducts p ON p.ID = c.ProductId
  WHERE c.IsCheckOut='1' AND c.CartType='regular'
    AND c.CartDate BETWEEN ? AND ?
  
  UNION ALL
  
  -- Cash Transactions
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
  
  -- Returns
  SELECT 'Retour' AS Type, r.ReturnPrice AS Amount,
       CONCAT(r.ReturnDate, ' 12:00:00') AS Date, 
       CONCAT(p.ProductName, ' (', r.Quantity, ' unités)') AS Comment
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE r.ReturnDate = ?
  
  ORDER BY Date ASC
";
$stmtTransactionsList = $con->prepare($sqlTransactionsList);
$stmtTransactionsList->bind_param('sssss', $startDateTime, $endDateTime, $startDateTime, $endDateTime, $date);
$stmtTransactionsList->execute();
$resultTransactionsList = $stmtTransactionsList->get_result();

// Process form submission for physical cash count
$discrepancy = 0;
$physicalCount = 0;
$reconciliationNotes = '';

if (isset($_POST['submit_reconciliation'])) {
    $physicalCount = $_POST['physical_count'];
    $reconciliationNotes = $_POST['reconciliation_notes'];
    $discrepancy = $physicalCount - $expectedEndingBalance;
    
    // You could save this reconciliation data to a table if needed
    // ...
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Réconciliation Journalière de Caisse</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .balance-card {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
    }
    .starting-balance {
      background-color: #e8f4f8;
      border: 1px solid #b8e0f0;
    }
    .ending-balance {
      background-color: #f4f8e8;
      border: 1px solid #e0f0b8;
    }
    .reconciliation {
      background-color: #f8e8e8;
      border: 1px solid #f0b8b8;
    }
    .discrepancy-positive {
      color: green;
    }
    .discrepancy-negative {
      color: red;
    }
    .balance-value {
      font-size: 24px;
      font-weight: bold;
    }
    .transaction-type {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 3px;
    }
    .transaction-sale {
      background-color: #dff0d8;
    }
    .transaction-deposit {
      background-color: #d9edf7;
    }
    .transaction-withdrawal {
      background-color: #fcf8e3;
    }
    .transaction-return {
      background-color: #f2dede;
    }
  </style>
</head>
<body>
<!-- Header and sidebar -->
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
      <a href="#" class="current">Réconciliation de Caisse</a>
    </div>
    <h1>Réconciliation Journalière de Caisse</h1>
  </div>

  <div class="container-fluid">
    <hr>
    
    <!-- Date selector -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-calendar"></i></span>
            <h5>Sélectionner la date</h5>
          </div>
          <div class="widget-content">
            <form method="get" class="form-horizontal" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
              <div class="control-group">
                <label class="control-label">Date de réconciliation :</label>
                <div class="controls">
                  <input type="date" name="date" value="<?php echo $date; ?>" required="true">
                  <button type="submit" class="btn btn-primary">Afficher</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Summary cards -->
    <div class="row-fluid">
      <div class="span4">
        <div class="balance-card starting-balance">
          <h3>Solde d'ouverture</h3>
          <p>Début de journée <?php echo date('d/m/Y', strtotime($date)); ?></p>
          <div class="balance-value"><?php echo number_format($startingBalance, 2); ?> GNF</div>
        </div>
      </div>
      <div class="span4">
        <div class="balance-card ending-balance">
          <h3>Solde de fermeture attendu</h3>
          <p>Fin de journée <?php echo date('d/m/Y', strtotime($date)); ?></p>
          <div class="balance-value"><?php echo number_format($expectedEndingBalance, 2); ?> GNF</div>
        </div>
      </div>
      <div class="span4">
        <div class="balance-card reconciliation">
          <h3>Écart</h3>
          <p>Différence entre le système et le physique</p>
          <div class="balance-value <?php echo ($discrepancy > 0) ? 'discrepancy-positive' : (($discrepancy < 0) ? 'discrepancy-negative' : ''); ?>">
            <?php echo ($physicalCount > 0) ? number_format($discrepancy, 2) . ' GNF' : '---'; ?>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Daily activity summary -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-signal"></i></span>
            <h5>Résumé de l'activité du jour</h5>
          </div>
          <div class="widget-content">
            <div class="row-fluid">
              <div class="span6">
                <table class="table table-bordered">
                  <tr>
                    <th>Ventes en espèces</th>
                    <td class="text-right">+ <?php echo number_format($totalCashSales, 2); ?> GNF</td>
                  </tr>
                  <tr>
                    <th>Dépôts</th>
                    <td class="text-right">+ <?php echo number_format($totalDeposits, 2); ?> GNF</td>
                  </tr>
                  <tr>
                    <th>Retraits</th>
                    <td class="text-right">- <?php echo number_format($totalWithdrawals, 2); ?> GNF</td>
                  </tr>
                  <tr>
                    <th>Retours / Remboursements</th>
                    <td class="text-right">- <?php echo number_format($totalReturns, 2); ?> GNF</td>
                  </tr>
                </table>
              </div>
              <div class="span6">
                <table class="table table-bordered">
                  <tr>
                    <th>Pour information - Ventes à crédit</th>
                    <td class="text-right"><?php echo number_format($totalCreditSales, 2); ?> GNF</td>
                  </tr>
                  <tr>
                    <th>Nombre de transactions</th>
                    <td class="text-right"><?php echo $resultTransactionsList->num_rows; ?></td>
                  </tr>
                  <tr>
                    <th>Variation nette de caisse</th>
                    <td class="text-right"><?php echo number_format($expectedEndingBalance - $startingBalance, 2); ?> GNF</td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Physical count form -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-check"></i></span>
            <h5>Réconciliation physique</h5>
          </div>
          <div class="widget-content">
            <form method="post" class="form-horizontal">
              <div class="control-group">
                <label class="control-label">Comptage physique :</label>
                <div class="controls">
                  <input type="number" name="physical_count" value="<?php echo $physicalCount; ?>" step="0.01" class="span6">
                  <span class="help-block">Entrez le montant total comptabilisé physiquement en caisse</span>
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Notes :</label>
                <div class="controls">
                  <textarea name="reconciliation_notes" class="span6" rows="3"><?php echo $reconciliationNotes; ?></textarea>
                  <span class="help-block">Notez toute explication sur les écarts</span>
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" name="submit_reconciliation" class="btn btn-success">Valider la réconciliation</button>
                <button type="button" class="btn" onclick="window.print();">Imprimer</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Detailed transactions -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th-list"></i></span>
            <h5>Transactions de la journée</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th width="5%">#</th>
                  <th width="15%">Type</th>
                  <th width="15%">Montant</th>
                  <th width="20%">Heure</th>
                  <th width="45%">Détails</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $cnt = 1;
                $runningBalance = $startingBalance;
                
                // Add starting balance as first row
                ?>
                <tr>
                  <td><?php echo $cnt++; ?></td>
                  <td><span class="transaction-type">Solde initial</span></td>
                  <td class="text-right"><?php echo number_format($startingBalance, 2); ?> GNF</td>
                  <td><?php echo date('H:i', strtotime($startDateTime)); ?></td>
                  <td>Solde d'ouverture</td>
                </tr>
                <?php
                
                while($row = $resultTransactionsList->fetch_assoc()) {
                  // Update running balance
                  if ($row['Type'] == 'Vente en Espèces' || $row['Type'] == 'Dépôt') {
                    $runningBalance += $row['Amount'];
                    $effect = '+';
                  } else {
                    $runningBalance -= $row['Amount'];
                    $effect = '-';
                  }
                  
                  // Determine transaction type class
                  $typeClass = '';
                  switch($row['Type']) {
                    case 'Vente en Espèces': $typeClass = 'transaction-sale'; break;
                    case 'Dépôt': $typeClass = 'transaction-deposit'; break;
                    case 'Retrait': $typeClass = 'transaction-withdrawal'; break;
                    case 'Retour': $typeClass = 'transaction-return'; break;
                    default: $typeClass = '';
                  }
                ?>
                <tr>
                  <td><?php echo $cnt++; ?></td>
                  <td><span class="transaction-type <?php echo $typeClass; ?>"><?php echo $row['Type']; ?></span></td>
                  <td class="text-right"><?php echo $effect.' '.number_format($row['Amount'], 2); ?> GNF</td>
                  <td><?php echo date('H:i', strtotime($row['Date'])); ?></td>
                  <td><?php echo htmlspecialchars($row['Comment']); ?></td>
                </tr>
                <?php
                }
                $stmtTransactionsList->close();
                
                // Add expected ending balance as last row
                ?>
                <tr>
                  <td><?php echo $cnt++; ?></td>
                  <td><span class="transaction-type">Solde final</span></td>
                  <td class="text-right"><strong><?php echo number_format($expectedEndingBalance, 2); ?> GNF</strong></td>
                  <td><?php echo date('H:i', strtotime($endDateTime)); ?></td>
                  <td>Solde de fermeture attendu</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    
  </div>
</div>

<!-- Footer -->
<?php include_once('includes/footer.php'); ?>

<!-- Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/matrix.js"></script>
</body>
</html>