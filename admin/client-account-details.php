<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['imsaid'] == 0)) {
  header('location:logout.php');
  exit;
}

// Récupère le nom/téléphone passés en GET
$customerName = mysqli_real_escape_string($con, $_GET['name']);
$mobile       = mysqli_real_escape_string($con, $_GET['mobile']);

// Traitement du paiement
if(isset($_POST['payDues'])) {
  $billingId = mysqli_real_escape_string($con, $_POST['billingId']);
  $paymentAmount = mysqli_real_escape_string($con, $_POST['paymentAmount']);
  $paymentMethod = mysqli_real_escape_string($con, $_POST['paymentMethod']);
  
  // Récupérer les montants actuels
  $getBilling = mysqli_query($con, "SELECT FinalAmount, Paid, Dues FROM tblcustomer WHERE ID='$billingId'");
  $billData = mysqli_fetch_assoc($getBilling);
  
  // Calculer les nouveaux montants
  $newPaid = $billData['Paid'] + $paymentAmount;
  $newDues = $billData['Dues'] - $paymentAmount;
  
  // Mettre à jour la base de données
  $updateQuery = "UPDATE tblcustomer SET 
                  Paid='$newPaid', 
                  Dues='$newDues',
                  ModeofPayment='$paymentMethod', 
                  LastUpdationDate=NOW() 
                  WHERE ID='$billingId'";
                  
  if(mysqli_query($con, $updateQuery)) {
    $msg = "Paiement de " . number_format($paymentAmount, 2) . " effectué avec succès.";
    
    // Enregistrer la transaction dans une table de paiements (si vous en avez une)
    $paymentRecord = "INSERT INTO tblpayments (BillingId, PaymentAmount, PaymentMethod, PaymentDate) 
                      VALUES ('$billingId', '$paymentAmount', '$paymentMethod', NOW())";
    mysqli_query($con, $paymentRecord);
    
    // Rediriger pour éviter les soumissions multiples
    header("Location: client-details.php?name=$customerName&mobile=$mobile&msg=$msg");
    exit;
  } else {
    $error = "Erreur lors du paiement: " . mysqli_error($con);
  }
}

// Requête : toutes les factures de ce client
$sql = "
  SELECT ID, BillingNumber, BillingDate, FinalAmount, Paid, Dues
  FROM tblcustomer
  WHERE CustomerName='$customerName' AND MobileNumber='$mobile'
  ORDER BY BillingDate DESC
";
$res = mysqli_query($con, $sql);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Détails du Compte Client</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .payment-form {
      margin-top: 20px;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      background-color: #f9f9f9;
    }
    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border: 1px solid transparent;
      border-radius: 4px;
    }
    .alert-success {
      color: #3c763d;
      background-color: #dff0d8;
      border-color: #d6e9c6;
    }
    .alert-danger {
      color: #a94442;
      background-color: #f2dede;
      border-color: #ebccd1;
    }
  </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <h1>Détails pour <?php echo htmlspecialchars($customerName); ?> (<?php echo htmlspecialchars($mobile); ?>)</h1>
  </div>
  <div class="container-fluid">
    <hr>
    
    <?php if(isset($_GET['msg'])){ ?>
    <div class="alert alert-success">
      <?php echo htmlspecialchars($_GET['msg']); ?>
    </div>
    <?php } ?>
    
    <?php if(isset($error)){ ?>
    <div class="alert alert-danger">
      <?php echo $error; ?>
    </div>
    <?php } ?>

    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>#</th>
          <th>Numéro de Facture</th>
          <th>Date</th>
          <th>Montant Final</th>
          <th>Payé</th>
          <th>Reste</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php
      // Variables pour cumuler les totaux
      $sumFinal = 0;
      $sumPaid  = 0;
      $sumDues  = 0;

      $cnt=1;
      while ($row = mysqli_fetch_assoc($res)) {
        $finalAmt = floatval($row['FinalAmount']);
        $paidAmt  = floatval($row['Paid']);
        $dueAmt   = floatval($row['Dues']);

        // On cumule
        $sumFinal += $finalAmt;
        $sumPaid  += $paidAmt;
        $sumDues  += $dueAmt;
        ?>
        <tr>
          <td><?php echo $cnt++; ?></td>
          <td><?php echo $row['BillingNumber']; ?></td>
          <td><?php echo $row['BillingDate']; ?></td>
          <td><?php echo number_format($finalAmt,2); ?></td>
          <td><?php echo number_format($paidAmt,2); ?></td>
          <td><?php echo number_format($dueAmt,2); ?></td>
          <td>
            <?php if($dueAmt > 0){ ?>
              <button type="button" class="btn btn-primary btn-sm pay-btn" data-toggle="modal" data-target="#paymentModal<?php echo $row['ID']; ?>">
                Effectuer un paiement
              </button>
              
              <!-- Modal pour le paiement -->
              <div class="modal fade" id="paymentModal<?php echo $row['ID']; ?>" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="paymentModalLabel">Paiement pour facture #<?php echo $row['BillingNumber']; ?></h5>
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                    </div>
                    <div class="modal-body">
                      <form method="post" action="">
                        <input type="hidden" name="billingId" value="<?php echo $row['ID']; ?>">
                        
                        <div class="form-group">
                          <label for="dueAmount<?php echo $row['ID']; ?>">Montant dû:</label>
                          <input type="text" class="form-control" id="dueAmount<?php echo $row['ID']; ?>" value="<?php echo number_format($dueAmt,2); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                          <label for="paymentAmount<?php echo $row['ID']; ?>">Montant à payer:</label>
                          <input type="number" step="0.01" min="0.01" max="<?php echo $dueAmt; ?>" class="form-control" id="paymentAmount<?php echo $row['ID']; ?>" name="paymentAmount" required>
                        </div>
                        
                        <div class="form-group">
                          <label for="paymentMethod<?php echo $row['ID']; ?>">Mode de paiement:</label>
                          <select class="form-control" id="paymentMethod<?php echo $row['ID']; ?>" name="paymentMethod" required>
                            <option value="">Sélectionner</option>
                            <option value="Espèces">Espèces</option>
                            <option value="Carte de crédit">Carte de crédit</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Virement bancaire">Virement bancaire</option>
                          </select>
                        </div>
                        
                        <button type="submit" name="payDues" class="btn btn-success">Confirmer le paiement</button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php } else { ?>
              <span class="badge badge-success">Payé intégralement</span>
            <?php } ?>
          </td>
        </tr>
        <?php
      }
      ?>
      </tbody>
      <tfoot>
        <tr style="font-weight: bold;">
          <td colspan="3" style="text-align: right;">TOTAL</td>
          <td><?php echo number_format($sumFinal,2); ?></td>
          <td><?php echo number_format($sumPaid,2); ?></td>
          <td><?php echo number_format($sumDues,2); ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div><!-- container-fluid -->
  
  <?php if($sumDues > 0) { ?>
  <div class="container-fluid">
    <div class="payment-form">
      <h4>Paiement du solde total</h4>
      <form method="post" action="">
        <input type="hidden" name="totalPayment" value="1">
        
        <div class="form-group">
          <label for="totalDueAmount">Montant total dû:</label>
          <input type="text" class="form-control" id="totalDueAmount" value="<?php echo number_format($sumDues,2); ?>" readonly>
        </div>
        
        <div class="form-group">
          <label for="paymentMethodTotal">Mode de paiement:</label>
          <select class="form-control" id="paymentMethodTotal" name="paymentMethodTotal" required>
            <option value="">Sélectionner</option>
            <option value="Espèces">Espèces</option>
            <option value="Carte de crédit">Carte de crédit</option>
            <option value="Mobile Money">Mobile Money</option>
            <option value="Virement bancaire">Virement bancaire</option>
          </select>
        </div>
        
        <button type="submit" name="payTotalDues" class="btn btn-primary">Payer le solde total</button>
      </form>
    </div>
  </div>
  <?php } ?>
  
  <div class="container-fluid">
    <a href="client-account.php" class="btn btn-secondary" style="margin: 15px 0;">← Retour</a>
  </div>
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
$(document).ready(function(){
  // Pour définir la valeur maximale du montant à payer
  $('.pay-btn').click(function(){
    var modalId = $(this).data('target');
    var dueAmount = $(modalId).find('[id^="dueAmount"]').val();
    dueAmount = parseFloat(dueAmount.replace(/,/g, ''));
    $(modalId).find('[id^="paymentAmount"]').attr('max', dueAmount);
  });
});
</script>
</body>
</html>