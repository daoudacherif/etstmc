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
        /* Style pour l'impression - uniquement le tableau */
        @media print {
            /* Cacher tous les éléments */
            body * {
                display: none !important;
            }
            
            /* Afficher uniquement le contenu principal et le tableau */
            body, 
            #content, 
            .container-fluid, 
            .row-fluid, 
            .widget-box, 
            .widget-content {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                background: white !important;
            }
            
            /* Styles pour le tableau spécifiquement */
            .data-table {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 0 !important;
                font-size: 12px !important;
            }
            
            .data-table thead {
                display: table-header-group !important;
            }
            
            .data-table tbody {
                display: table-row-group !important;
            }
            
            .data-table tr {
                display: table-row !important;
                page-break-inside: avoid !important;
            }
            
            .data-table th, 
            .data-table td {
                display: table-cell !important;
                border: 1px solid #000 !important;
                padding: 5px !important;
                text-align: left !important;
            }
            
            .data-table th {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
            }
            
            /* Cacher tout le reste */
            .no-print,
            .print-header,
            .print-footer,
            #content-header,
            .widget-title,
            .buttons {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<!-- Éléments qui seront cachés à l'impression -->
<div class="no-print">
    <?php include_once 'includes/header.php'; ?>
    <?php include_once 'includes/sidebar.php'; ?>
</div>

<div id="content">
    <!-- En-tête de contenu - caché à l'impression -->
    <div id="content-header" class="no-print">
        <div id="breadcrumb">
            <a href="dashboard.php" title="Accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a href="stock-report.php" class="current">Rapport de Stock</a>
        </div>
        <h1>Rapport de Stock</h1>
    </div>
    
    <div class="container-fluid">
        <hr class="no-print" />
        
        <!-- Formulaire de sélection des dates - caché à l'impression -->
        <div class="row-fluid no-print">
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
                        <div class="widget-title no-print">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5>
                                Rapport d'inventaire du <?= htmlspecialchars($fdate) ?> au <?= htmlspecialchars($tdate) ?>
                            </h5>
                            <div class="buttons no-print">
                                <button onclick="window.print()" class="btn btn-primary btn-mini"><i class="icon-print"></i> Imprimer Tableau</button>
                                <a href="export-stock.php?from=<?= urlencode($fdate) ?>&to=<?= urlencode($tdate) ?>" class="btn btn-info btn-mini"><i class="icon-download"></i> Exporter</a>
                            </div>
                        </div>
                        
                        <div class="widget-content">
                            <!-- Seul le tableau sera visible à l'impression -->
                            <table class="table table-bordered data-table">
                                <thead>
                                    <tr>
                                        <th width="5%">N°</th>
                                        <th width="20%">Nom du Produit</th>
                                        <th width="15%">Catégorie</th>
                                        <th width="15%">Marque</th>
                                        <th width="10%">Modèle</th>
                                        <th width="8%">Stock Initial</th>
                                        <th width="7%">Vendus</th>
                                        <th width="7%">Retournés</th>
                                        <th width="8%">Stock Restant</th>
                                        <th width="5%">Statut</th>
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
                                            <td><?= $remain === 0 ? 'Épuisé' : $remain ?></td>
                                            <td><?= $row['Status'] == '1' ? 'Actif' : 'Inactif' ?></td>
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
            <div class="row-fluid no-print">
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
                                                <td><?= $remain === 0 ? 'Épuisé' : $remain ?></td>
                                                <td><?= $row['Status'] == '1' ? 'Actif' : 'Inactif' ?></td>
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

<!-- Pied de page - caché à l'impression -->
<div class="no-print">
    <?php include_once 'includes/footer.php'; ?>
</div>

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