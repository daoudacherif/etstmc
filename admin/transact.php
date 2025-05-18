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
// 0) RESET/INITIALIZE DAILY BALANCE IF NEEDED
// ---------------------------------------------------------------------

// Check if we've initialized today
$todayInitialized = false;
$sqlCheckInit = "SELECT ID FROM tblcashtransactions 
                WHERE DATE(TransDate) = CURDATE() 
                AND Comments = 'DAILY_INIT'
                LIMIT 1";
$resCheckInit = mysqli_query($con, $sqlCheckInit);
if (mysqli_num_rows($resCheckInit) > 0) {
  $todayInitialized = true;
}

// If not initialized, calculate previous day's balance and create init entry
if (!$todayInitialized) {
  // Get previous day's final balance
  $sqlPrevBalance = "
    SELECT 
      (SELECT COALESCE(SUM(c.ProductQty * p.Price), 0)
       FROM tblcart c 
       JOIN tblproducts p ON p.ID = c.ProductId
       WHERE c.IsCheckOut = '1' 
       AND DATE(c.CartDate) < CURDATE()) +
      
      (SELECT COALESCE(SUM(Paid), 0)
       FROM tblcustomer
       WHERE DATE(BillingDate) < CURDATE()) +
      
      (SELECT COALESCE(SUM(CASE WHEN TransType='IN' AND Comments NOT IN ('Daily Sale', 'Customer Payments', 'DAILY_INIT') 
                           THEN Amount ELSE 0 END), 0)
       FROM tblcashtransactions
       WHERE DATE(TransDate) < CURDATE()) -
      
      (SELECT COALESCE(SUM(CASE WHEN TransType='OUT' 
                           THEN Amount ELSE 0 END), 0)
       FROM tblcashtransactions
       WHERE DATE(TransDate) < CURDATE()) -
      
      (SELECT COALESCE(SUM(r.Quantity * p.Price), 0)
       FROM tblreturns r
       JOIN tblproducts p ON p.ID = r.ProductID
       WHERE DATE(r.ReturnDate) < CURDATE())
       
    AS yesterdayBalance
  ";
  
  $resPrevBalance = mysqli_query($con, $sqlPrevBalance);
  $rowPrevBalance = mysqli_fetch_assoc($resPrevBalance);
  $yesterdayBalance = floatval($rowPrevBalance['yesterdayBalance']);
  
  // Insert initialization row
  $sqlInit = "
    INSERT INTO tblcashtransactions(TransDate, TransType, Amount, BalanceAfter, Comments)
    VALUES (NOW(), 'INIT', '$yesterdayBalance', '$yesterdayBalance', 'DAILY_INIT')
  ";
  mysqli_query($con, $sqlInit);
}

// ---------------------------------------------------------------------
// 1) CALCULATE TODAY'S STATS
// ---------------------------------------------------------------------

// 1.1 Regular sales (from tblcart)
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

// 1.2 Credit sales - for display only (from tblcreditcart)
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

// 1.3 Customer payments (from tblcustomer)
$sqlCustomerPayments = "
  SELECT COALESCE(SUM(Paid), 0) AS totalPaid
  FROM tblcustomer
  WHERE DATE(BillingDate) = CURDATE()
";
$resCustomerPayments = mysqli_query($con, $sqlCustomerPayments);
$rowCustomerPayments = mysqli_fetch_assoc($resCustomerPayments);
$todayCustomerPayments = floatval($rowCustomerPayments['totalPaid']);

// 1.4 Manual deposits and withdrawals (from tblcashtransactions)
$sqlManualTransactions = "
  SELECT
    COALESCE(SUM(CASE WHEN TransType='IN' AND Comments NOT IN ('Daily Sale', 'Customer Payments', 'DAILY_INIT') 
                      THEN Amount ELSE 0 END), 0) AS manualDeposits,
    COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS withdrawals
  FROM tblcashtransactions
  WHERE DATE(TransDate) = CURDATE()
";
$resManualTransactions = mysqli_query($con, $sqlManualTransactions);
$rowManualTransactions = mysqli_fetch_assoc($resManualTransactions);
$todayManualDeposits = floatval($rowManualTransactions['manualDeposits']);
$todayWithdrawals = floatval($rowManualTransactions['withdrawals']);

// 1.5 Returns (from tblreturns)
$sqlReturns = "
  SELECT COALESCE(SUM(r.Quantity * p.Price), 0) AS totalReturns
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE DATE(r.ReturnDate) = CURDATE()
";
$resReturns = mysqli_query($con, $sqlReturns);
$rowReturns = mysqli_fetch_assoc($resReturns);
$todayReturns = floatval($rowReturns['totalReturns']);

// 1.6 Check if sales and payments have been recorded in transactions
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
// 2) RECORD SALES AND PAYMENTS IF NOT ALREADY DONE
// ---------------------------------------------------------------------

// 2.1 Record regular sales
if ($todayRegularSales > 0 && !$salesRecorded) {
  // Get current balance
  $sqlCurrentBal = "SELECT BalanceAfter FROM tblcashtransactions 
                    WHERE DATE(TransDate) = CURDATE() 
                    ORDER BY ID DESC LIMIT 1";
  $resCurrentBal = mysqli_query($con, $sqlCurrentBal);
  
  if (mysqli_num_rows($resCurrentBal) > 0) {
    $rowCurrentBal = mysqli_fetch_assoc($resCurrentBal);
    $currentBal = floatval($rowCurrentBal['BalanceAfter']);
  } else {
    $currentBal = 0; // Should not happen since we initialized
  }
  
  $newBal = $currentBal + $todayRegularSales;
  
  // Insert sales transaction
  $sqlInsertSales = "
    INSERT INTO tblcashtransactions(TransDate, TransType, Amount, BalanceAfter, Comments)
    VALUES (NOW(), 'IN', '$todayRegularSales', '$newBal', 'Daily Sale')
  ";
  mysqli_query($con, $sqlInsertSales);
}

// 2.2 Record customer payments
if ($todayCustomerPayments > 0 && !$paymentsRecorded) {
  // Get current balance
  $sqlCurrentBal = "SELECT BalanceAfter FROM tblcashtransactions 
                    WHERE DATE(TransDate) = CURDATE() 
                    ORDER BY ID DESC LIMIT 1";
  $resCurrentBal = mysqli_query($con, $sqlCurrentBal);
  
  if (mysqli_num_rows($resCurrentBal) > 0) {
    $rowCurrentBal = mysqli_fetch_assoc($resCurrentBal);
    $currentBal = floatval($rowCurrentBal['BalanceAfter']);
  } else {
    $currentBal = 0; // Should not happen
  }
  
  $newBal = $currentBal + $todayCustomerPayments;
  
  // Insert payments transaction
  $sqlInsertPayments = "
    INSERT INTO tblcashtransactions(TransDate, TransType, Amount, BalanceAfter, Comments)
    VALUES (NOW(), 'IN', '$todayCustomerPayments', '$newBal', 'Customer Payments')
  ";
  mysqli_query($con, $sqlInsertPayments);
}

// ---------------------------------------------------------------------
// 3) CALCULATE CURRENT BALANCE
// ---------------------------------------------------------------------

// Get the most recent balance
$sqlCurrentBalance = "
  SELECT BalanceAfter 
  FROM tblcashtransactions 
  WHERE DATE(TransDate) = CURDATE()
  ORDER BY ID DESC 
  LIMIT 1
";
$resCurrentBalance = mysqli_query($con, $sqlCurrentBalance);

if (mysqli_num_rows($resCurrentBalance) > 0) {
  $rowCurrentBalance = mysqli_fetch_assoc($resCurrentBalance);
  $currentBalance = floatval($rowCurrentBalance['BalanceAfter']);
} else {
  // Fallback - should not happen
  $currentBalance = 0;
}

// ---------------------------------------------------------------------
// 4) HANDLE NEW MANUAL TRANSACTION
// ---------------------------------------------------------------------

$transactionError = '';

if (isset($_POST['submit'])) {
  $transtype = $_POST['transtype']; // 'IN' or 'OUT'
  $amount = floatval($_POST['amount']);
  $comments = mysqli_real_escape_string($con, $_POST['comments']);

  if ($amount <= 0) {
    $transactionError = 'Montant invalide. Doit être > 0';
  } 
  // Block withdrawal if balance is insufficient
  else if ($transtype == 'OUT' && $amount > $currentBalance) {
    $transactionError = 'Impossible d\'effectuer un retrait : montant supérieur au solde actuel';
  }
  else {
    // Get current balance
    $sqlCurrentBal = "
      SELECT BalanceAfter 
      FROM tblcashtransactions 
      WHERE DATE(TransDate) = CURDATE()
      ORDER BY ID DESC 
      LIMIT 1
    ";
    $resCurrentBal = mysqli_query($con, $sqlCurrentBal);
    
    if (mysqli_num_rows($resCurrentBal) > 0) {
      $rowCurrentBal = mysqli_fetch_assoc($resCurrentBal);
      $currentBal = floatval($rowCurrentBal['BalanceAfter']);
    } else {
      $currentBal = 0; // Should not happen
    }

    // Calculate new balance
    $newBal = ($transtype == 'IN') ? $currentBal + $amount : $currentBal - $amount;
    
    // Insert transaction
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
// 5) PREPARE DATA FOR DISPLAY
// ---------------------------------------------------------------------

// Today's totals
$todayTotalIn = $todayRegularSales + $todayCustomerPayments + $todayManualDeposits;
$todayTotalOut = $todayWithdrawals + $todayReturns;
$todayNet = $todayTotalIn - $todayTotalOut;

// Get yesterday's final balance (from initialization transaction)
$sqlYesterdayBalance = "
  SELECT Amount 
  FROM tblcashtransactions 
  WHERE DATE(TransDate) = CURDATE() 
  AND Comments = 'DAILY_INIT'
  LIMIT 1
";
$resYesterdayBalance = mysqli_query($con, $sqlYesterdayBalance);
if (mysqli_num_rows($resYesterdayBalance) > 0) {
  $rowYesterdayBalance = mysqli_fetch_assoc($resYesterdayBalance);
  $yesterdayBalance = floatval($rowYesterdayBalance['Amount']);
} else {
  $yesterdayBalance = 0;
}

// Determine if OUT transactions should be disabled
$outDisabled = ($currentBalance <= 0);

// Maximum withdrawal amount
$maxWithdrawal = ($currentBalance > 0) ? $currentBalance : 0;

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
    .type-init { 
      background-color: #d9edf7; 
      color: #3a87ad;
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
    <h1>Transactions en espèces</h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- Balance summary box -->
    <div class="row-fluid">
      <div class="span12">
        <div class="balance-box">
          <div class="row-fluid">
            <div class="span7">
              <h3>Solde actuel: <span class="<?php echo ($currentBalance > 0) ? 'text-success' : 'text-error'; ?>">
                <?php echo number_format($currentBalance, 2); ?>
              </span></h3>
              
              <h4>Détail du jour:</h4>
              <table class="table table-bordered table-striped" style="width: auto;">
                <tr>
                  <td>Solde initial (J-1):</td>
                  <td style="text-align: right;"><?php echo number_format($yesterdayBalance, 2); ?></td>
                </tr>
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
                  <th style="text-align: right;" class="<?php echo ($todayNet >= 0) ? 'text-success' : 'text-error'; ?>">
                    <?php echo number_format($todayNet, 2); ?>
                  </th>
                </tr>
              </table>
            </div>
            
            <div class="span5">
              <div style="padding: 20px; background-color: #eee; border-radius: 5px;">
                <h4>Informations supplémentaires:</h4>
                <p><strong>Ventes à terme (non incluses dans le solde):</strong><br>
                   <?php echo number_format($todayCreditSales, 2); ?></p>
                
                <?php if ($currentBalance <= 0): ?>
                  <p class="text-error"><strong>SOLDE INSUFFISANT:</strong><br>
                     Les retraits sont désactivés.</p>
                <?php else: ?>
                  <p><strong>Montant max. retirable:</strong><br>
                     <?php echo number_format($maxWithdrawal, 2); ?></p>
                <?php endif; ?>
                
                <p><small>Le solde est réinitialisé chaque jour, en reportant le solde final de la veille.</small></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- New transaction form -->
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
                    <span class="help-inline text-error">Retraits désactivés (solde insuffisant)</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="control-group">
                <label class="control-label">Montant :</label>
                <div class="controls">
                  <input type="number" name="amount" id="amount" step="0.01" min="0.01" required />
                  <span id="amount-warning" class="help-inline text-error" style="display: none;">
                    Le montant doit être inférieur au solde actuel
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

    <!-- Recent transactions list -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Transactions récentes</h5>
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
                $sqlList = "SELECT * FROM tblcashtransactions ORDER BY ID DESC LIMIT 50";
                $resList = mysqli_query($con, $sqlList);
                $cnt = 1;
                
                while ($row = mysqli_fetch_assoc($resList)) {
                  $id = $row['ID'];
                  $transDate = $row['TransDate'];
                  $transType = $row['TransType'];
                  $amount = floatval($row['Amount']);
                  $balance = floatval($row['BalanceAfter']);
                  $comments = $row['Comments'];
                  
                  // Determine CSS classes for types
                  $typeClass = '';
                  if ($transType == 'IN') {
                    $typeClass = 'type-in';
                    $transTypeLabel = 'IN';
                  } elseif ($transType == 'OUT') {
                    $typeClass = 'type-out';
                    $transTypeLabel = 'OUT';
                  } elseif ($transType == 'INIT') {
                    $typeClass = 'type-init';
                    $transTypeLabel = 'INIT';
                  }
                  
                  // Determine CSS class for comments
                  $commentsClass = '';
                  if ($comments == 'Daily Sale') {
                    $commentsClass = 'comments-daily-sale';
                  } elseif ($comments == 'Customer Payments') {
                    $commentsClass = 'comments-customer-payments';
                  }
                  
                  // Hide initialization rows unless viewing full history
                  $rowClass = '';
                  if ($comments == 'DAILY_INIT') {
                    $rowClass = 'style="background-color: #f9f9f9;"';
                    $commentsDisplay = 'Solde initial';
                  } else {
                    $commentsDisplay = $comments;
                  }
                ?>
                <tr <?php echo $rowClass; ?>>
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
                    <?php echo $commentsDisplay; ?>
                  </td>
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
  // Dynamic amount validation for withdrawals
  $('#transtype, #amount').on('change input', function() {
    var transType = $('#transtype').val();
    var amount = parseFloat($('#amount').val()) || 0;
    var maxWithdrawal = <?php echo $maxWithdrawal; ?>;
    
    if (transType === 'OUT') {
      if (amount > maxWithdrawal) {
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
  
  // Form submission validation
  $('form').on('submit', function(e) {
    var transType = $('#transtype').val();
    var amount = parseFloat($('#amount').val()) || 0;
    var maxWithdrawal = <?php echo $maxWithdrawal; ?>;
    
    if (transType === 'OUT' && amount > maxWithdrawal) {
      e.preventDefault();
      alert('Impossible d\'effectuer un retrait de ce montant. Maximum: ' + maxWithdrawal.toFixed(2));
      return false;
    }
    
    return true;
  });
});
</script>
</body>
</html>