<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Check admin login
if (strlen($_SESSION['imsaid'] == 0)) {
  header('location:logout.php');
  exit;
}

// ---------------------------------------------------------------------
// 1) CALCULER LE SOLDE DU JOUR UNIQUEMENT (Période de 24h)
// ---------------------------------------------------------------------

// 1.1 Ventes régulières du jour (from tblcart)
$sqlRegularSales = "
  SELECT COALESCE(SUM(c.ProductQty * p.Price), 0) AS totalSales
  FROM tblcart c
  JOIN tblproducts p ON p.ID = c.ProductId
  WHERE c.IsCheckOut = '1'
    AND DATE(c.CartDate) = CURDATE()
";
$resRegularSales = mysqli_query($con, $sqlRegularSales);
$rowRegularSales = mysqli_fetch_assoc($resRegularSales);
$todayRegularSales = floatval($rowRegularSales['totalSales']);

// 1.2 Ventes à crédit du jour - pour affichage uniquement (from tblcreditcart)
$sqlCreditSales = "
  SELECT COALESCE(SUM(c.ProductQty * p.Price), 0) AS totalSales
  FROM tblcreditcart c
  JOIN tblproducts p ON p.ID = c.ProductId
  WHERE c.IsCheckOut = '1'
    AND DATE(c.CartDate) = CURDATE()
";
$resCreditSales = mysqli_query($con, $sqlCreditSales);
$rowCreditSales = mysqli_fetch_assoc($resCreditSales);
$todayCreditSales = floatval($rowCreditSales['totalSales']);

// 1.3 Paiements clients du jour (from tblcustomer)
$sqlCustomerPayments = "
  SELECT COALESCE(SUM(Paid), 0) AS totalPaid
  FROM tblcustomer
  WHERE DATE(BillingDate) = CURDATE()
";
$resCustomerPayments = mysqli_query($con, $sqlCustomerPayments);
$rowCustomerPayments = mysqli_fetch_assoc($resCustomerPayments);
$todayCustomerPayments = floatval($rowCustomerPayments['totalPaid']);

// 1.4 Dépôts et retraits manuels du jour (from tblcashtransactions)
$sqlManualTransactions = "
  SELECT
    COALESCE(SUM(CASE WHEN TransType='IN' AND Comments NOT IN ('Daily Sale', 'Customer Payments') 
                      THEN Amount ELSE 0 END), 0) AS manualDeposits,
    COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS withdrawals
  FROM tblcashtransactions
  WHERE DATE(TransDate) = CURDATE()
";
$resManualTransactions = mysqli_query($con, $sqlManualTransactions);
$rowManualTransactions = mysqli_fetch_assoc($resManualTransactions);
$todayManualDeposits = floatval($rowManualTransactions['manualDeposits']);
$todayWithdrawals = floatval($rowManualTransactions['withdrawals']);

// 1.5 Retours du jour (from tblreturns)
$sqlReturns = "
  SELECT COALESCE(SUM(r.Quantity * p.Price), 0) AS totalReturns
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE DATE(r.ReturnDate) = CURDATE()
";
$resReturns = mysqli_query($con, $sqlReturns);
$rowReturns = mysqli_fetch_assoc($resReturns);
$todayReturns = floatval($rowReturns['totalReturns']);

// 1.6 Calcul du solde du jour uniquement (sans historique)
$todayTotalIn = $todayRegularSales + $todayCustomerPayments + $todayManualDeposits;
$todayTotalOut = $todayWithdrawals + $todayReturns;
$todayBalance = $todayTotalIn - $todayTotalOut;

// 1.7 Vérifier si les ventes et paiements ont déjà été enregistrés dans les transactions
$salesRecorded = false;
$sqlCheckSales = "
  SELECT ID FROM tblcashtransactions 
  WHERE DATE(TransDate) = CURDATE() 
  AND Comments = 'Daily Sale'
  LIMIT 1
";
$resCheckSales = mysqli_query($con, $sqlCheckSales);
if (mysqli_num_rows($resCheckSales) > 0) {
  $salesRecorded = true;
}

$paymentsRecorded = false;
$sqlCheckPayments = "
  SELECT ID FROM tblcashtransactions 
  WHERE DATE(TransDate) = CURDATE() 
  AND Comments = 'Customer Payments'
  LIMIT 1
";
$resCheckPayments = mysqli_query($con, $sqlCheckPayments);
if (mysqli_num_rows($resCheckPayments) > 0) {
  $paymentsRecorded = true;
}

// ---------------------------------------------------------------------
// 2) ENREGISTRER LES VENTES ET PAIEMENTS DANS LES TRANSACTIONS SI PAS DÉJÀ FAIT
// ---------------------------------------------------------------------

// 2.1 Enregistrer les ventes régulières
if ($todayRegularSales > 0 && !$salesRecorded) {
  // Calculer le nouveau solde après cette transaction
  $sqlCurrentBal = "
    SELECT COALESCE(SUM(CASE WHEN TransType='IN' THEN Amount ELSE 0 END), 0) -
           COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS currentBalance
    FROM tblcashtransactions
    WHERE DATE(TransDate) = CURDATE()
  ";
  $resCurrentBal = mysqli_query($con, $sqlCurrentBal);
  $rowCurrentBal = mysqli_fetch_assoc($resCurrentBal);
  $currentBal = floatval($rowCurrentBal['currentBalance']);
  
  $newBal = $currentBal + $todayRegularSales;
  
  // Insérer la transaction de ventes
  $sqlInsertSales = "
    INSERT INTO tblcashtransactions(TransDate, TransType, Amount, BalanceAfter, Comments)
    VALUES (NOW(), 'IN', '$todayRegularSales', '$newBal', 'Daily Sale')
  ";
  mysqli_query($con, $sqlInsertSales);
  
  // Actualiser la page pour refléter les changements
  echo "<script>window.location.href='transact.php'</script>";
  exit;
}

// 2.2 Enregistrer les paiements clients
if ($todayCustomerPayments > 0 && !$paymentsRecorded) {
  // Calculer le nouveau solde après cette transaction
  $sqlCurrentBal = "
    SELECT COALESCE(SUM(CASE WHEN TransType='IN' THEN Amount ELSE 0 END), 0) -
           COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS currentBalance
    FROM tblcashtransactions
    WHERE DATE(TransDate) = CURDATE()
  ";
  $resCurrentBal = mysqli_query($con, $sqlCurrentBal);
  $rowCurrentBal = mysqli_fetch_assoc($resCurrentBal);
  $currentBal = floatval($rowCurrentBal['currentBalance']);
  
  $newBal = $currentBal + $todayCustomerPayments;
  
  // Insérer la transaction de paiements
  $sqlInsertPayments = "
    INSERT INTO tblcashtransactions(TransDate, TransType, Amount, BalanceAfter, Comments)
    VALUES (NOW(), 'IN', '$todayCustomerPayments', '$newBal', 'Customer Payments')
  ";
  mysqli_query($con, $sqlInsertPayments);
  
  // Actualiser la page pour refléter les changements
  echo "<script>window.location.href='transact.php'</script>";
  exit;
}

// ---------------------------------------------------------------------
// 3) GÉRER UNE NOUVELLE TRANSACTION MANUELLE
// ---------------------------------------------------------------------

$transactionError = '';

if (isset($_POST['submit'])) {
  $transtype = $_POST['transtype']; // 'IN' ou 'OUT'
  $amount = floatval($_POST['amount']);
  $comments = mysqli_real_escape_string($con, $_POST['comments']);

  if ($amount <= 0) {
    $transactionError = 'Montant invalide. Doit être > 0';
  } 
  // Bloquer le retrait si le solde du jour est insuffisant
  else if ($transtype == 'OUT' && $amount > $todayBalance) {
    $transactionError = 'Impossible d\'effectuer un retrait : montant supérieur au solde du jour';
  }
  else {
    // Calculer le solde actuel dans la table des transactions
    $sqlCurrentBal = "
      SELECT COALESCE(SUM(CASE WHEN TransType='IN' THEN Amount ELSE 0 END), 0) -
             COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS currentBalance
      FROM tblcashtransactions
      WHERE DATE(TransDate) = CURDATE()
    ";
    $resCurrentBal = mysqli_query($con, $sqlCurrentBal);
    $rowCurrentBal = mysqli_fetch_assoc($resCurrentBal);
    $currentBal = floatval($rowCurrentBal['currentBalance']);

    // Calculer le nouveau solde
    $newBal = ($transtype == 'IN') ? $currentBal + $amount : $currentBal - $amount;
    
    // Insérer la transaction
    $sqlInsert = "
      INSERT INTO tblcashtransactions(TransDate, TransType, Amount, BalanceAfter, Comments)
      VALUES (NOW(), '$transtype', '$amount', '$newBal', '$comments')
    ";
    
    if (mysqli_query($con, $sqlInsert)) {
      echo "<script>alert('Transaction enregistrée!');</script>";
      echo "<script>window.location.href='transact.php'</script>";
      exit;
    } else {
      $transactionError = 'Erreur lors de l\'insertion de la transaction';
    }
  }
  
  if ($transactionError) {
    echo "<script>alert('$transactionError');</script>";
  }
}

// ---------------------------------------------------------------------
// 4) RÉCUPÉRER LES TRANSACTIONS RÉCENTES POUR L'AFFICHAGE
// ---------------------------------------------------------------------

// Calculer le solde actuel en base pour l'affichage
$sqlCurrentDbBalance = "
  SELECT COALESCE(SUM(CASE WHEN TransType='IN' THEN Amount ELSE 0 END), 0) -
         COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS currentBalance
  FROM tblcashtransactions
  WHERE DATE(TransDate) = CURDATE()
";
$resCurrentDbBalance = mysqli_query($con, $sqlCurrentDbBalance);
$rowCurrentDbBalance = mysqli_fetch_assoc($resCurrentDbBalance);
$currentDbBalance = floatval($rowCurrentDbBalance['currentBalance']);

// Désactiver les retraits si le solde est insuffisant
$outDisabled = ($todayBalance <= 0);

// Montant maximum retirable
$maxWithdrawal = ($todayBalance > 0) ? $todayBalance : 0;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Gestion d'inventaire | Transactions en espèces</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .balance-box {
      border: 1px solid #ccc;
      padding: 15px;
      margin-bottom: 20px;
      background-color: #f9f9f9;
    }
    .text-success { color: #468847; }
    .text-error { color: #b94a48; }
    .text-warning { color: #c09853; }
    .text-info { color: #3a87ad; }
    .highlight-daily {
      background-color: #fffacd; 
      font-weight: bold;
    }
    
    .transaction-type {
      display: inline-block;
      padding: 2px 6px;
      font-size: 12px;
      font-weight: bold;
      border-radius: 3px;
      text-align: center;
    }
    .type-in { 
      background-color: #dff0d8; 
      color: #468847;
    }
    .type-out { 
      background-color: #f2dede; 
      color: #b94a48;
    }
    .comments-daily-sale,
    .comments-customer-payments {
      color: #3a87ad;
      font-style: italic;
    }
  </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
      <a href="transact.php" class="current">Transactions en espèces</a>
    </div>
    <h1>Transactions en espèces (PÉRIODE: AUJOURD'HUI UNIQUEMENT)</h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- Résumé du solde -->
    <div class="row-fluid">
      <div class="span12">
        <div class="balance-box">
          <div class="row-fluid">
            <div class="span7">
              <h3>Solde du jour: <span class="<?php echo ($todayBalance > 0) ? 'text-success' : 'text-error'; ?> highlight-daily">
                <?php echo number_format($todayBalance, 2); ?>
              </span></h3>
              
              <h4>Détail du jour:</h4>
              <table class="table table-bordered table-striped" style="width: auto;">
                <tr>
                  <td>Ventes régulières:</td>
                  <td style="text-align: right;">
                    +<?php echo number_format($todayRegularSales, 2); ?>
                    <?php if ($salesRecorded): ?>
                      <span class="text-info">(enregistré)</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <td>Paiements clients:</td>
                  <td style="text-align: right;">
                    +<?php echo number_format($todayCustomerPayments, 2); ?>
                    <?php if ($paymentsRecorded): ?>
                      <span class="text-info">(enregistré)</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <td>Dépôts manuels:</td>
                  <td style="text-align: right;">+<?php echo number_format($todayManualDeposits, 2); ?></td>
                </tr>
                <tr>
                  <td>Retraits:</td>
                  <td style="text-align: right;">-<?php echo number_format($todayWithdrawals, 2); ?></td>
                </tr>
                <tr>
                  <td>Retours:</td>
                  <td style="text-align: right;">-<?php echo number_format($todayReturns, 2); ?></td>
                </tr>
                <tr>
                  <th>Solde du jour:</th>
                  <th style="text-align: right;" class="<?php echo ($todayBalance >= 0) ? 'text-success' : 'text-error'; ?> highlight-daily">
                    <?php echo number_format($todayBalance, 2); ?>
                  </th>
                </tr>
              </table>
            </div>
            
            <div class="span5">
              <div style="padding: 20px; background-color: #eee; border-radius: 5px;">
                <h4>Informations:</h4>
                <p><strong>Ventes à terme (non incluses dans le solde):</strong><br>
                   <?php echo number_format($todayCreditSales, 2); ?></p>
                
                <?php if ($todayBalance <= 0): ?>
                  <p class="text-error"><strong>SOLDE INSUFFISANT:</strong><br>
                     Les retraits sont désactivés.</p>
                <?php else: ?>
                  <p><strong>Montant max. retirable aujourd'hui:</strong><br>
                     <?php echo number_format($maxWithdrawal, 2); ?></p>
                <?php endif; ?>
                
                <p class="text-warning"><strong>IMPORTANT:</strong> Le solde est calculé uniquement sur la période de 24h (aujourd'hui). Les transactions des jours précédents ne sont pas prises en compte.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Formulaire de nouvelle transaction -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-plus"></i></span>
            <h5>Ajouter une nouvelle transaction</h5>
          </div>
          <div class="widget-content nopadding">
            <form method="post" class="form-horizontal">
              <div class="control-group">
                <label class="control-label">Type de transaction :</label>
                <div class="controls">
                  <select name="transtype" id="transtype" required>
                    <option value="IN">Dépôt (IN)</option>
                    <?php if (!$outDisabled): ?>
                    <option value="OUT">Retrait (OUT)</option>
                    <?php endif; ?>
                  </select>
                  <?php if ($outDisabled): ?>
                    <span class="help-inline text-error">Retraits désactivés (solde du jour insuffisant)</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="control-group">
                <label class="control-label">Montant :</label>
                <div class="controls">
                  <input type="number" name="amount" id="amount" step="0.01" min="0.01" required />
                  <span id="amount-warning" class="help-inline text-error" style="display: none;">
                    Le montant doit être inférieur au solde du jour (<?php echo number_format($todayBalance, 2); ?>)
                  </span>
                </div>
              </div>

              <div class="control-group">
                <label class="control-label">Commentaires :</label>
                <div class="controls">
                  <input type="text" name="comments" placeholder="Note optionnelle" />
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" name="submit" class="btn btn-success">
                  Enregistrer la transaction
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <hr>

    <!-- Liste des transactions récentes -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Transactions d'aujourd'hui</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th width="5%">#</th>
                  <th width="15%">Date/Heure</th>
                  <th width="10%">Type</th>
                  <th width="15%">Montant</th>
                  <th width="15%">Solde après</th>
                  <th width="40%">Commentaires</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Afficher uniquement les transactions d'aujourd'hui
                $sqlList = "SELECT * FROM tblcashtransactions 
                           WHERE DATE(TransDate) = CURDATE() 
                           ORDER BY ID DESC";
                $resList = mysqli_query($con, $sqlList);
                $cnt = 1;
                
                if (mysqli_num_rows($resList) > 0) {
                  while ($row = mysqli_fetch_assoc($resList)) {
                    $id = $row['ID'];
                    $transDate = $row['TransDate'];
                    $transType = $row['TransType'];
                    $amount = floatval($row['Amount']);
                    $balance = floatval($row['BalanceAfter']);
                    $comments = $row['Comments'];
                    
                    // Déterminer la classe CSS pour le type
                    $typeClass = '';
                    if ($transType == 'IN') {
                      $typeClass = 'type-in';
                      $transTypeLabel = 'IN';
                    } elseif ($transType == 'OUT') {
                      $typeClass = 'type-out';
                      $transTypeLabel = 'OUT';
                    }
                    
                    // Déterminer la classe CSS pour les commentaires
                    $commentsClass = '';
                    if ($comments == 'Daily Sale') {
                      $commentsClass = 'comments-daily-sale';
                    } elseif ($comments == 'Customer Payments') {
                      $commentsClass = 'comments-customer-payments';
                    }
                  ?>
                  <tr>
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($transDate)); ?></td>
                    <td>
                      <span class="transaction-type <?php echo $typeClass; ?>">
                        <?php echo $transTypeLabel; ?>
                      </span>
                    </td>
                    <td style="text-align: right;">
                      <?php echo number_format($amount, 2); ?>
                    </td>
                    <td style="text-align: right;">
                      <?php echo number_format($balance, 2); ?>
                    </td>
                    <td class="<?php echo $commentsClass; ?>">
                      <?php echo $comments; ?>
                    </td>
                  </tr>
                  <?php
                    $cnt++;
                  }
                } else {
                  echo '<tr><td colspan="6" style="text-align: center;">Aucune transaction aujourd\'hui</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Historique des transactions -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-time"></i></span>
            <h5>Historique des transactions (30 derniers jours)</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th width="5%">#</th>
                  <th width="10%">Date</th>
                  <th width="10%">Type</th>
                  <th width="15%">Montant</th>
                  <th width="15%">Solde après</th>
                  <th width="45%">Commentaires</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Afficher l'historique des transactions (sauf aujourd'hui)
                $sqlHistory = "SELECT * FROM tblcashtransactions 
                              WHERE DATE(TransDate) < CURDATE() 
                              ORDER BY TransDate DESC, ID DESC 
                              LIMIT 100";
                $resHistory = mysqli_query($con, $sqlHistory);
                $cnt = 1;
                
                if (mysqli_num_rows($resHistory) > 0) {
                  while ($row = mysqli_fetch_assoc($resHistory)) {
                    $id = $row['ID'];
                    $transDate = $row['TransDate'];
                    $transType = $row['TransType'];
                    $amount = floatval($row['Amount']);
                    $balance = floatval($row['BalanceAfter']);
                    $comments = $row['Comments'];
                    
                    // Déterminer la classe CSS pour le type
                    $typeClass = '';
                    if ($transType == 'IN') {
                      $typeClass = 'type-in';
                      $transTypeLabel = 'IN';
                    } elseif ($transType == 'OUT') {
                      $typeClass = 'type-out';
                      $transTypeLabel = 'OUT';
                    }
                  ?>
                  <tr>
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($transDate)); ?></td>
                    <td>
                      <span class="transaction-type <?php echo $typeClass; ?>">
                        <?php echo $transTypeLabel; ?>
                      </span>
                    </td>
                    <td style="text-align: right;">
                      <?php echo number_format($amount, 2); ?>
                    </td>
                    <td style="text-align: right;">
                      <?php echo number_format($balance, 2); ?>
                    </td>
                    <td>
                      <?php echo $comments; ?>
                    </td>
                  </tr>
                  <?php
                    $cnt++;
                  }
                } else {
                  echo '<tr><td colspan="6" style="text-align: center;">Pas d\'historique disponible</td></tr>';
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
$(document).ready(function() {
  // Validation dynamique du montant pour les retraits
  $('#transtype, #amount').on('change input', function() {
    var transType = $('#transtype').val();
    var amount = parseFloat($('#amount').val()) || 0;
    var todayBalance = <?php echo $todayBalance; ?>;
    
    if (transType === 'OUT') {
      if (amount > todayBalance) {
        $('#amount-warning').show();
        $('#amount').addClass('error');
      } else {
        $('#amount-warning').hide();
        $('#amount').removeClass('error');
      }
    } else {
      $('#amount-warning').hide();
      $('#amount').removeClass('error');
    }
  });
  
  // Validation du formulaire avant soumission
  $('form').on('submit', function(e) {
    var transType = $('#transtype').val();
    var amount = parseFloat($('#amount').val()) || 0;
    var todayBalance = <?php echo $todayBalance; ?>;
    
    if (transType === 'OUT' && amount > todayBalance) {
      e.preventDefault();
      alert('Impossible d\'effectuer un retrait supérieur au solde du jour (' + todayBalance.toFixed(2) + ')');
      return false;
    }
    
    return true;
  });
});
</script>
</body>
</html>