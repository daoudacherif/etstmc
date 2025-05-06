<?php
session_start();
error_reporting(E_ALL);
include_once 'includes/dbconnection.php';

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// Initialiser les variables
$fdate = filter_input(INPUT_POST, 'fromdate', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-30 days'));
$tdate = filter_input(INPUT_POST, 'todate', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
$csrf_valid = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

// Validation des dates
if (!empty($fdate) && !empty($tdate)) {
    if (strtotime($fdate) > strtotime($tdate)) {
        $error_msg = "La date de début ne peut pas être postérieure à la date de fin";
        $fdate = date('Y-m-d', strtotime('-30 days'));
        $tdate = date('Y-m-d');
    }
}

// Définir constantes pour réutilisation
define('STATUS_ACTIVE', '1');
define('DEFAULT_LIMIT', 10);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Système de Gestion des Inventaires | Rapport de Stock</title>
    <?php include_once 'includes/cs.php'; ?>
    <?php include_once 'includes/responsive.php'; ?>
    <style>
        /* Améliorations visuelles pour l'interface */
        .widget-box {
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            transition: all 0.3s cubic-bezier(.25,.8,.25,1);
            margin-bottom: 20px;
        }
        
        .widget-box:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.16), 0 4px 8px rgba(0,0,0,0.23);
        }
        
        .buttons .btn {
            margin-left: 5px;
        }
        
        .data-table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        
        .stock-warning {
            color: #ff7043;
            font-weight: bold;
        }
        
        .stock-danger {
            color: #e53935;
            font-weight: bold;
        }
        
        .stock-ok {
            color: #43a047;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .data-table {
                display: block;
                width: 100%;
                overflow-x: auto;
            }
            
            .form-actions {
                text-align: center;
            }
            
            .widget-title h5 {
                font-size: 14px;
            }
        }
        
        /* Style pour l'impression - tableau uniquement */
        @media print {
            /* Cacher tout par défaut */
            body * {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Réinitialiser la hauteur et la largeur */
            html, body {
                height: auto !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Afficher uniquement le tableau et ses composants */
            .widget-content,
            .data-table,
            .data-table thead,
            .data-table tbody,
            .data-table tr,
            .data-table th,
            .data-table td {
                display: block !important;
                visibility: visible !important;
            }
            
            .data-table {
                display: table !important;
                width: 100% !important;
                border-collapse: collapse !important;
                margin: 0 !important;
                font-size: 12px !important;
                page-break-inside: auto !important;
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
                font-size: 11px !important;
            }
            
            .data-table th {
                background-color: #f0f0f0 !important;
                font-weight: bold !important;
            }
            
            /* Cacher spécifiquement les contrôles DataTables et autres éléments d'UI */
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate,
            .dataTables_wrapper .dataTables_processing,
            .buttons,
            .widget-title,
            #header,
            #footer,
            #sidebar,
            .no-print,
            input,
            form,
            .alert {
                display: none !important;
                visibility: hidden !important;
            }
        }
        
        /* Styles d'accessibilité améliorés */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        .data-table tr:hover {
            background-color: #f5f5f5;
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
        <div id="breadcrumb" aria-label="Fil d'Ariane">
            <a href="dashboard.php" title="Accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a href="stock-report.php" class="current">Rapport de Stock</a>
        </div>
        <h1>Rapport de Stock</h1>
    </div>
    
    <div class="container-fluid">
        <hr class="no-print" />
        
        <?php if (isset($error_msg)): ?>
        <div class="alert alert-error no-print">
            <button class="close" data-dismiss="alert">×</button>
            <strong>Erreur!</strong> <?= htmlspecialchars($error_msg) ?>
        </div>
        <?php endif; ?>
        
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
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <div class="control-group">
                                <label class="control-label" for="fromdate">De Date :</label>
                                <div class="controls">
                                    <input type="date" class="span11" name="fromdate" id="fromdate" value="<?php echo $fdate; ?>" required aria-required="true" />
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label" for="todate">À Date :</label>
                                <div class="controls">
                                    <input type="date" class="span11" name="todate" id="todate" value="<?php echo $tdate; ?>" required aria-required="true" />
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success" name="submit"><i class="icon-search"></i> Générer le Rapport</button>
                                <button type="button" class="btn" onclick="resetDates()"><i class="icon-refresh"></i> Réinitialiser</button>
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
                                <button onclick="window.print()" class="btn btn-primary btn-mini" title="Imprimer uniquement le tableau"><i class="icon-print"></i> Imprimer Tableau</button>
                                <a href="export-stock.php?from=<?= urlencode($fdate) ?>&to=<?= urlencode($tdate) ?>&token=<?= urlencode($_SESSION['csrf_token']) ?>" class="btn btn-info btn-mini" title="Exporter en Excel"><i class="icon-download"></i> Exporter</a>
                                <button onclick="toggleFullScreen()" class="btn btn-default btn-mini" title="Afficher en plein écran"><i class="icon-fullscreen"></i></button>
                            </div>
                        </div>
                        
                        <div class="widget-content">
                            <div class="table-responsive">
                                <!-- Table qui sera visible à l'impression -->
                                <table class="table table-bordered data-table" role="grid" aria-label="Rapport de stock">
                                    <thead>
                                        <tr>
                                            <th scope="col" width="5%">N°</th>
                                            <th scope="col" width="20%">Nom du Produit</th>
                                            <th scope="col" width="15%">Catégorie</th>
                                            <th scope="col" width="15%">Marque</th>
                                            <th scope="col" width="10%">Modèle</th>
                                            <th scope="col" width="8%">Stock Initial</th>
                                            <th scope="col" width="7%">Vendus</th>
                                            <th scope="col" width="7%">Retournés</th>
                                            <th scope="col" width="8%">Stock Restant</th>
                                            <th scope="col" width="5%">Statut</th>
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
                                    $total_initial = 0;
                                    $total_sold = 0;
                                    $total_returned = 0;
                                    $total_remain = 0;

                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            $initial = (int)$row['initial_stock'];
                                            $sold = (int)$row['sold_qty'];
                                            $returned = (int)$row['returned_qty'];
                                            $remain = $initial - $sold + $returned;
                                            $remain = max(0, $remain);
                                            
                                            // Cumuls pour les totaux
                                            $total_initial += $initial;
                                            $total_sold += $sold;
                                            $total_returned += $returned;
                                            $total_remain += $remain;
                                            
                                            // Définir la classe CSS selon le niveau de stock
                                            $stockClass = '';
                                            if ($remain === 0) {
                                                $stockClass = 'stock-danger';
                                            } elseif ($remain < 5) {
                                                $stockClass = 'stock-warning';
                                            } elseif ($remain > 0) {
                                                $stockClass = 'stock-ok';
                                            }
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
                                                <td class="<?= $stockClass ?>"><?= $remain === 0 ? 'Épuisé' : $remain ?></td>
                                                <td><?= $row['Status'] == STATUS_ACTIVE ? 'Actif' : 'Inactif' ?></td>
                                            </tr>
                                            <?php
                                            $cnt++;
                                        }
                                        // Ajouter une ligne de total
                                        ?>
                                        <tr class="info">
                                            <td colspan="5"><strong>TOTAUX</strong></td>
                                            <td><strong><?= $total_initial ?></strong></td>
                                            <td><strong><?= $total_sold ?></strong></td>
                                            <td><strong><?= $total_returned ?></strong></td>
                                            <td><strong><?= $total_remain ?></strong></td>
                                            <td>-</td>
                                        </tr>
                                        <?php
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
                            <div class="table-responsive">
                                <table class="table table-bordered data-table" role="grid" aria-label="Aperçu des produits récents">
                                    <thead>
                                        <tr>
                                            <th scope="col">N°</th>
                                            <th scope="col">Nom du Produit</th>
                                            <th scope="col">Catégorie</th>
                                            <th scope="col">Marque</th>
                                            <th scope="col">Modèle</th>
                                            <th scope="col">Stock Initial</th>
                                            <th scope="col">Vendus</th>
                                            <th scope="col">Retournés</th>
                                            <th scope="col">Stock Restant</th>
                                            <th scope="col">Statut</th>
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
                                            LIMIT ?
                                        ";
                                        $stmt = $con->prepare($sql);
                                        $limit = DEFAULT_LIMIT;
                                        $stmt->bind_param('i', $limit);
                                        $stmt->execute();
                                        $ret = $stmt->get_result();
                                        
                                        if ($ret->num_rows > 0) {
                                            $cnt = 1;
                                            while ($row = $ret->fetch_assoc()) {
                                                $initial = (int)$row['initial_stock'];
                                                $sold = (int)$row['sold_qty'];
                                                $returned = (int)$row['returned_qty'];
                                                $remain = $initial - $sold + $returned;
                                                $remain = max(0, $remain);
                                                
                                                // Définir la classe CSS selon le niveau de stock
                                                $stockClass = '';
                                                if ($remain === 0) {
                                                    $stockClass = 'stock-danger';
                                                } elseif ($remain < 5) {
                                                    $stockClass = 'stock-warning';
                                                } elseif ($remain > 0) {
                                                    $stockClass = 'stock-ok';
                                                }
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
                                                    <td class="<?= $stockClass ?>"><?= $remain === 0 ? 'Épuisé' : $remain ?></td>
                                                    <td><?= $row['Status'] == STATUS_ACTIVE ? 'Actif' : 'Inactif' ?></td>
                                                </tr>
                                                <?php
                                                $cnt++;
                                            }
                                        } else {
                                            echo '<tr><td colspan="10" class="text-center">Aucun Article trouvé</td></tr>';
                                        }
                                        $stmt->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
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
    // Initialisation améliorée de DataTables
    $(document).ready(function() {
        $('.data-table').DataTable({
            "paging": true,
            "ordering": true,
            "info": true,
            "searching": true,
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Tous"]],
            "language": {
                "lengthMenu": "Afficher _MENU_ entrées par page",
                "zeroRecords": "Aucun enregistrement trouvé",
                "info": "Page _PAGE_ sur _PAGES_",
                "infoEmpty": "Aucun enregistrement disponible",
                "infoFiltered": "(filtré de _MAX_ enregistrements au total)",
                "search": "Rechercher :",
                "paginate": {
                    "first": "Premier",
                    "last": "Dernier",
                    "next": "Suivant",
                    "previous": "Précédent"
                }
            },
            "dom": "<'row'<'span6'l><'span6'f>><'row'<'span12'tr>><'row'<'span6'i><'span6'p>>",
            "responsive": true,
            // Configurer l'impression DataTables
            "buttons": [
                {
                    extend: 'print',
                    text: 'Imprimer',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
            // Désactiver le tri par défaut de la colonne N°
            "columnDefs": [
                { "orderable": false, "targets": 0 }
            ],
            // Mode d'affichage pour l'impression
            "initComplete": function(settings, json) {
                if (window.matchMedia('print').matches) {
                    // Si en mode impression, masquer les contrôles
                    this.api().draw(false);
                }
            }
        });
    });
    
    // Validation du formulaire côté client
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        form && form.addEventListener('submit', function(e) {
            const from = new Date(document.getElementById('fromdate').value);
            const to = new Date(document.getElementById('todate').value);
            if (from > to) {
                e.preventDefault();
                alert('La date de début ne peut pas être après la date de fin.');
            }
        });
    });
    
    // Fonction pour réinitialiser les dates
    function resetDates() {
        document.getElementById('fromdate').value = '<?= date('Y-m-d', strtotime('-30 days')) ?>';
        document.getElementById('todate').value = '<?= date('Y-m-d') ?>';
    }
    
    // Fonction pour afficher en plein écran
    function toggleFullScreen() {
        if (!document.fullscreenElement) {
            const element = document.querySelector('.widget-box');
            if (element.requestFullscreen) {
                element.requestFullscreen();
            } else if (element.mozRequestFullScreen) {
                element.mozRequestFullScreen();
            } else if (element.webkitRequestFullscreen) {
                element.webkitRequestFullscreen();
            } else if (element.msRequestFullscreen) {
                element.msRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
    }

    // Optimisation pour l'impression
    window.addEventListener('beforeprint', function() {
        // Masquer manuellement tous les contrôles DataTables avant l'impression
        const datatablesControls = document.querySelectorAll('.dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate');
        datatablesControls.forEach(element => {
            element.style.display = 'none';
        });
    });
</script>

</body>
</html>