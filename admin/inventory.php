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
      /* Masquer tous les éléments sauf le tableau */
      body * {
        visibility: hidden;
      }
      
      /* Rendre visible uniquement le contenu à imprimer */
      .print-container,
      .print-container * {
        visibility: visible;
      }
      
      /* Positionnement du contenu à imprimer */
      .print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
      }
      
      /* Masquer les colonnes Actions */
      .no-print {
        display: none !important;
      }
      
      /* Styles du tableau pour l'impression */
      table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 11px !important;
      }
      
      table th,
      table td {
        border: 1px solid #000 !important;
        padding: 5px !important;
        text-align: left !important;
      }
      
      table th {
        background-color: #f0f0f0 !important;
        font-weight: bold !important;
        text-align: center !important;
      }
      
      /* En-tête d'impression */
      .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #000;
      }
      
      .print-header h2 {
        margin: 0 0 10px 0;
        font-size: 20px;
      }
      
      .print-header p {
        margin: 5px 0;
        font-size: 12px;
      }
      
      /* Couleurs pour l'impression */
      .stock-critical { 
        color: #000 !important; 
        font-weight: bold !important;
        text-decoration: underline !important;
      }
      
      .stock-low { 
        color: #000 !important; 
        font-weight: bold !important;
        font-style: italic !important;
      }
      
      .stock-good { 
        color: #000 !important; 
      }
      
      /* Supprimer les marges du body */
      body {
        margin: 0 !important;
        padding: 10px !important;
      }
      
      /* Pagination DataTables */
      .dataTables_paginate,
      .dataTables_info,
      .dataTables_length,
      .dataTables_filter,
      .dataTables_wrapper .row:first-child,
      .dataTables_wrapper .row:last-child {
        display: none !important;
      }
    }
    
    /* Masquer l'en-tête d'impression en mode normal */
    .print-header {
      display: none;
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
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <strong>Voir l'Inventaire des Articles</strong>
    </div>
    <h1>Inventaire des Articles</h1>
  </div>
  
  <div class="container-fluid">
    <hr>
    
    <!-- Bouton d'impression -->
    <div class="row-fluid">
      <div class="span12">
        <button onclick="printInventory()" class="btn btn-print">
          <i class="icon-print"></i> Imprimer l'inventaire
        </button>
      </div>
    </div>
    
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Inventaire des Articles</h5>
          </div>
          <div class="widget-content nopadding">
            <!-- Container pour l'impression -->
            <div class="print-container">
              <!-- En-tête pour l'impression -->
              <div class="print-header">
                <h2>INVENTAIRE DES ARTICLES</h2>
                <p>Date d'impression : <?= date('d/m/Y H:i') ?></p>
                <p>Nombre total d'articles : <span id="total-articles">0</span></p>
              </div>
              
              <table class="table table-bordered data-table" id="inventory-table">
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
                  // Requête pour récupérer l'inventaire
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

                  $totalProducts = 0;
                  if (mysqli_num_rows($ret) > 0) {
                    $cnt = 1;
                    while ($row = mysqli_fetch_assoc($ret)) {
                      $totalProducts++;
                      // Le stock actuel est déjà dans la base de données
                      $current_stock = intval($row['current_stock']);
                      $sold = intval($row['sold_qty']);
                      $returned = intval($row['returned_qty']);
                      
                      // Calcul du stock initial = stock actuel + vendu - retourné
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
            </div><!-- print-container -->
          </div><!-- widget-content -->
        </div><!-- widget-box -->
      </div><!-- span12 -->
    </div><!-- row-fluid -->
    
    <!-- Résumé pour affichage écran uniquement -->
    <div class="row-fluid" style="margin-top: 20px;">
      <div class="span12">
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
        <div class="alert alert-info">
          <h4>Résumé de l'inventaire</h4>
          <p><strong>Total produits actifs :</strong> <?= $stats['total_products'] ?></p>
          <p><strong>Produits en rupture :</strong> <?= $stats['products_out_of_stock'] ?></p>
          <p><strong>Produits en stock faible :</strong> <?= $stats['products_low_stock'] ?></p>
          <p><strong>Total unités en stock :</strong> <?= $stats['total_stock_units'] ?></p>
        </div>
      </div>
    </div>
    
  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>

<!-- scripts pour DataTable -->
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
    // Mettre à jour le nombre total d'articles
    $('#total-articles').text('<?= $totalProducts ?>');
    
    // Initialisation DataTable
    var table = $('.data-table').DataTable({
        "pageLength": 50,
        "order": [[ 8, "asc" ]], // Trier par stock actuel croissant
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json"
        }
    });
    
    // Tooltip pour les boutons d'action
    $('.tip-top').tooltip({
        placement: 'top'
    });
});

// Fonction d'impression personnalisée
function printInventory() {
    // Sauvegarder la configuration actuelle du DataTable
    var table = $('.data-table').DataTable();
    var currentPage = table.page.info().page;
    var currentLength = table.page.info().length;
    
    // Afficher toutes les lignes avant l'impression
    table.page.len(-1).draw();
    
    // Attendre que le DataTable se redessine
    setTimeout(function() {
        // Lancer l'impression
        window.print();
        
        // Restaurer la pagination après un court délai
        setTimeout(function() {
            table.page.len(currentLength).draw();
            table.page(currentPage).draw('page');
        }, 100);
    }, 100);
}
</script>

</body>
</html>