<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid']) == 0) {
  header('location:logout.php');
  exit;
}

// ==========================
// 1) Gérer la suppression d'une facture COMPLÈTE
// ==========================
if (isset($_GET['delete_id']) && !empty($_GET['delete_id'])) {
  $billingId = intval($_GET['delete_id']);
  $type = $_GET['type']; // 'cart' ou 'credit'
  
  // Table à utiliser selon le type
  $tableToUse = ($type == 'credit') ? 'tblcreditcart' : 'tblcart';
  $customerTable = ($type == 'credit') ? 'tblcustomer' : 'tblcustumer'; // Note: you might have both tblcustomer and tblcustumer tables
  
  // Start transaction for complete deletion
  mysqli_autocommit($con, FALSE);
  
  try {
    // 1. Récupérer TOUS les articles de cette facture pour restaurer le stock
    $sqlCartItems = "SELECT ProductId, ProductQty FROM $tableToUse WHERE BillingId='$billingId'";
    $cartQuery = mysqli_query($con, $sqlCartItems);
    
    if (!$cartQuery) {
      throw new Exception('Failed to fetch cart items: ' . mysqli_error($con));
    }
    
    // 2. Restaurer le stock pour TOUS les produits de cette facture
    while ($item = mysqli_fetch_assoc($cartQuery)) {
      $productId = $item['ProductId'];
      $quantity = $item['ProductQty'];
      
      // Mettre à jour le stock dans tblproducts (augmenter le stock)
      $updateStock = "UPDATE tblproducts SET Stock = Stock + $quantity WHERE ID='$productId'";
      $updateResult = mysqli_query($con, $updateStock);
      
      if (!$updateResult) {
        throw new Exception('Failed to update stock for product ' . $productId . ': ' . mysqli_error($con));
      }
    }
    
    // 3. Supprimer TOUS les articles du panier avec ce BillingId
    $deleteCartQuery = "DELETE FROM $tableToUse WHERE BillingId='$billingId'";
    $deleteCartResult = mysqli_query($con, $deleteCartQuery);
    
    if (!$deleteCartResult) {
      throw new Exception('Failed to delete cart items: ' . mysqli_error($con));
    }
    
    $deletedCartItems = mysqli_affected_rows($con);
    
    // 4. Supprimer les paiements liés à cette facture (si la table existe)
    $checkPaymentTable = mysqli_query($con, "SHOW TABLES LIKE 'tblpayments'");
    if (mysqli_num_rows($checkPaymentTable) > 0) {
      $deletePaymentsQuery = "DELETE FROM tblpayments WHERE BillingNumber='$billingId'";
      $deletePaymentsResult = mysqli_query($con, $deletePaymentsQuery);
      
      if (!$deletePaymentsResult) {
        throw new Exception('Failed to delete payment records: ' . mysqli_error($con));
      }
      
      $deletedPayments = mysqli_affected_rows($con);
    } else {
      $deletedPayments = 0;
    }
    
    // 5. Supprimer le client/facture de la table customer
    // First check which customer table exists and has the record
    $customerTableToUse = '';
    $customerId = 0;
    
    // Check tblcustomer first
    $checkCustomer1 = mysqli_query($con, "SELECT ID FROM tblcustomer WHERE BillingNumber='$billingId' LIMIT 1");
    if ($checkCustomer1 && mysqli_num_rows($checkCustomer1) > 0) {
      $customerTableToUse = 'tblcustomer';
      $customerRow = mysqli_fetch_assoc($checkCustomer1);
      $customerId = $customerRow['ID'];
    } else {
      // Check tblcustumer (if it exists)
      $checkTable = mysqli_query($con, "SHOW TABLES LIKE 'tblcustumer'");
      if (mysqli_num_rows($checkTable) > 0) {
        $checkCustomer2 = mysqli_query($con, "SELECT ID FROM tblcustumer WHERE BillingNumber='$billingId' LIMIT 1");
        if ($checkCustomer2 && mysqli_num_rows($checkCustomer2) > 0) {
          $customerTableToUse = 'tblcustumer';
          $customerRow = mysqli_fetch_assoc($checkCustomer2);
          $customerId = $customerRow['ID'];
        }
      }
    }
    
    $deletedCustomer = 0;
    if (!empty($customerTableToUse)) {
      $deleteCustomerQuery = "DELETE FROM $customerTableToUse WHERE BillingNumber='$billingId'";
      $deleteCustomerResult = mysqli_query($con, $deleteCustomerQuery);
      
      if (!$deleteCustomerResult) {
        throw new Exception('Failed to delete customer record: ' . mysqli_error($con));
      }
      
      $deletedCustomer = mysqli_affected_rows($con);
    }
    
    // Commit all changes
    mysqli_commit($con);
    mysqli_autocommit($con, TRUE);
    
    // Success message with details
    $successMsg = "Facture $billingId supprimée avec succès!\\n";
    $successMsg .= "- Articles supprimés: $deletedCartItems\\n";
    $successMsg .= "- Client supprimé: $deletedCustomer\\n";
    $successMsg .= "- Paiements supprimés: $deletedPayments\\n";
    $successMsg .= "- Stock restauré pour tous les produits";
    
    echo "<script>alert('$successMsg');</script>";
    
  } catch (Exception $e) {
    // Rollback on error
    mysqli_rollback($con);
    mysqli_autocommit($con, TRUE);
    
    $errorMsg = "Erreur lors de la suppression: " . $e->getMessage();
    echo "<script>alert('$errorMsg');</script>";
  }
  
  echo "<script>window.location.href='facture.php'</script>";
  exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Gestion des stocks | Factures</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
</head>
<body>

<!-- Header + Sidebar -->
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <a href="facture.php" class="current">Factures</a>
    </div>
    <h1>Gérer les factures</h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- =========== ONGLETS POUR SÉPARER LES FACTURES =========== -->
    <div class="widget-box">
      <div class="widget-title">
        <ul class="nav nav-tabs">
          <li class="active"><a data-toggle="tab" href="#tab-comptant">Factures Comptant</a></li>
          <li><a data-toggle="tab" href="#tab-credit">Factures à Terme</a></li>
        </ul>
      </div>
      <div class="widget-content tab-content">
        <!-- ONGLET FACTURES COMPTANT -->
        <div id="tab-comptant" class="tab-pane active">
          <div class="widget-box">
            <div class="widget-title">
              <span class="icon"><i class="icon-th"></i></span>
              <h5>Liste des factures comptant</h5>
            </div>
            <div class="widget-content nopadding">
              <table class="table table-bordered data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Numéro de facture</th>
                    <th>Date</th>
                    <th>Nombre d'articles</th>
                    <th>Total</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // Récupérer la liste des factures comptant
                  $sqlFacturesComptant = "
                    SELECT 
                      BillingId, 
                      CartDate,
                      COUNT(*) as ItemCount,
                      SUM(Price * ProductQty) as Total
                    FROM tblcart 
                    WHERE IsCheckOut = 1
                    GROUP BY BillingId, CartDate
                    ORDER BY CartDate DESC
                  ";
                  $factureComptantQuery = mysqli_query($con, $sqlFacturesComptant);
                  $cnt = 1;
                  while ($row = mysqli_fetch_assoc($factureComptantQuery)) {
                    ?>
                    <tr>
                      <td><?php echo $cnt; ?></td>
                      <td><?php echo $row['BillingId']; ?></td>
                      <td><?php echo $row['CartDate']; ?></td>
                      <td><?php echo $row['ItemCount']; ?></td>
                      <td><?php echo number_format($row['Total'], 2); ?> GNF</td>
                      <td>
                        <a href="facture-details.php?id=<?php echo $row['BillingId']; ?>&type=cart" class="btn btn-info btn-mini">
                          <i class="icon-eye-open"></i> Détails
                        </a>
                        <a href="facture.php?delete_id=<?php echo $row['BillingId']; ?>&type=cart" 
                          class="btn btn-danger btn-mini" 
                          onclick="return confirm('ATTENTION: Cette action va supprimer COMPLÈTEMENT cette facture:\\n\\n- Tous les articles de la facture\\n- Le dossier client\\n- Tous les paiements liés\\n- Le stock sera restauré\\n\\nÊtes-vous absolument sûr?')">
                          <i class="icon-trash"></i> Supprimer
                        </a>
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
        
        <!-- ONGLET FACTURES À TERME -->
        <div id="tab-credit" class="tab-pane">
          <div class="widget-box">
            <div class="widget-title">
              <span class="icon"><i class="icon-th"></i></span>
              <h5>Liste des factures à terme</h5>
            </div>
            <div class="widget-content nopadding">
              <table class="table table-bordered data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Numéro de facture</th>
                    <th>Date</th>
                    <th>Nombre d'articles</th>
                    <th>Total</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  // Récupérer la liste des factures à crédit avec informations client
                  $sqlFacturesCredit = "
                    SELECT 
                      c.BillingId, 
                      c.CartDate,
                      COUNT(c.ID) as ItemCount,
                      SUM(c.Price * c.ProductQty) as Total,
                      cust.CustomerName,
                      cust.Dues
                    FROM tblcreditcart c
                    LEFT JOIN tblcustomer cust ON cust.BillingNumber = c.BillingId
                    WHERE c.IsCheckOut = 1
                    GROUP BY c.BillingId, c.CartDate, cust.CustomerName, cust.Dues
                    ORDER BY c.CartDate DESC
                  ";
                  $factureCreditQuery = mysqli_query($con, $sqlFacturesCredit);
                  $cnt = 1;
                  while ($row = mysqli_fetch_assoc($factureCreditQuery)) {
                    $duesClass = ($row['Dues'] > 0) ? 'text-warning' : 'text-success';
                    $duesText = ($row['Dues'] > 0) ? 'Impayé: ' . number_format($row['Dues'], 2) . ' GNF' : 'Soldé';
                    ?>
                    <tr>
                      <td><?php echo $cnt; ?></td>
                      <td><?php echo $row['BillingId']; ?></td>
                      <td><?php echo $row['CartDate']; ?></td>
                      <td><?php echo $row['ItemCount']; ?></td>
                      <td>
                        <?php echo number_format($row['Total'], 2); ?> GNF
                        <br><small class="<?php echo $duesClass; ?>"><?php echo $duesText; ?></small>
                        <?php if ($row['CustomerName']): ?>
                          <br><small class="text-muted">Client: <?php echo $row['CustomerName']; ?></small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="facture-details.php?id=<?php echo $row['BillingId']; ?>&type=credit" class="btn btn-info btn-mini">
                          <i class="icon-eye-open"></i> Détails
                        </a>
                        <a href="facture.php?delete_id=<?php echo $row['BillingId']; ?>&type=credit" 
                          class="btn btn-danger btn-mini" 
                          onclick="return confirm('ATTENTION: Cette action va supprimer COMPLÈTEMENT cette facture:\\n\\n- Tous les articles de la facture (<?php echo $row['ItemCount']; ?> articles)\\n- Le dossier client <?php echo $row['CustomerName'] ? '(' . $row['CustomerName'] . ')' : ''; ?>\\n- Tous les paiements liés\\n- Le stock sera restauré\\n\\nÊtes-vous absolument sûr?')">
                          <i class="icon-trash"></i> Supprimer
                        </a>
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
  </div><!-- container-fluid -->
</div><!-- content -->

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
</body>
</html>