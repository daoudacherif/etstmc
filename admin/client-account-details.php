<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['imsaid'] == 0)) {
  header('location:logout.php');
  exit;
}

/**
 * Obtenir un access token OAuth2 de Nimba using cURL.
 */
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token";  // Verify this URL with your Nimba documentation.
    
    // Replace with your real credentials
    $client_id     = "1608e90e20415c7edf0226bf86e7effd";      
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    
    // Encode the credentials in Base64 ("client_id:client_secret")
    $credentials = base64_encode($client_id . ":" . $client_secret);
    
    $headers = array(
        "Authorization: Basic " . $credentials,
        "Content-Type: application/x-www-form-urlencoded"
    );
    
    $postData = http_build_query(array(
        "grant_type" => "client_credentials"
    ));
    
    // Use cURL for the POST request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // For development only!
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === FALSE) {
        $error = curl_error($ch);
        error_log("cURL error while obtaining token: " . $error);
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    
    if ($httpCode != 200) {
        error_log("Error obtaining access token. HTTP Code: $httpCode. Response: $response");
        return false;
    }
    
    $decoded = json_decode($response, true);
    if (!isset($decoded['access_token'])) {
        error_log("API error (token): " . print_r($decoded, true));
        return false;
    }
    return $decoded['access_token'];
}

/**
 * Function to send an SMS via the Nimba API.
 * The message content is passed via the $message parameter.
 * The payload sent is logged so you can verify the SMS content.
 */
function sendSmsNotification($to, $message) {
    // Nimba API endpoint for sending SMS
    $url = "https://api.nimbasms.com/v1/messages";
    
    // Replace with your actual service credentials (as provided by Nimba)
    $service_id    = "1608e90e20415c7edf0226bf86e7effd";    
    $secret_token  = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    
    // Build the Basic Auth string (Base64 of "service_id:secret_token")
    $authString = base64_encode($service_id . ":" . $secret_token);
    
    // Prepare the JSON payload with recipient, message and sender_name
    $payload = array(
        "to"          => array($to),
        "message"     => $message,
        "sender_name" => "SMS 9080"   // Replace with your approved sender name with Nimba
    );
    $postData = json_encode($payload);
    
    // Log the payload for debugging (check your server error logs)
    error_log("Nimba SMS Payload: " . $postData);
    
    $headers = array(
        "Authorization: Basic " . $authString,
        "Content-Type: application/json"
    );
    
    $options = array(
        "http" => array(
            "method"        => "POST",
            "header"        => implode("\r\n", $headers),
            "content"       => $postData,
            "ignore_errors" => true
        )
    );
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    // Log complete API response for debugging
    error_log("Nimba API SMS Response: " . $response);
    
    // Retrieve HTTP status code from response headers
    $http_response_header = isset($http_response_header) ? $http_response_header : array();
    if (empty($http_response_header)) {
        error_log("No HTTP response headers - SMS send failed");
        return false;
    }
    
    $status_line = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
    $status_code = isset($match[1]) ? $match[1] : 0;
    
    if ($status_code != 201) {
        error_log("SMS send failed. HTTP Code: $status_code. Details: " . print_r(json_decode($response, true), true));
        return false;
    }
    
    return true;
}

// Récupère le nom/téléphone passés en GET
$customerName = mysqli_real_escape_string($con, $_GET['name']);
$mobile       = mysqli_real_escape_string($con, $_GET['mobile']);

// Traitement du paiement total
if(isset($_POST['payTotalDues'])) {
  $paymentAmount = mysqli_real_escape_string($con, $_POST['paymentAmount']);
  $paymentMethod = mysqli_real_escape_string($con, $_POST['paymentMethod']);
  $sendSms = isset($_POST['sendSms']) ? true : false;
  
  // Récupérer toutes les factures avec des dettes
  $getBillings = mysqli_query($con, "SELECT ID, Dues FROM tblcustomer 
                                    WHERE CustomerName='$customerName' 
                                    AND MobileNumber='$mobile' 
                                    AND Dues > 0 
                                    ORDER BY BillingDate ASC");
  
  $remainingPayment = $paymentAmount;
  $updatedBills = 0;
  
  // Commencer une transaction
  mysqli_begin_transaction($con);
  
  try {
    // Parcourir les factures pour appliquer le paiement, en commençant par les plus anciennes
    while($bill = mysqli_fetch_assoc($getBillings) && $remainingPayment > 0) {
      $billId = $bill['ID'];
      $currentDues = $bill['Dues'];
      
      // Déterminer combien appliquer à cette facture
      $amountToApply = min($remainingPayment, $currentDues);
      
      if($amountToApply > 0) {
        // Mettre à jour cette facture
        $updateQuery = "UPDATE tblcustomer SET 
                        Paid = Paid + $amountToApply, 
                        Dues = Dues - $amountToApply,
                        ModeofPayment = '$paymentMethod',
                        LastUpdationDate = NOW()
                        WHERE ID = '$billId'";
                        
        mysqli_query($con, $updateQuery);
        
        // Enregistrer cette transaction de paiement
        $paymentRecord = "INSERT INTO tblpayments (BillingId, PaymentAmount, PaymentMethod, PaymentDate) 
                          VALUES ('$billId', '$amountToApply', '$paymentMethod', NOW())";
        mysqli_query($con, $paymentRecord);
        
        $remainingPayment -= $amountToApply;
        $updatedBills++;
      }
    }
    
    // Valider la transaction
    mysqli_commit($con);
    
    // Récupérer le solde restant après le paiement
    $getRemainingDues = mysqli_query($con, "SELECT SUM(Dues) as totalDues FROM tblcustomer 
                                          WHERE CustomerName='$customerName' 
                                          AND MobileNumber='$mobile'");
    $duesData = mysqli_fetch_assoc($getRemainingDues);
    $remainingDues = $duesData['totalDues'];
    
    // Envoyer SMS de confirmation si option cochée
    if ($sendSms && !empty($mobile)) {
      $formattedAmount = number_format($paymentAmount, 2);
      $formattedDues = number_format($remainingDues, 2);
      
      $smsMessage = "Cher(e) $customerName, votre paiement de $formattedAmount a été reçu avec succès. ";
      
      if ($remainingDues > 0) {
        $smsMessage .= "Votre solde restant est de $formattedDues.";
      } else {
        $smsMessage .= "Toutes vos factures sont maintenant réglées. Merci!";
      }
      
      // Envoyer le SMS au client
      $smsResult = sendSmsNotification($mobile, $smsMessage);
      
      if ($smsResult) {
        $msg = "Paiement de " . number_format($paymentAmount, 2) . " effectué avec succès. SMS de confirmation envoyé.";
      } else {
        $msg = "Paiement de " . number_format($paymentAmount, 2) . " effectué avec succès. Échec de l'envoi du SMS.";
      }
    } else {
      $msg = "Paiement de " . number_format($paymentAmount, 2) . " effectué avec succès. $updatedBills facture(s) mise(s) à jour.";
    }
    
    // Rediriger pour éviter les soumissions multiples
    header("Location: client-details.php?name=$customerName&mobile=$mobile&msg=$msg");
    exit;
    
  } catch(Exception $e) {
    // Annuler la transaction en cas d'erreur
    mysqli_rollback($con);
    $error = "Erreur lors du paiement: " . $e->getMessage();
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
      padding: 20px;
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
    .payment-summary {
      font-size: 18px;
      font-weight: bold;
      margin-bottom: 15px;
      color: #333;
    }
    .client-info {
      background-color: #f5f5f5;
      border-left: 4px solid #337ab7;
      padding: 10px 15px;
      margin-bottom: 20px;
    }
    .sms-option {
      margin-top: 15px;
      padding: 10px;
      background-color: #e8f4fb;
      border-radius: 5px;
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
    
    <div class="client-info">
      <h4>Informations client</h4>
      <p><strong>Nom:</strong> <?php echo htmlspecialchars($customerName); ?></p>
      <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($mobile); ?></p>
    </div>

    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>#</th>
          <th>Numéro de Facture</th>
          <th>Date</th>
          <th>Montant Final</th>
          <th>Payé</th>
          <th>Reste</th>
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
        </tr>
      </tfoot>
    </table>
  </div><!-- container-fluid -->
  
  <?php if($sumDues > 0) { ?>
  <div class="container-fluid">
    <div class="payment-form">
      <h3>Règlement de votre solde</h3>
      <div class="payment-summary">
        Total des factures: <?php echo number_format($sumFinal,2); ?> <br>
        Total payé: <?php echo number_format($sumPaid,2); ?> <br>
        Solde restant dû: <?php echo number_format($sumDues,2); ?>
      </div>
      
      <form method="post" action="">
        <div class="form-group">
          <label for="paymentAmount"><strong>Montant à payer:</strong></label>
          <input type="number" step="0.01" min="0.01" max="<?php echo $sumDues; ?>" class="form-control" id="paymentAmount" name="paymentAmount" value="<?php echo $sumDues; ?>" required>
          <small class="form-text text-muted">Le paiement sera appliqué aux factures les plus anciennes en premier.</small>
        </div>
        
        <div class="form-group">
          <label for="paymentMethod"><strong>Mode de paiement:</strong></label>
          <select class="form-control" id="paymentMethod" name="paymentMethod" required>
            <option value="">Sélectionner</option>
            <option value="Espèces">Espèces</option>
            <option value="Carte de crédit">Carte de crédit</option>
            <option value="Mobile Money">Mobile Money</option>
            <option value="Virement bancaire">Virement bancaire</option>
            <option value="Chèque">Chèque</option>
          </select>
        </div>
        
        <div class="sms-option">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="sendSms" name="sendSms" checked>
            <label class="form-check-label" for="sendSms">Envoyer un SMS de confirmation au client</label>
          </div>
          <small class="form-text text-muted">Un SMS sera envoyé au numéro <?php echo htmlspecialchars($mobile); ?> contenant les détails du paiement et le solde restant.</small>
        </div>
        
        <button type="submit" name="payTotalDues" class="btn btn-success btn-lg" style="margin-top: 15px;">Effectuer le paiement</button>
      </form>
    </div>
  </div>
  <?php } else { ?>
  <div class="container-fluid">
    <div class="alert alert-success">
      <h4>Félicitations! Toutes vos factures sont intégralement payées.</h4>
      <p>Vous n'avez aucun solde dû actuellement.</p>
      
      <div class="sms-option" style="margin-top: 15px;">
        <form method="post" action="">
          <input type="hidden" name="sendThankYouSms" value="1">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="sendSms" name="sendSms" checked>
            <label class="form-check-label" for="sendSms">Envoyer un SMS de remerciement au client</label>
          </div>
          <small class="form-text text-muted">Un SMS sera envoyé au numéro <?php echo htmlspecialchars($mobile); ?> pour remercier le client.</small>
          <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Envoyer SMS</button>
        </form>
      </div>
    </div>
  </div>
  <?php } ?>
  
  <!-- Traitement du SMS de remerciement -->
  <?php
  if(isset($_POST['sendThankYouSms']) && isset($_POST['sendSms'])) {
    $thankYouMessage = "Cher(e) $customerName, nous vous remercions pour votre paiement. Toutes vos factures sont réglées. À bientôt!";
    $smsResult = sendSmsNotification($mobile, $thankYouMessage);
    
    if($smsResult) {
      echo '<script>alert("SMS de remerciement envoyé avec succès.");</script>';
    } else {
      echo '<script>alert("Échec de l\'envoi du SMS de remerciement.");</script>';
    }
  }
  ?>
  
  <div class="container-fluid">
    <a href="client-account-details.php" class="btn btn-secondary" style="margin: 15px 0;">← Retour</a>
  </div>
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>