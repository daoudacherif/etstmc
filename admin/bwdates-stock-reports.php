<?php
session_start();
error_reporting(E_ALL);
include_once 'includes/dbconnection.php';

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// Initialiser les variables
$fdate = filter_input(INPUT_POST, 'fromdate', FILTER_SANITIZE_STRING);
$tdate = filter_input(INPUT_POST, 'todate', FILTER_SANITIZE_STRING);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Système de Gestion des Inventaires | Rapport de Stock</title>
    <?php include_once 'includes/cs.php'; ?>
    <?php include_once 'includes/responsive.php'; ?>
    <style>
      /* Styles normaux pour l'interface */
      .widget-content {
          padding: 15px;
      }
      
      /* Styles spécifiques pour l'impression - SEUL LE TABLEAU EST VISIBLE */
      @media print {
          /* Cacher absolument tout */
          body * {
              visibility: hidden;
              display: none;
          }
          
          /* Afficher seulement le tableau et son contenu */
          .data-table,
          .data-table * {
              visibility: visible;
              display: table-row;
          }
          
          .data-table {
              position: absolute;
              left: 0;
              top: 0;
              width: 100%;
              display: table;
              border-collapse: collapse;
          }
          
          /* Styles pour que le tableau soit bien formaté */
          .data-table th, 
          .data-table td {
              padding: 8px;
              border: 1px solid #000;
              text-align: left;
          }
          
          .data-table th {
              font-weight: bold;
              background-color: #f5f5f5 !important;
          }
          
          .data-table thead {
              display: table-header-group;
          }
          
          .data-table tbody {
              display: table-row-group;
          }
          
          .data-table tr {
              page-break-inside: avoid;
              display: table-row;
          }
          
          /* Exceptions pour certains éléments spécifiques */
          .text-danger {
              color: #d9534f !important;
          }
          
          .label-success {
              background-color: #dff0d8 !important;
              border: 1px solid #3c763d !important;
          }
          
          .label-important {
              background-color: #f2dede !important;
              border: 1px solid #a94442 !important;
          }
      }
    </style>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<?php include_once 'includes/sidebar.php'; ?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" title="Accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a href="stock-report.php" class="current">Rapport de Stock</a>
        </div>
        <h1>Rapport de Stock</h1>
    </div>
    
    <div class="container-fluid">
        <hr />
        
        <!-- Formulaire de sélection des dates -->
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-calendar"></i></span>
                        <h5>Sélectionner la période du rapport</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <form method="post" class="form-horizontal" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <div class="control-group">
                                <label class="control-label">De Date :</label>
                                <div class="controls">
                                    <input type="date" class="span11" name="fromdate" id="fromdate" value="<?php echo $fdate; ?>" required='true' />
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">À Date :</label>
                                <div class="controls">
                                    <input type="date" class="span11" name="todate" id="todate" value="<?php echo $tdate; ?>" required='true' />
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success" name="submit"><i class="icon-search"></i> Générer le Rapport</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($fdate && $tdate): ?>
            <!-- Tableau des résultats -->
            <div class="row-fluid">
                <div class="span12">
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5>
                                Rapport d'inventaire du <?= htmlspecialchars($fdate) ?> au <?= htmlspecialchars($tdate) ?>
                            </h5>
                            <div class="buttons">
                                <button onclick="window.print()" class="btn btn-primary btn-mini"><i class="icon-print"></i> Imprimer</button>
                                <a href="export-stock.php?from=<?= urlencode($fdate) ?>&to=<?= urlencode($tdate) ?>" class="btn btn-info btn-mini"><i class="icon-download"></i> Exporter</a>
                            </div>
                        </div>
                        <div class="widget-content">
                            <!-- Tableau optimisé pour l'impression -->
                            <table class="table table-bordered data-table">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Nom du Produit</th>
                                        <th>Catégorie</th>
                                        <th>Marque</th>
                                        <th>Modèle</th>
                                        <th>Stock Initial</th>
                                        <th>Vendus</th>
                                        <th>Retournés</th>
                                        <th>Stock Restant</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                // Préparer la requête pour éviter injection SQL
                                $stmt = $con->prepare("
                                    SELECT 
                                        p.ID, 
                                        p.ProductName, 
                                        COALESCE(c.CategoryName, 'N/A') AS CategoryName, 
                                        p.BrandName, 
                                        p.ModelNumber, 
                                        p.Stock AS initial_stock, 
                                        COALESCE(SUM(cart.ProductQty), 0) AS sold_qty,
                                        COALESCE(
                                            (SELECT SUM(Quantity) FROM tblreturns WHERE ProductID = p.ID AND 
                                            DATE(ReturnDate) BETWEEN ? AND ?),
                                            0
                                        ) AS returned_qty,
                                        p.Status
                                    FROM tblproducts p
                                    LEFT JOIN tblcategory c ON c.ID = p.CatID
                                    LEFT JOIN tblcart cart ON cart.ProductId = p.ID AND cart.IsCheckOut = 1
                                    WHERE DATE(p.CreationDate) BETWEEN ? AND ?
                                    GROUP BY p.ID
                                    ORDER BY p.ID DESC
                                ");
                                
                                $stmt->bind_param('ssss', $fdate, $tdate, $fdate, $tdate);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $cnt = 1;

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $initial = (int)$row['initial_stock'];
                                        $sold = (int)$row['sold_qty'];
                                        $returned = (int)$row['returned_qty'];
                                        $remain = $initial - $sold + $returned;
                                        $remain = max(0, $remain);
                                        ?>
                                        <tr>
                                            <td><?= $cnt ?></td>
                                            <td><?= htmlspecialchars($row['ProductName']) ?></td>
                                            <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                                            <td><?= htmlspecialchars($row['BrandName']) ?></td>
                                            <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                                            <td><?= $initial ?></td>
                                            <td><?= $sold ?></td>
                                            <td><?= $returned ?></td>
                                            <td class="<?= $remain === 0 ? 'text-danger' : '' ?>">
                                                <?= $remain === 0 ? 'Épuisé' : $remain ?>
                                            </td>
                                            <td>
                                                <?php if($row['Status'] == '1'): ?>
                                                    <span class="label label-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="label label-important">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php
                                        $cnt++;
                                    }
                                } else {
                                    echo '<tr><td colspan="10" class="text-center">Aucun enregistrement trouvé pour cette période.</td></tr>';
                                }
                                $stmt->close();
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row-fluid">
                <div class="span12">
                    <div class="alert alert-info">
                        <button class="close" data-dismiss="alert">×</button>
                        <strong>Info!</strong> Veuillez sélectionner les dates de début et de fin pour générer le rapport.
                    </div>
                    
                    <!-- Aperçu des produits récents -->
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5>Aperçu des Produits Récents</h5>
                        </div>
                        <div class="widget-content">
                            <table class="table table-bordered data-table">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Nom du Produit</th>
                                        <th>Catégorie</th>
                                        <th>Marque</th>
                                        <th>Modèle</th>
                                        <th>Stock Initial</th>
                                        <th>Vendus</th>
                                        <th>Retournés</th>
                                        <th>Stock Restant</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "
                                        SELECT 
                                            p.ID AS pid,
                                            p.ProductName,
                                            COALESCE(c.CategoryName, 'N/A') AS CategoryName,
                                            p.BrandName,
                                            p.ModelNumber,
                                            p.Stock AS initial_stock,
                                            COALESCE(SUM(cart.ProductQty), 0) AS sold_qty,
                                            COALESCE(
                                                (SELECT SUM(Quantity) FROM tblreturns WHERE ProductID = p.ID),
                                                0
                                            ) AS returned_qty,
                                            p.Status
                                        FROM tblproducts p
                                        LEFT JOIN tblcategory c ON c.ID = p.CatID
                                        LEFT JOIN tblcart cart ON cart.ProductId = p.ID AND cart.IsCheckOut = 1
                                        GROUP BY p.ID
                                        ORDER BY p.CreationDate DESC 
                                        LIMIT 10
                                    ";
                                    $ret = mysqli_query($con, $sql) or die('Erreur SQL : ' . mysqli_error($con));
                                    
                                    if (mysqli_num_rows($ret) > 0) {
                                        $cnt = 1;
                                        while ($row = mysqli_fetch_assoc($ret)) {
                                            $initial = (int)$row['initial_stock'];
                                            $sold = (int)$row['sold_qty'];
                                            $returned = (int)$row['returned_qty'];
                                            $remain = $initial - $sold + $returned;
                                            $remain = max(0, $remain);
                                            ?>
                                            <tr>
                                                <td><?= $cnt ?></td>
                                                <td><?= htmlspecialchars($row['ProductName']) ?></td>
                                                <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                                                <td><?= htmlspecialchars($row['BrandName']) ?></td>
                                                <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                                                <td><?= $initial ?></td>
                                                <td><?= $sold ?></td>
                                                <td><?= $returned ?></td>
                                                <td class="<?= $remain === 0 ? 'text-danger' : '' ?>">
                                                    <?= $remain === 0 ? 'Épuisé' : $remain ?>
                                                </td>
                                                <td>
                                                    <?php if($row['Status'] == '1'): ?>
                                                        <span class="label label-success">Actif</span>
                                                    <?php else: ?>
                                                        <span class="label label-important">Inactif</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php
                                            $cnt++;
                                        }
                                    } else {
                                        echo '<tr><td colspan="10" class="text-center">Aucun Article trouvé</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

<!-- Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.tables.js"></script>
<script>
    // Validation JS: assure fromdate <= todate
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        form && form.addEventListener('submit', function(e) {
            const from = new Date(document.getElementById('fromdate').value);
            const to = new Date(document.getElementById('todate').value);
            if (from > to) {
                alert('La date de début ne peut pas être après la date de fin.');
                e.preventDefault();
            }
        });
    });
</script>

</body>
</html>