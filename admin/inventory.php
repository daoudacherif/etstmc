<?php 
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Inventaire des Articles</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .stock-critical { color: #c62828; font-weight: bold; }
    .stock-low { color: #ef6c00; font-weight: bold; }
    .stock-good { color: #2e7d32; }
    
    /* Styles pour l'impression */
    @media print {
      .no-print {
        display: none !important;
      }
      .print-title {
        text-align: center;
        margin-bottom: 20px;
      }
      .table {
        font-size: 12px;
      }
      .table th,
      .table td {
        padding: 5px !important;
      }
      body {
        margin: 0;
        padding: 10px;
      }
      #content-header,
      #breadcrumb {
        display: none;
      }
    }
    
    .print-header {
      display: none;
    }
    
    @media print {
      .print-header {
        display: block;
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #000;
        padding-bottom: 10px;
      }
    }
    
    .action-buttons {
      white-space: nowrap;
    }
    
    .btn-print {
      background-color: #5bc0de;
      border-color: #46b8da;
      color: white;
      margin-bottom: 15px;
    }
    
    .btn-print:hover {
      background-color: #31b0d5;
      border-color: #269abc;
      color: white;
    }
  </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header" class="no-print">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <strong>Voir l'Inventaire des Articles</strong>
    </div>
    <h1>Inventaire des Articles</h1>
  </div>
  
  <!-- En-tête pour l'impression -->
  <div class="print-header">
    <h1>INVENTAIRE DES ARTICLES</h1>
    <p>Date d'impression : <?= date('d/m/Y H:i') ?></p>
  </div>
  
  <div class="container-fluid">
    <hr class="no-print">
    
    <!-- Bouton d'impression -->
    <div class="row-fluid no-print">
      <div class="span12">
        <button onclick="window.print()" class="btn btn-print">
          <i class="icon-print"></i> Imprimer l'inventaire
        </button>
      </div>
    </div>
    
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title no-print">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Inventaire des Articles</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom du Article</th>
                  <th>Catégorie</th>
                  <th>Marque</th>
                  <th>Modèle</th>
                  <th>Stock Initial</th>
                  <th>Vendus</th>
                  <th>Retournés</th>
                  <th>Stock Actuel</th>
                  <th>Statut</th>
                  <th class="no-print">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Requête pour récupérer l'inventaire (logique métier inchangée)
                $sql = "
                  SELECT 
                    p.ID            AS pid,
                    p.ProductName,
                    COALESCE(c.CategoryName, 'N/A') AS CategoryName,
                    p.BrandName,
                    p.ModelNumber,
                    p.Stock         AS current_stock,
                    COALESCE(SUM(cart.ProductQty), 0) AS sold_qty,
                    COALESCE(
                      (SELECT SUM(Quantity) FROM tblreturns WHERE ProductID = p.ID),
                      0
                    ) AS returned_qty,
                    p.Status
                  FROM tblproducts p
                  LEFT JOIN tblcategory c 
                    ON c.ID = p.CatID
                  LEFT JOIN tblcart cart 
                    ON cart.ProductId = p.ID 
                   AND cart.IsCheckOut = 1
                  GROUP BY p.ID
                  ORDER BY p.Stock ASC, p.ID DESC
                ";
                $ret = mysqli_query($con, $sql) 
                  or die('Erreur SQL : ' . mysqli_error($con));

                if (mysqli_num_rows($ret) > 0) {
                  $cnt = 1;
                  while ($row = mysqli_fetch_assoc($ret)) {
                    // Le stock actuel est déjà dans la base de données
                    $current_stock = intval($row['current_stock']);
                    $sold = intval($row['sold_qty']);
                    $returned = intval($row['returned_qty']);
                    
                    // Calcul du stock initial = stock actuel + vendu - retourné (logique inchangée)
                    $initial_stock = $current_stock + $sold - $returned;
                    
                    // Déterminer la classe CSS pour le niveau de stock
                    $stockClass = '';
                    if ($current_stock == 0) {
                        $stockClass = 'stock-critical';
                    } elseif ($current_stock <= 5) {
                        $stockClass = 'stock-low';
                    } else {
                        $stockClass = 'stock-good';
                    }
                    ?>
                    <tr>
                      <td><?= $cnt ?></td>
                      <td><?= htmlspecialchars($row['ProductName']) ?></td>
                      <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                      <td><?= htmlspecialchars($row['BrandName']) ?></td>
                      <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                      <td><?= $initial_stock ?></td>
                      <td><?= $sold ?></td>
                      <td><?= $returned ?></td>
                      <td class="<?= $stockClass ?>">
                        <?= $current_stock === 0 ? 'Épuisé' : $current_stock ?>
                      </td>
                      <td><?= $row['Status'] == 1 ? 'Actif' : 'Inactif' ?></td>
                      <td class="no-print">
                        <div class="action-buttons">
                          <a href="product-history.php?pid=<?= $row['pid'] ?>" 
                             class="btn btn-info btn-mini tip-top" 
                             title="Voir l'historique">
                            <i class="icon-time"></i> Historique
                          </a>
                        </div>
                      </td>
                    </tr>
                    <?php
                    $cnt++;
                  }
                } else {
                  echo '<tr><td colspan="11" class="text-center">Aucun Article trouvé</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div><!-- widget-content -->
        </div><!-- widget-box -->
      </div><!-- span12 -->
    </div><!-- row-fluid -->
    
    <!-- Résumé pour l'impression -->
    <div class="row-fluid" style="margin-top: 20px;">
      <div class="span12">
        <div class="print-summary">
          <?php
          // Statistiques globales
          $statsQuery = "
            SELECT 
              COUNT(*) as total_products,
              SUM(CASE WHEN Stock = 0 THEN 1 ELSE 0 END) as products_out_of_stock,
              SUM(CASE WHEN Stock <= 5 AND Stock > 0 THEN 1 ELSE 0 END) as products_low_stock,
              SUM(Stock) as total_stock_units
            FROM tblproducts 
            WHERE Status = 1
          ";
          $statsResult = mysqli_query($con, $statsQuery);
          $stats = mysqli_fetch_assoc($statsResult);
          ?>
          <div class="alert alert-info no-print">
            <h4>Résumé de l'inventaire</h4>
            <p><strong>Total produits actifs :</strong> <?= $stats['total_products'] ?></p>
            <p><strong>Produits en rupture :</strong> <?= $stats['products_out_of_stock'] ?></p>
            <p><strong>Produits en stock faible :</strong> <?= $stats['products_low_stock'] ?></p>
            <p><strong>Total unités en stock :</strong> <?= $stats['total_stock_units'] ?></p>
          </div>
          
          <!-- Version imprimable du résumé -->
          <div style="display: none;">
            <div class="print-summary-table">
              <h3>Résumé de l'inventaire</h3>
              <table style="width: 100%; margin-top: 20px; border: 1px solid #000;">
                <tr>
                  <td style="border: 1px solid #000; padding: 5px;"><strong>Total produits actifs</strong></td>
                  <td style="border: 1px solid #000; padding: 5px;"><?= $stats['total_products'] ?></td>
                </tr>
                <tr>
                  <td style="border: 1px solid #000; padding: 5px;"><strong>Produits en rupture</strong></td>
                  <td style="border: 1px solid #000; padding: 5px;"><?= $stats['products_out_of_stock'] ?></td>
                </tr>
                <tr>
                  <td style="border: 1px solid #000; padding: 5px;"><strong>Produits en stock faible</strong></td>
                  <td style="border: 1px solid #000; padding: 5px;"><?= $stats['products_low_stock'] ?></td>
                </tr>
                <tr>
                  <td style="border: 1px solid #000; padding: 5px;"><strong>Total unités en stock</strong></td>
                  <td style="border: 1px solid #000; padding: 5px;"><?= $stats['total_stock_units'] ?></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    
  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>

<!-- scripts pour DataTable si nécessaire -->
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
    // Vérifier si DataTable existe déjà et le détruire si nécessaire
    if ($.fn.DataTable.isDataTable('.data-table')) {
        $('.data-table').DataTable().destroy();
    }
    
    // Initialisation DataTable avec configuration pour l'impression
    $('.data-table').dataTable({
        "destroy": true, // Permet de réinitialiser automatiquement
        "pageLength": 50,
        "order": [[ 8, "asc" ]], // Trier par stock actuel croissant
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json"
        }
    });
    
    // Gestion de l'impression
    window.addEventListener('beforeprint', function() {
        // Afficher le résumé dans la version imprimée
        $('.print-summary-table').parent().show();
        
        // Masquer la pagination DataTable
        $('.dataTables_wrapper .dataTables_paginate').hide();
        $('.dataTables_wrapper .dataTables_info').hide();
        $('.dataTables_wrapper .dataTables_length').hide();
        $('.dataTables_wrapper .dataTables_filter').hide();
        
        // Afficher tous les éléments du tableau
        $('.data-table').dataTable().fnSettings()._iDisplayLength = -1;
        $('.data-table').dataTable().fnDraw();
    });
    
    window.addEventListener('afterprint', function() {
        // Restaurer l'affichage normal
        $('.print-summary-table').parent().hide();
        $('.dataTables_wrapper .dataTables_paginate').show();
        $('.dataTables_wrapper .dataTables_info').show();
        $('.dataTables_wrapper .dataTables_length').show();
        $('.dataTables_wrapper .dataTables_filter').show();
        
        // Restaurer la pagination
        $('.data-table').dataTable().fnSettings()._iDisplayLength = 50;
        $('.data-table').dataTable().fnDraw();
    });
});

// Fonction d'impression personnalisée
function printInventory() {
    window.print();
}

// Tooltip pour les boutons d'action
$(document).ready(function() {
    $('.tip-top').tooltip({
        placement: 'top'
    });
});
</script>

</body>
</html>