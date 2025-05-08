<?php 
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// Désactivation d'un produit
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    // Mettre le produit en inactif
    mysqli_query($con, "UPDATE tblproducts SET Status = 0 WHERE ID = $delid");
    header('location:manage-product.php');
    exit;
}

// DEBUG - Vérifions la connexion à la base de données
if (!$con) {
    die("Erreur de connexion: " . mysqli_connect_error());
}

// Comptons d'abord le nombre de produits
$countSql = "SELECT COUNT(*) as total FROM tblproducts";
$countResult = mysqli_query($con, $countSql);
$countRow = mysqli_fetch_assoc($countResult);
$totalProducts = $countRow['total'];

// Récupération des produits avec une requête simplifiée
$sql = "SELECT * FROM tblproducts ORDER BY ID DESC";
$ret = mysqli_query($con, $sql);

// Vérifier si la requête a réussi
$querySuccess = ($ret !== false);

// Obtenir le nombre de lignes
$numRows = $querySuccess ? mysqli_num_rows($ret) : 0;

// Récupérer l'erreur SQL si la requête a échoué
$sqlError = $querySuccess ? "" : mysqli_error($con);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gérer les Produits</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    <style>
        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .has-error {
            border-color: #dc3545;
            background-color: #f8d7da;
        }
        .plain-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .plain-table th, .plain-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .plain-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a class="current">Gérer les Produits</a>
        </div>
        <h1>Gérer les Produits</h1>
    </div>
    <div class="container-fluid"><hr>
        
        <!-- DEBUG: Informations de débogage -->
        <div class="debug-info <?= $querySuccess ? '' : 'has-error' ?>">
            <h4>Informations de débogage</h4>
            <p>Nombre total de produits en base: <strong><?= $totalProducts ?></strong></p>
            <p>Requête SQL exécutée: <code><?= htmlspecialchars($sql) ?></code></p>
            <p>Statut de la requête: <?= $querySuccess ? '<span class="label label-success">Succès</span>' : '<span class="label label-important">Échec</span>' ?></p>
            <p>Nombre de lignes retournées: <strong><?= $numRows ?></strong></p>
            <?php if (!$querySuccess): ?>
                <p>Erreur SQL: <strong><?= htmlspecialchars($sqlError) ?></strong></p>
            <?php endif; ?>
        </div>

        <!-- Affichage basique sans DataTables -->
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-th"></i></span>
                        <h5>Liste des Produits (Affichage simple)</h5>
                    </div>
                    <div class="widget-content">
                        <?php if ($numRows > 0): ?>
                            <table class="plain-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Stock</th>
                                        <th>Prix</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    while ($row = mysqli_fetch_assoc($ret)) {
                                        echo "<tr>";
                                        echo "<td>" . $row['ID'] . "</td>";
                                        echo "<td>" . htmlspecialchars($row['ProductName']) . "</td>";
                                        echo "<td>" . $row['Stock'] . "</td>";
                                        echo "<td>" . number_format($row['Price'], 2) . "</td>";
                                        echo "<td>" . ($row['Status'] == 1 ? 'Actif' : 'Inactif') . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <strong>Information!</strong> Aucun produit trouvé dans la base de données.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Version avec DataTables -->
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-th"></i></span>
                        <h5>Liste des Produits (avec DataTables)</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <table class="table table-bordered" id="product-table">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Produit</th>
                                    <th>Stock</th>
                                    <th>Prix</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            // Réinitialiser le pointeur de résultat
                            mysqli_data_seek($ret, 0);
                            
                            if ($numRows > 0) {
                                $cnt = 1;
                                while ($row = mysqli_fetch_assoc($ret)) {
                                    ?>
                                    <tr>
                                        <td><?= $cnt++ ?></td>
                                        <td><?= htmlspecialchars($row['ProductName']) ?></td>
                                        <td><?= intval($row['Stock']) ?></td>
                                        <td><?= number_format(floatval($row['Price']), 2) ?></td>
                                        <td><?= $row['Status'] == 1 ? 'Actif' : 'Inactif' ?></td>
                                        <td>
                                            <a href="editproducts.php?editid=<?= $row['ID'] ?>" class="btn btn-mini btn-info"><i class="icon-edit"></i></a>
                                            <a href="manage-product.php?delid=<?= $row['ID'] ?>" 
                                               onclick="return confirm('Désactiver ce produit ?')" 
                                               class="btn btn-mini btn-danger"><i class="icon-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="6" class="text-center">Aucun produit trouvé</td></tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    // Initialisation simple sans les plugins additionnels qui pourraient causer des problèmes
    $('#product-table').DataTable({
        "paging": true,
        "ordering": true,
        "info": true,
        "searching": true,
        "language": {
            "emptyTable": "Aucune donnée disponible",
            "info": "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
            "infoEmpty": "Affichage de 0 à 0 sur 0 entrées",
            "search": "Rechercher:",
            "lengthMenu": "Afficher _MENU_ entrées",
            "zeroRecords": "Aucun résultat trouvé"
        }
    });
});
</script>
</body>
</html>