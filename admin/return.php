<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
} else {

// Handle return submission
if(isset($_POST['process_return'])) {
    $invoice_id = mysqli_real_escape_string($con, $_POST['invoice_id']);
    $return_reason = mysqli_real_escape_string($con, $_POST['return_reason']);
    $return_date = date('Y-m-d H:i:s');
    
    // Generate return ID
    $return_id = 'RET' . date('YmdHis') . rand(100, 999);
    
    $total_return_amount = 0;
    $items_returned = 0;
    
    // Process each returned item
    if(isset($_POST['return_qty']) && is_array($_POST['return_qty'])) {
        foreach($_POST['return_qty'] as $product_id => $return_qty) {
            $return_qty = intval($return_qty);
            if($return_qty > 0) {
                // Get product details
                $product_query = mysqli_query($con, "SELECT Price FROM tblproducts WHERE ID='$product_id'");
                $product_data = mysqli_fetch_assoc($product_query);
                $unit_price = $product_data['Price'];
                $return_amount = $return_qty * $unit_price;
                
                // Insert return record
                $insert_return = mysqli_query($con, "INSERT INTO tblreturns 
                    (ReturnId, InvoiceId, ProductId, ReturnQty, UnitPrice, ReturnAmount, ReturnReason, ReturnDate) 
                    VALUES 
                    ('$return_id', '$invoice_id', '$product_id', '$return_qty', '$unit_price', '$return_amount', '$return_reason', '$return_date')");
                
                if($insert_return) {
                    // Update product stock (add back returned quantity)
                    mysqli_query($con, "UPDATE tblproducts SET ProductStock = ProductStock + $return_qty WHERE ID='$product_id'");
                    
                    $total_return_amount += $return_amount;
                    $items_returned++;
                }
            }
        }
    }
    
    if($items_returned > 0) {
        $success_msg = "Retour traité avec succès! ID de retour: $return_id";
    } else {
        $error_msg = "Aucun produit sélectionné pour le retour.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de Gestion d'Inventaire || Retour de Produits</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>
<style>
  .invoice-box {
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
  }
  .invoice-header {
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
  }
  .invoice-total {
    font-weight: bold;
    color: #d9534f;
  }
  .search-form {
    background-color: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
  }
  .customer-info td, .customer-info th {
    padding: 8px;
  }
  
  /* Return-specific styles */
  .return-form {
    background-color: #e8f5e8;
    border: 2px solid #4caf50;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
  }
  
  .return-qty-input {
    width: 60px;
    text-align: center;
  }
  
  .return-checkbox {
    margin-right: 10px;
  }
  
  .return-row {
    background-color: #f0f8f0;
  }
  
  .return-summary {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    padding: 15px;
    margin-top: 20px;
  }
  
  .alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
  }
  
  .alert-error {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
  }
  
  .credit-badge {
    display: inline-block;
    padding: 3px 7px;
    background-color: #f0ad4e;
    color: white;
    border-radius: 3px;
    font-size: 12px;
    margin-left: 10px;
  }
  
  .dues-info {
    background-color: #fff3cd;
    padding: 10px;
    border: 1px solid #ffeeba;
    border-radius: 4px;
    margin-top: 10px;
    margin-bottom: 10px;
  }
  
  .payment-label {
    font-weight: bold;
    color: #856404;
  }
</style>
<script>
function toggleReturnRow(checkbox, productId) {
    var row = document.getElementById('product_row_' + productId);
    var qtyInput = document.getElementById('return_qty_' + productId);
    
    if(checkbox.checked) {
        row.classList.add('return-row');
        qtyInput.disabled = false;
        qtyInput.focus();
    } else {
        row.classList.remove('return-row');
        qtyInput.disabled = true;
        qtyInput.value = '';
    }
    calculateReturnTotal();
}

function validateReturnQty(input, maxQty) {
    var value = parseInt(input.value);
    if(value > maxQty) {
        alert('La quantité de retour ne peut pas dépasser la quantité achetée (' + maxQty + ')');
        input.value = maxQty;
    }
    if(value < 0) {
        input.value = 0;
    }
    calculateReturnTotal();
}

function calculateReturnTotal() {
    var total = 0;
    var checkboxes = document.querySelectorAll('input[name="return_items[]"]:checked');
    
    checkboxes.forEach(function(checkbox) {
        var productId = checkbox.value;
        var qtyInput = document.getElementById('return_qty_' + productId);
        var unitPrice = parseFloat(document.getElementById('unit_price_' + productId).value);
        var qty = parseInt(qtyInput.value) || 0;
        
        total += qty * unitPrice;
    });
    
    document.getElementById('return_total_display').textContent = total.toFixed(2);
}

function selectAllProducts() {
    var checkboxes = document.querySelectorAll('input[name="return_items[]"]');
    var selectAll = document.getElementById('select_all').checked;
    
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = selectAll;
        toggleReturnRow(checkbox, checkbox.value);
    });
}
</script>
</head>
<body>
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb"> 
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> 
      <a href="product-return.php" class="current">Retour de Produits</a> 
    </div>
    <h1>Retour de Produits</h1>
  </div>
  
  <div class="container-fluid">
    <hr>
    
    <?php if(isset($success_msg)) { ?>
    <div class="alert alert-success">
      <button class="close" data-dismiss="alert">×</button>
      <strong>Succès!</strong> <?php echo $success_msg; ?>
    </div>
    <?php } ?>
    
    <?php if(isset($error_msg)) { ?>
    <div class="alert alert-error">
      <button class="close" data-dismiss="alert">×</button>
      <strong>Erreur!</strong> <?php echo $error_msg; ?>
    </div>
    <?php } ?>
    
    <div class="row-fluid">
      <div class="span12">
        <!-- Search Form -->
        <div class="widget-box search-form">
          <div class="widget-title">
            <span class="icon"><i class="icon-search"></i></span>
            <h5>Rechercher une Facture pour Retour</h5>
          </div>
          <div class="widget-content">
            <form method="post" class="form-horizontal">
              <div class="control-group">
                <label class="control-label">Rechercher par :</label>
                <div class="controls">
                  <input type="text" class="span6" name="searchdata" id="searchdata" value="<?php echo isset($_POST['searchdata']) ? htmlspecialchars($_POST['searchdata']) : ''; ?>" required='true' placeholder="Numéro de facture ou numéro de mobile"/>
                  <button class="btn btn-primary" type="submit" name="search"><i class="icon-search"></i> Rechercher</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      
        <?php
        if(isset($_POST['search'])) { 
          $sdata = mysqli_real_escape_string($con, $_POST['searchdata']);
        ?>
        <div>
          <h4 align="center">Facture trouvée pour "<?php echo htmlspecialchars($sdata); ?>"</h4>
        </div>
        
        <?php
        // Get customer information
        $stmt = mysqli_prepare($con, "SELECT DISTINCT 
                                      tblcustomer.CustomerName,
                                      tblcustomer.MobileNumber,
                                      tblcustomer.ModeofPayment,
                                      tblcustomer.BillingDate,
                                      tblcustomer.BillingNumber,
                                      tblcustomer.FinalAmount,
                                      tblcustomer.Paid,
                                      tblcustomer.Dues
                                    FROM 
                                      tblcustomer
                                    LEFT JOIN 
                                      tblcart ON tblcustomer.BillingNumber = tblcart.BillingId
                                    LEFT JOIN 
                                      tblcreditcart ON tblcustomer.BillingNumber = tblcreditcart.BillingId
                                    WHERE 
                                      tblcustomer.BillingNumber = ? OR tblcustomer.MobileNumber = ?
                                    LIMIT 1");
        
        mysqli_stmt_bind_param($stmt, "ss", $sdata, $sdata);
        mysqli_stmt_execute($stmt);
        $customerResult = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($customerResult) > 0) {
          $customerRow = mysqli_fetch_assoc($customerResult);
          $invoiceid = $customerRow['BillingNumber'];
          $finalAmount = $customerRow['FinalAmount'];
          $paidAmount = $customerRow['Paid'];
          $duesAmount = $customerRow['Dues'];
          $formattedDate = date("d/m/Y", strtotime($customerRow['BillingDate']));
          
          $isCredit = ($customerRow['Dues'] > 0 || $customerRow['ModeofPayment'] == 'credit');
          
          // Determine which table to use
          $checkCreditCart = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcreditcart WHERE BillingId='$invoiceid'");
          $checkRegularCart = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcart WHERE BillingId='$invoiceid'");
          
          $creditItems = 0;
          $regularItems = 0;
          
          if ($rowCredit = mysqli_fetch_assoc($checkCreditCart)) {
            $creditItems = $rowCredit['count'];
          }
          
          if ($rowRegular = mysqli_fetch_assoc($checkRegularCart)) {
            $regularItems = $rowRegular['count'];
          }
          
          $useTable = ($creditItems > 0) ? 'tblcreditcart' : 'tblcart';
        ?>
        
        <div class="invoice-box">
          <div class="invoice-header">
            <div class="row-fluid">
              <div class="span6">
                <h3>
                  Facture #<?php echo htmlspecialchars($invoiceid); ?>
                  <?php if ($isCredit): ?>
                  <span class="credit-badge">Vente à Terme</span>
                  <?php endif; ?>
                </h3>
                <p>Date: <?php echo $formattedDate; ?></p>
              </div>
              <div class="span6 text-right">
                <h4><?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?></h4>
                <p>Système de Gestion d'Inventaire</p>
              </div>
            </div>
          </div>
          
          <table class="table customer-info">
            <tr>
              <th width="25%">Nom du client:</th>
              <td width="25%"><?php echo htmlspecialchars($customerRow['CustomerName']); ?></td>
              <th width="25%">Numéro de mobile:</th>
              <td width="25%"><?php echo htmlspecialchars($customerRow['MobileNumber']); ?></td>
            </tr>
            <tr>
              <th>Mode de paiement:</th>
              <td colspan="3"><?php echo htmlspecialchars($customerRow['ModeofPayment']); ?></td>
            </tr>
          </table>
          
          <?php if ($isCredit): ?>
          <div class="dues-info">
            <div class="row-fluid">
              <div class="span4">
                <span class="payment-label">Montant total:</span> 
                <span class="payment-value"><?php echo number_format($finalAmount, 2); ?> GNF</span>
              </div>
              <div class="span4">
                <span class="payment-label">Montant payé:</span> 
                <span class="payment-value"><?php echo number_format($paidAmount, 2); ?> GNF</span>
              </div>
              <div class="span4">
                <span class="payment-label">Reste à payer:</span> 
                <span class="payment-value"><?php echo number_format($duesAmount, 2); ?> GNF</span>
              </div>
            </div>
          </div>
          <?php endif; ?>
        
          <!-- Return Form -->
          <form method="post" class="return-form">
            <input type="hidden" name="invoice_id" value="<?php echo $invoiceid; ?>">
            
            <div class="widget-box">
              <div class="widget-title"> 
                <span class="icon"><i class="icon-th"></i></span>
                <h5>Sélectionner les produits à retourner</h5>
              </div>
              <div class="widget-content nopadding">
                <table class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th width="5%">
                        <input type="checkbox" id="select_all" onchange="selectAllProducts()"> Tout
                      </th>
                      <th width="25%">Nom du produit</th>
                      <th width="15%">Référence</th>
                      <th width="10%">Qté achetée</th>
                      <th width="15%">Prix unitaire</th>
                      <th width="15%">Qté à retourner</th>
                      <th width="15%">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                  // Get product details
                  $stmt = mysqli_prepare($con, "SELECT 
                                              tblproducts.ID,
                                              tblproducts.ProductName,
                                              tblproducts.ModelNumber,
                                              tblproducts.Price,
                                              $useTable.ProductQty,
                                              $useTable.Price as CartPrice
                                            FROM 
                                              $useTable
                                            JOIN 
                                              tblproducts ON $useTable.ProductId = tblproducts.ID
                                            WHERE 
                                              $useTable.BillingId = ?
                                            ORDER BY
                                              tblproducts.ProductName ASC");
                  
                  mysqli_stmt_bind_param($stmt, "s", $invoiceid);
                  mysqli_stmt_execute($stmt);
                  $productResult = mysqli_stmt_get_result($stmt);
                  
                  if(mysqli_num_rows($productResult) > 0) {
                    while($productRow = mysqli_fetch_assoc($productResult)) {
                      $product_id = $productRow['ID'];
                      $pq = $productRow['ProductQty'];
                      $ppu = $productRow['CartPrice'] ?: $productRow['Price'];
                      $total = $pq * $ppu;
                  ?>
                    <tr id="product_row_<?php echo $product_id; ?>">
                      <td>
                        <input type="checkbox" name="return_items[]" value="<?php echo $product_id; ?>" 
                               class="return-checkbox" onchange="toggleReturnRow(this, <?php echo $product_id; ?>)">
                      </td>
                      <td><?php echo htmlspecialchars($productRow['ProductName']); ?></td>
                      <td><?php echo htmlspecialchars($productRow['ModelNumber']); ?></td>
                      <td><?php echo $pq; ?></td>
                      <td><?php echo number_format($ppu, 2); ?></td>
                      <td>
                        <input type="number" 
                               id="return_qty_<?php echo $product_id; ?>"
                               name="return_qty[<?php echo $product_id; ?>]" 
                               class="return-qty-input" 
                               min="0" 
                               max="<?php echo $pq; ?>" 
                               disabled
                               onchange="validateReturnQty(this, <?php echo $pq; ?>)"
                               oninput="calculateReturnTotal()">
                        <input type="hidden" id="unit_price_<?php echo $product_id; ?>" value="<?php echo $ppu; ?>">
                      </td>
                      <td><?php echo number_format($total, 2); ?></td>
                    </tr>
                  <?php
                    }
                  } else { 
                  ?>
                    <tr>
                      <td colspan="7" class="text-center">Aucun produit trouvé pour cette facture</td>
                    </tr>
                  <?php 
                  } 
                  ?>
                  </tbody>
                </table>
              </div>
            </div>
            
            <!-- Return Summary and Reason -->
            <div class="return-summary">
              <div class="row-fluid">
                <div class="span6">
                  <div class="control-group">
                    <label class="control-label"><strong>Motif du retour:</strong></label>
                    <div class="controls">
                      <select name="return_reason" required class="span10">
                        <option value="">Sélectionner un motif</option>
                        <option value="Produit défectueux">Produit défectueux</option>
                        <option value="Erreur de commande">Erreur de commande</option>
                        <option value="Client insatisfait">Client insatisfait</option>
                        <option value="Produit endommagé">Produit endommagé</option>
                        <option value="Autre">Autre</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="span6">
                  <div class="text-right">
                    <h4>Montant total du retour: <span id="return_total_display">0.00</span> GNF</h4>
                  </div>
                </div>
              </div>
              
              <div class="row-fluid">
                <div class="span12 text-center">
                  <button type="submit" name="process_return" class="btn btn-success btn-large">
                    <i class="icon-ok"></i> Traiter le Retour
                  </button>
                  <button type="button" class="btn btn-secondary" onclick="window.location.reload();">
                    <i class="icon-refresh"></i> Annuler
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
        <?php 
        } else { 
        ?>
          <div class="alert alert-error">
            <button class="close" data-dismiss="alert">×</button>
            <strong>Erreur!</strong> Aucune facture trouvée pour ce numéro de facture ou numéro de mobile.
          </div>
        <?php 
        }
        } 
        ?>
      </div>
    </div>
  </div>
</div>

<?php include_once('includes/footer.php');?>

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
<?php } ?>