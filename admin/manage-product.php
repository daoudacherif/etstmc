<?php 
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// Vérifier si un ID de produit est fourni
if (!isset($_GET['pid']) || empty($_GET['pid'])) {
    header('location:manage-inventory.php');
    exit;
}

$productId = intval($_GET['pid']);

// Récupérer les informations du produit
$productQuery = mysqli_query($con, "
    SELECT p.*, c.CategoryName 
    FROM tblproducts p
    LEFT JOIN tblcategory c ON c.ID = p.CatID
    WHERE p.ID = $productId
");

if (mysqli_num_rows($productQuery) == 0) {
    echo "<script>alert('Produit non trouvé'); window.location.href='manage-inventory.php';</script>";
    exit;
}

$product = mysqli_fetch_assoc($productQuery);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Historique du produit - <?= htmlspecialchars($product['ProductName']) ?></title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .movement-in { color: #5cb85c; }
    .movement-out { color: #d9534f; }
    .timeline-badge { 
      width: 50px; 
      height: 50px; 
      line-height: 50px; 
      font-size: 1.4em; 
      text-align: center; 
      position: absolute; 
      top: 16px; 
      left: 50%; 
      margin-left: -25px; 
      background-color: #999999; 
      z-index: 100; 
      border-top-right-radius: 50%; 
      border-top-left-radius: 50%; 
      border-bottom-right-radius: 50%; 
      border-bottom-left-radius: 50%; 
    }
    .timeline-badge.success { background-color: #5cb85c; }
    .timeline-badge.danger { background-color: #d9534f; }
    .timeline-badge.warning { background-color: #f0ad4e; }
    .product-info {
      background-color: #f8f8f8;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <a href="manage-inventory.php">Gestion des Articles</a>
      <strong>Historique</strong>
    </div>
    <h1>Historique du produit</h1>
  </div>
  
  <div class="container-fluid">
    <hr>
    
    <!-- Informations du produit -->
    <div class="product-info">
      <h3><?= htmlspecialchars($product['ProductName']) ?></h3>
      <div class="row-fluid">
        <div class="span3"><strong>Catégorie:</strong> <?= htmlspecialchars($product['CategoryName'] ?? 'N/A') ?></div>
        <div class="span3"><strong>Marque:</strong> <?= htmlspecialchars($product['BrandName']) ?></div>
        <div class="span3"><strong>Modèle:</strong> <?= htmlspecialchars($product['ModelNumber']) ?></div>
        <div class="span3"><strong>Prix:</strong> <?= number_format($product['Price'], 2) ?> GNF</div>
      </div>
    </div>
    
    <div class="row-fluid">
      <div class="span12">
        <!-- Statistiques -->
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-signal"></i></span>
            <h5>Résumé des mouvements</h5>
          </div>
          <div class="widget-content">
            <?php
            // Calculer les statistiques
            $stats = mysqli_query($con, "
                SELECT 
                    p.Stock as initial_stock,
                    COALESCE(SUM(c.ProductQty), 0) as total_sold,
                    COALESCE((SELECT SUM(Quantity) FROM tblreturns WHERE ProductID = $productId), 0) as total_returned
                FROM tblproducts p
                LEFT JOIN tblcart c ON c.ProductId = p.ID AND c.IsCheckOut = 1
                WHERE p.ID = $productId
                GROUP BY p.ID
            ");
            $stat = mysqli_fetch_assoc($stats);
            $remaining = $stat['initial_stock'] - $stat['total_sold'] + $stat['total_returned'];
            ?>
            <div class="row-fluid">
              <div class="span3">
                <div class="well well-small">
                  <h4>Stock Initial</h4>
                  <h2><?= $stat['initial_stock'] ?></h2>
                </div>
              </div>
              <div class="span3">
                <div class="well well-small">
                  <h4>Total Vendu</h4>
                  <h2 class="movement-out"><?= $stat['total_sold'] ?></h2>
                </div>
              </div>
              <div class="span3">
                <div class="well well-small">
                  <h4>Total Retourné</h4>
                  <h2 class="movement-in"><?= $stat['total_returned'] ?></h2>
                </div>
              </div>
              <div class="span3">
                <div class="well well-small">
                  <h4>Stock Restant</h4>
                  <h2 class="<?= $remaining <= 0 ? 'movement-out' : ($remaining < 5 ? 'text-warning' : 'movement-in') ?>">
                    <?= $remaining ?>
                  </h2>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Historique des mouvements -->
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-time"></i></span>
            <h5>Historique détaillé des mouvements</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Type</th>
                  <th>Quantité</th>
                  <th>Client/Référence</th>
                  <th>Prix unitaire</th>
                  <th>Total</th>
                  <th>Stock après mouvement</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Stock initial
                $currentStock = $stat['initial_stock'];
                echo "<tr class='info'>";
                echo "<td>-</td>";
                echo "<td><span class='label label-info'>Stock Initial</span></td>";
                echo "<td class='movement-in'>+" . $stat['initial_stock'] . "</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "<td>-</td>";
                echo "<td><strong>" . $currentStock . "</strong></td>";
                echo "</tr>";
                
                // Récupérer tous les mouvements (ventes et retours) triés par date
                $movements = mysqli_query($con, "
                    SELECT 
                        'sale' as type,
                        c.BillingDate as date,
                        c.ProductQty as quantity,
                        c.Price as unit_price,
                        cu.CustomerName as reference,
                        c.BillingId
                    FROM tblcart c
                    LEFT JOIN tblcustomer cu ON cu.BillingNumber = c.BillingId
                    WHERE c.ProductId = $productId AND c.IsCheckOut = 1
                    
                    UNION ALL
                    
                    SELECT 
                        'return' as type,
                        r.ReturnDate as date,
                        r.Quantity as quantity,
                        r.RefundAmount / r.Quantity as unit_price,
                        CONCAT('Retour #', r.ID, ' - ', r.ReturnReason) as reference,
                        r.BillingNumber as BillingId
                    FROM tblreturns r
                    WHERE r.ProductID = $productId
                    
                    ORDER BY date DESC
                ");
                
                while ($movement = mysqli_fetch_assoc($movements)) {
                    if ($movement['type'] == 'sale') {
                        $currentStock -= $movement['quantity'];
                        $moveClass = 'movement-out';
                        $moveSign = '-';
                        $labelClass = 'label-important';
                        $labelText = 'Vente';
                    } else {
                        $currentStock += $movement['quantity'];
                        $moveClass = 'movement-in';
                        $moveSign = '+';
                        $labelClass = 'label-success';
                        $labelText = 'Retour';
                    }
                    
                    echo "<tr>";
                    echo "<td>" . date('d/m/Y H:i', strtotime($movement['date'])) . "</td>";
                    echo "<td><span class='label $labelClass'>$labelText</span></td>";
                    echo "<td class='$moveClass'><strong>$moveSign" . $movement['quantity'] . "</strong></td>";
                    echo "<td>" . htmlspecialchars($movement['reference']) . "</td>";
                    echo "<td>" . number_format($movement['unit_price'], 2) . " GNF</td>";
                    echo "<td>" . number_format($movement['quantity'] * $movement['unit_price'], 2) . " GNF</td>";
                    echo "<td><strong>" . $currentStock . "</strong></td>";
                    echo "</tr>";
                }
                
                if (mysqli_num_rows($movements) == 0) {
                    echo "<tr><td colspan='7' class='text-center'>Aucun mouvement enregistré</td></tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
        
        <div class="form-actions">
          <a href="manage-inventory.php" class="btn btn-primary">
            <i class="icon-arrow-left"></i> Retour à la liste
          </a>
          <a href="editproducts.php?editid=<?= $productId ?>" class="btn btn-info">
            <i class="icon-edit"></i> Modifier le produit
          </a>
        </div>
        
      </div>
    </div>
  </div>
</div>

<?php include_once('includes/footer.php'); ?>

<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/matrix.js"></script>
</body>
</html>