<?php
// ============== UPDATED return.php ==============
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Check if admin is logged in
if (strlen($_SESSION['imsaid']) == 0) {
  header('location:logout.php');
  exit;
}

// ==========================
// Handle new return submission with VALIDATION
// ==========================
if (isset($_POST['submit'])) {
  $billingNumber = mysqli_real_escape_string($con, $_POST['billingnumber']);
  $productID     = intval($_POST['productid']);
  $quantity      = intval($_POST['quantity']);
  $returnPrice   = floatval($_POST['price']);
  $returnDate    = $_POST['returndate'];
  $reason        = mysqli_real_escape_string($con, $_POST['reason']);

  // Validation flag
  $isValid = true;
  $errorMessage = "";

  // 1. Verify billing number exists
  $checkBilling = mysqli_query($con, "SELECT ID FROM tblcustomer WHERE BillingNumber = '$billingNumber'");
  if (mysqli_num_rows($checkBilling) == 0) {
    $isValid = false;
    $errorMessage = "Numéro de facture invalide. Cette facture n'existe pas.";
  } else {
    // 2. Get original sale details and validate quantity
    $originalQty = 0;
    $originalPrice = 0;
    
    // Check in cash sales (tblcart) - IMPORTANT: BillingId = BillingNumber dans votre système
    $cashQuery = "SELECT ProductQty, Price FROM tblcart 
                  WHERE BillingId = '$billingNumber' AND ProductId = '$productID' AND IsCheckOut = 1";
    $cashResult = mysqli_query($con, $cashQuery);
    
    if (mysqli_num_rows($cashResult) > 0) {
      $cashRow = mysqli_fetch_assoc($cashResult);
      $originalQty = $cashRow['ProductQty'];
      $originalPrice = $cashRow['Price'];
    }

    // Check if product was in this sale
    if ($originalQty == 0) {
      $isValid = false;
      $errorMessage = "Ce produit n'a pas été vendu dans cette facture.";
    } else {
      // 3. Check already returned quantity
      $returnedQuery = "SELECT SUM(Quantity) as TotalReturned FROM tblreturns 
                        WHERE BillingNumber = '$billingNumber' AND ProductID = '$productID'";
      $returnedResult = mysqli_query($con, $returnedQuery);
      $returnedRow = mysqli_fetch_assoc($returnedResult);
      $alreadyReturned = $returnedRow['TotalReturned'] ? $returnedRow['TotalReturned'] : 0;
      
      $availableToReturn = $originalQty - $alreadyReturned;
      
      if ($quantity > $availableToReturn) {
        $isValid = false;
        $errorMessage = "Quantité invalide. Vendu: $originalQty, Déjà retourné: $alreadyReturned, Maximum retournable: $availableToReturn";
      }
      
      // 4. Validate return price
      if ($returnPrice > $originalPrice) {
        $isValid = false;
        $errorMessage = "Le prix de retour ($returnPrice) ne peut pas dépasser le prix de vente original ($originalPrice).";
      }
    }
  }

  // Process if valid
  if ($isValid) {
    $sqlInsert = "INSERT INTO tblreturns(BillingNumber, ReturnDate, ProductID, Quantity, Reason, ReturnPrice) 
                  VALUES('$billingNumber', '$returnDate', '$productID', '$quantity', '$reason', '$returnPrice')";
    $queryInsert = mysqli_query($con, $sqlInsert);

    if ($queryInsert) {
      // Update product stock
      $sqlUpdate = "UPDATE tblproducts SET Stock = Stock + $quantity WHERE ID='$productID'";
      mysqli_query($con, $sqlUpdate);
      
      echo "<script>alert('Retour enregistré avec succès!');</script>";
    } else {
      echo "<script>alert('Erreur lors de l\'enregistrement.');</script>";
    }
  } else {
    echo "<script>alert('$errorMessage');</script>";
  }
  
  echo "<script>window.location.href='return.php'</script>";
  exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Gestion des stocks | Retours de Article</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  
  <script src="js/jquery.min.js"></script>
  <script>
  // AJAX function to validate billing and load products
  function validateBilling() {
    var billNum = $('#billingnumber').val();
    if (billNum.length > 0) {
      $.ajax({
        url: 'ajax/validate-billing.php',
        type: 'POST',
        data: {billingnumber: billNum},
        success: function(response) {
          var data = JSON.parse(response);
          if (data.valid) {
            $('#billing-info').html(data.customerInfo);
            $('#billing-info').removeClass('alert-error').addClass('alert-success');
            
            // Update product dropdown
            $('#productid').html(data.productOptions);
            $('#productid').prop('disabled', false);
          } else {
            $('#billing-info').html('<strong>Erreur:</strong> ' + data.message);
            $('#billing-info').removeClass('alert-success').addClass('alert-error');
            $('#productid').prop('disabled', true);
            $('#product-details').html('');
          }
          $('#billing-info').show();
        }
      });
    }
  }
  
  // Load product details when product is selected
  function loadProductDetails() {
    var productId = $('#productid').val();
    var billNum = $('#billingnumber').val();
    
    if (productId && billNum) {
      $.ajax({
        url: 'ajax/get-product-details.php',
        type: 'POST',
        data: {
          productid: productId,
          billingnumber: billNum
        },
        success: function(response) {
          var data = JSON.parse(response);
          $('#product-details').html(data.details);
          $('#product-details').show();
          
          // Set max quantity
          $('#quantity').attr('max', data.maxReturn);
          
          // Set price constraints
          $('#price').attr('max', data.originalPrice);
          $('#price').val(data.originalPrice);
        }
      });
    }
  }
  </script>
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
      <a href="return.php" class="current">Retours de Article</a>
    </div>
    <h1>Gérer les retours de Article</h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- =========== NEW RETURN FORM =========== -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-align-justify"></i></span>
            <h5>Ajouter un nouveau retour</h5>
          </div>
          <div class="widget-content nopadding">
            <form method="post" class="form-horizontal">

              <!-- Billing Number -->
              <div class="control-group">
                <label class="control-label">Numéro de facture :</label>
                <div class="controls">
                  <input type="text" id="billingnumber" name="billingnumber" 
                         placeholder="ex. 123456789" required onblur="validateBilling()" />
                  <div id="billing-info" class="alert" style="display:none; margin-top:10px;"></div>
                </div>
              </div>

              <!-- Return Date -->
              <div class="control-group">
                <label class="control-label">Date de retour :</label>
                <div class="controls">
                  <input type="date" name="returndate" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
              </div>

              <!-- Product Selection -->
              <div class="control-group">
                <label class="control-label">Sélectionner un produit :</label>
                <div class="controls">
                  <select id="productid" name="productid" required onchange="loadProductDetails()" disabled>
                    <option value="">-- Entrez d'abord le numéro de facture --</option>
                  </select>
                  <div id="product-details" class="alert alert-info" style="display:none; margin-top:10px;"></div>
                </div>
              </div>

              <!-- Quantity -->
              <div class="control-group">
                <label class="control-label">Quantité retournée :</label>
                <div class="controls">
                  <input type="number" id="quantity" name="quantity" min="1" value="1" required />
                </div>
              </div>

              <!-- Price -->
              <div class="control-group">
                <label class="control-label">Prix de retour :</label>
                <div class="controls">
                  <input type="number" id="price" name="price" step="0.01" min="0" value="0" required />
                  <span class="help-inline">Prix maximum basé sur le prix de vente original</span>
                </div>
              </div>

              <!-- Reason -->
              <div class="control-group">
                <label class="control-label">Raison (facultatif) :</label>
                <div class="controls">
                  <input type="text" name="reason" placeholder="ex. Défaut, Mauvaise taille, etc." />
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" name="submit" class="btn btn-success">
                  Enregistrer le retour
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <hr>

    <!-- =========== LIST OF RECENT RETURNS =========== -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Retours récents</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Numéro de facture</th>
                  <th>Date de retour</th>
                  <th>Produit</th>
                  <th>Quantité</th>
                  <th>Prix</th>
                  <th>Raison</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sqlReturns = "
                  SELECT r.ID as returnID,
                         r.BillingNumber,
                         r.ReturnDate,
                         r.Quantity,
                         r.Reason,
                         r.ReturnPrice,
                         p.ProductName
                  FROM tblreturns r
                  LEFT JOIN tblproducts p ON p.ID = r.ProductID
                  ORDER BY r.ID DESC
                  LIMIT 50
                ";
                $returnsQuery = mysqli_query($con, $sqlReturns);
                $cnt = 1;
                while ($row = mysqli_fetch_assoc($returnsQuery)) {
                  ?>
                  <tr>
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo $row['BillingNumber']; ?></td>
                    <td><?php echo $row['ReturnDate']; ?></td>
                    <td><?php echo $row['ProductName']; ?></td>
                    <td><?php echo $row['Quantity']; ?></td>
                    <td><?php echo number_format($row['ReturnPrice'],2); ?></td>
                    <td><?php echo $row['Reason']; ?></td>
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

<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.tables.js"></script>
</body>
</html>