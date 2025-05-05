<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
} else {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de Gestion d'Inventaire || Rechercher Facture</title>
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
  @media print {
    .no-print {
      display: none !important;
    }
    .container-fluid {
      padding: 0;
    }
    body {
      padding: 0;
      margin: 0;
    }
    .invoice-box {
      border: none;
      background: none;
    }
  }
</style>
</head>
<body>

<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>

<div id="content">
  <div id="content-header" class="no-print">
    <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="invoice-search.php" class="current">Rechercher Facture</a> </div>
    <h1>Rechercher Facture</h1>
  </div>
  <div class="container-fluid">
    <hr class="no-print">
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box search-form no-print">
          <div class="widget-title">
            <span class="icon"><i class="icon-search"></i></span>
            <h5>Rechercher une Facture</h5>
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
          // Sécuriser la donnée de recherche
          $sdata = mysqli_real_escape_string($con, $_POST['searchdata']);
        ?>
        <div class="no-print">
          <h4 align="center">Résultat pour le mot-clé "<?php echo htmlspecialchars($sdata); ?>"</h4>
        </div>
        
        <?php
        // Préparer la requête pour récupérer les informations du client
        $stmt = mysqli_prepare($con, "SELECT DISTINCT 
                                      tblcustomer.CustomerName,
                                      tblcustomer.MobileNumber,
                                      tblcustomer.ModeofPayment,
                                      tblcustomer.BillingDate,
                                      tblcustomer.BillingNumber,
                                      tblcustomer.FinalAmount
                                    FROM 
                                      tblcustomer
                                    LEFT JOIN 
                                      tblcart ON tblcustomer.BillingNumber = tblcart.BillingId
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
          $formattedDate = date("d/m/Y", strtotime($customerRow['BillingDate']));
        ?>
        <div id="printArea">
          <div class="invoice-box">
            <div class="invoice-header">
              <div class="row-fluid">
                <div class="span6">
                  <h3>Facture #<?php echo htmlspecialchars($invoiceid); ?></h3>
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
          
            <div class="widget-box">
              <div class="widget-title"> 
                <span class="icon"><i class="icon-th"></i></span>
                <h5>Détails des produits</h5>
              </div>
              <div class="widget-content nopadding">
                <table class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th width="5%">N°</th>
                      <th width="35%">Nom du produit</th>
                      <th width="15%">Référence</th>
                      <th width="10%">Quantité</th>
                      <th width="15%">Prix unitaire</th>
                      <th width="20%">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php
                  // Préparer la requête pour récupérer les détails des produits
                  $stmt = mysqli_prepare($con, "SELECT 
                                              tblproducts.ProductName,
                                              tblproducts.ModelNumber,
                                              tblproducts.Price,
                                              tblcart.ProductQty,
                                              tblcart.Price as CartPrice
                                            FROM 
                                              tblcart
                                            JOIN 
                                              tblproducts ON tblcart.ProductId = tblproducts.ID
                                            WHERE 
                                              tblcart.BillingId = ?
                                            ORDER BY
                                              tblproducts.ProductName ASC");
                  
                  mysqli_stmt_bind_param($stmt, "s", $invoiceid);
                  mysqli_stmt_execute($stmt);
                  $productResult = mysqli_stmt_get_result($stmt);
                  
                  if(mysqli_num_rows($productResult) > 0) {
                    $cnt = 1;
                    $gtotal = 0;
                    
                    while($productRow = mysqli_fetch_assoc($productResult)) {
                      $pq = $productRow['ProductQty'];
                      $ppu = $productRow['CartPrice'] ?: $productRow['Price']; // Utiliser le prix du panier s'il existe
                      $total = $pq * $ppu;
                      $gtotal += $total;
                  ?>
                    <tr>
                      <td><?php echo $cnt; ?></td>
                      <td><?php echo htmlspecialchars($productRow['ProductName']); ?></td>
                      <td><?php echo htmlspecialchars($productRow['ModelNumber']); ?></td>
                      <td><?php echo $pq; ?></td>
                      <td><?php echo number_format($ppu, 2); ?></td>
                      <td><?php echo number_format($total, 2); ?></td>
                    </tr>
                  <?php
                      $cnt++;
                    }
                  
                    // Si le montant final existe dans la base de données, l'utiliser
                    $displayTotal = $finalAmount ?: $gtotal;
                  ?>
                    <tr>
                      <th colspan="5" class="text-right invoice-total">Total général</th>
                      <th class="invoice-total"><?php echo number_format($displayTotal, 2); ?></th>
                    </tr>
                  <?php 
                  } else { 
                  ?>
                    <tr>
                      <td colspan="6" class="text-center">Aucun produit trouvé pour cette facture</td>
                    </tr>
                  <?php 
                  } 
                  ?>
                  </tbody>
                </table>
              </div>
            </div>
            
            <div class="row-fluid no-print">
              <div class="span12 text-center">
                <button class="btn btn-primary" onclick="window.print();">
                  <i class="icon-print"></i> Imprimer Facture
                </button>
              </div>
            </div>
          </div>
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

<!--Footer-part-->
<?php include_once('includes/footer.php');?>
<!--end-Footer-part-->
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