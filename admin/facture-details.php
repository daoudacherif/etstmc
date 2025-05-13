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
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header('location:facture.php');
  exit;
}

$billingId = intval($_GET['id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Gestion des stocks | Détails de la facture</title>
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
      <a href="facture.php">Factures</a>
      <a href="#" class="current">Détails</a>
    </div>
    <h1>Détails de la facture #<?php echo $billingId; ?></h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- =========== INFORMATIONS DE LA FACTURE =========== -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-file"></i></span>
            <h5>Informations de la facture</h5>
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
              FROM tblcart 
              WHERE BillingId='$billingId' 
              GROUP BY BillingId, CartDate
            ";
            $factureInfoQuery = mysqli_query($con, $sqlFactureInfo);
            $factureInfo = mysqli_fetch_assoc($factureInfoQuery);
            
            if (!$factureInfo) {
              echo "<div class='alert alert-error'>Facture introuvable!</div>";
            } else {
            ?>
            <div class="row-fluid">
              <div class="span6">
                <table class="table table-bordered">
                  <tr>
                    <th>Numéro de facture:</th>
                    <td><?php echo $factureInfo['BillingId']; ?></td>
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
                </table>
              </div>
            </div>
            
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
                  FROM tblcart c
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
            <div class="text-right">
              <a href="facture.php?delete_id=<?php echo $billingId; ?>" 
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