<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid']) == 0) {
  header('location:logout.php');
  exit;
}

// Vérifier si l'ID de la facture est fourni
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['type'])) {
  header('location:facture.php');
  exit;
}

$billingId = intval($_GET['id']);
$type = $_GET['type']; // 'cart' ou 'credit'

// Table à utiliser selon le type
$tableToUse = ($type == 'credit') ? 'tblcreditcart' : 'tblcart';
$typeLabel = ($type == 'credit') ? 'à terme' : 'comptant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Gestion des stocks | Détails de la facture</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .payment-info {
      background: #f9f9f9;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    .progress {
      height: 20px;
      margin-bottom: 10px;
      overflow: hidden;
      background-color: #f5f5f5;
      border-radius: 4px;
      box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
    }
    .progress .bar {
      float: left;
      width: 0;
      height: 100%;
      font-size: 12px;
      line-height: 20px;
      color: #fff;
      text-align: center;
      background-color: #51a351;
      box-shadow: inset 0 -1px 0 rgba(0,0,0,.15);
      transition: width .6s ease;
    }
  </style>
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
      <a href="facture.php">Factures</a>
      <a href="#" class="current">Détails</a>
    </div>
    <h1>Détails de la facture <?php echo $typeLabel; ?> #<?php echo $billingId; ?></h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- =========== INFORMATIONS DE LA FACTURE =========== -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-file"></i></span>
            <h5>Informations de la facture <?php echo $typeLabel; ?></h5>
            <div class="buttons">
              <a href="facture.php" class="btn btn-primary">
                <i class="icon-arrow-left"></i> Retour aux factures
              </a>
              <a href="javascript:window.print()" class="btn btn-success">
                <i class="icon-print"></i> Imprimer
              </a>
            </div>
          </div>
          <div class="widget-content">
            <?php
            // Récupérer les informations générales de la facture
            $sqlFactureInfo = "
              SELECT 
                CartDate,
                BillingId,
                SUM(Price * ProductQty) as Total,
                COUNT(*) as ItemCount
              FROM $tableToUse 
              WHERE BillingId='$billingId' 
              GROUP BY BillingId, CartDate
            ";
            $factureInfoQuery = mysqli_query($con, $sqlFactureInfo);
            $factureInfo = mysqli_fetch_assoc($factureInfoQuery);
            
            if (!$factureInfo) {
              echo "<div class='alert alert-error'>Facture introuvable!</div>";
            } else {
              // Pour les factures à terme, récupérer les informations client et les montants payés/dus
              $custInfo = null;
              
              if($type == 'credit') {
                $custQuery = mysqli_query($con, "SELECT * FROM tblcustumer WHERE BillingNumber='$billingId'");
                if(mysqli_num_rows($custQuery) > 0) {
                  $custInfo = mysqli_fetch_assoc($custQuery);
                }
              }
            ?>
            
            <div class="row-fluid">
              <div class="span6">
                <table class="table table-bordered">
                  <tr>
                    <th>Numéro de facture:</th>
                    <td><?php echo $factureInfo['BillingId']; ?></td>
                  </tr>
                  <tr>
                    <th>Type:</th>
                    <td><?php echo ucfirst($typeLabel); ?></td>
                  </tr>
                  <tr>
                    <th>Date:</th>
                    <td><?php echo $factureInfo['CartDate']; ?></td>
                  </tr>
                  <tr>
                    <th>Nombre d'articles:</th>
                    <td><?php echo $factureInfo['ItemCount']; ?></td>
                  </tr>
                </table>
              </div>
              <div class="span6">
                <table class="table table-bordered">
                  <tr>
                    <th>Total:</th>
                    <td><strong><?php echo number_format($factureInfo['Total'], 2); ?> €</strong></td>
                  </tr>
                  <?php if($type == 'credit' && !empty($custInfo)): ?>
                  <tr>
                    <th>Montant payé:</th>
                    <td><?php echo number_format($custInfo['Paid'], 2); ?> €</td>
                  </tr>
                  <tr>
                    <th>Reste à payer:</th>
                    <td><?php echo number_format($custInfo['Dues'], 2); ?> €</td>
                  </tr>
                  <tr>
                    <th>Statut:</th>
                    <td>
                      <?php if($custInfo['Paid'] == $custInfo['FinalAmount'] || $custInfo['Dues'] <= 0): ?>
                        <span class="label label-success">Payé</span>
                      <?php else: ?>
                        <span class="label label-important">En attente</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endif; ?>
                </table>
              </div>
            </div>
            
            <?php if($type == 'credit' && !empty($custInfo)): ?>
            <!-- Informations de paiement pour les factures à terme -->
            <div class="row-fluid">
              <div class="span12">
                <div class="payment-info">
                  <h4>Suivi des paiements</h4>
                  
                  <?php 
                  $progressPercent = ($custInfo['FinalAmount'] > 0) ? ($custInfo['Paid'] / $custInfo['FinalAmount']) * 100 : 0;
                  ?>
                  <div class="progress">
                    <div class="bar" style="width: <?php echo $progressPercent; ?>%;">
                      <?php echo number_format($progressPercent, 1); ?>%
                    </div>
                  </div>
                  
                  <div class="row-fluid">
                    <div class="span4">
                      <p><strong>Total à payer:</strong> <?php echo number_format($custInfo['FinalAmount'], 2); ?> €</p>
                    </div>
                    <div class="span4">
                      <p><strong>Déjà payé:</strong> <?php echo number_format($custInfo['Paid'], 2); ?> €</p>
                    </div>
                    <div class="span4">
                      <p><strong>Reste à payer:</strong> <?php echo number_format($custInfo['Dues'], 2); ?> €</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
            
            <h4>Articles de la facture</h4>
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Produit</th>
                  <th>Quantité</th>
                  <th>Prix unitaire</th>
                  <th>Sous-total</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Récupérer les articles de la facture
                $sqlItems = "
                  SELECT 
                    c.ID,
                    c.ProductQty,
                    c.Price,
                    p.ProductName
                  FROM $tableToUse c
                  LEFT JOIN tblproducts p ON p.ID = c.ProductId
                  WHERE c.BillingId='$billingId'
                  ORDER BY c.ID ASC
                ";
                $itemsQuery = mysqli_query($con, $sqlItems);
                $cnt = 1;
                while ($item = mysqli_fetch_assoc($itemsQuery)) {
                  $subtotal = $item['Price'] * $item['ProductQty'];
                ?>
                <tr>
                  <td><?php echo $cnt; ?></td>
                  <td><?php echo $item['ProductName']; ?></td>
                  <td><?php echo $item['ProductQty']; ?></td>
                  <td><?php echo number_format($item['Price'], 2); ?> €</td>
                  <td><?php echo number_format($subtotal, 2); ?> €</td>
                </tr>
                <?php
                  $cnt++;
                }
                ?>
                <tr>
                  <td colspan="4" align="right"><strong>Total:</strong></td>
                  <td><strong><?php echo number_format($factureInfo['Total'], 2); ?> €</strong></td>
                </tr>
              </tbody>
            </table>
            
            <?php if($type == 'credit' && !empty($custInfo)): ?>
            <!-- Informations client -->
            <div class="row-fluid">
              <div class="span12">
                <h4>Informations client</h4>
                <table class="table table-bordered">
                  <tr>
                    <th width="20%">Nom du client:</th>
                    <td><?php echo $custInfo['CustomerName']; ?></td>
                    <th width="20%">Téléphone:</th>
                    <td><?php echo $custInfo['MobileNumber']; ?></td>
                  </tr>
                  <tr>
                    <th>Mode de paiement:</th>
                    <td><?php echo $custInfo['ModeofPayment']; ?></td>
                    <th>Date de facturation:</th>
                    <td><?php echo $custInfo['BillingDate']; ?></td>
                  </tr>
                </table>
              </div>
            </div>
            <?php endif; ?>
            
            <div class="text-right">
              <a href="facture.php?delete_id=<?php echo $billingId; ?>&type=<?php echo $type; ?>" 
                class="btn btn-danger" 
                onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette facture? Cette action mettra à jour le stock des produits.')">
                <i class="icon-trash"></i> Supprimer cette facture
              </a>
            </div>
            <?php } ?>
          </div><!-- widget-content -->
        </div><!-- widget-box -->
      </div>
    </div><!-- row-fluid -->

  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>

<!-- Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/matrix.js"></script>
</body>
</html>