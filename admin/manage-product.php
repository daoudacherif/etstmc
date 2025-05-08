<?php 
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// Désactivation d'un produit
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    mysqli_query($con, "UPDATE tblproducts SET Status = 0 WHERE ID = $delid");
    header('location:manage-product.php');
    exit;
}

// Récupération des produits avec une requête simple
$sql = "SELECT * FROM tblproducts ORDER BY ID DESC";
$ret = mysqli_query($con, $sql);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gérer les Produits</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
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
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-th"></i></span>
                        <h5>Liste des Produits</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <table class="table table-bordered" id="productTable">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Produit</th>
                                    <th>Modèle</th>
                                    <th>Stock</th>
                                    <th>Prix</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            if (mysqli_num_rows($ret) > 0) {
                                $cnt = 1;
                                while ($row = mysqli_fetch_assoc($ret)) {
                                    $statusLabel = $row['Status'] == 1
                                        ? '<span class="label label-success">Actif</span>'
                                        : '<span class="label label-important">Inactif</span>';
                                    ?>
                                    <tr>
                                        <td><?= $cnt++ ?></td>
                                        <td><?= htmlspecialchars($row['ProductName']) ?></td>
                                        <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                                        <td><?= intval($row['Stock']) ?></td>
                                        <td><?= number_format(floatval($row['Price']), 2) ?></td>
                                        <td class="center"><?= $statusLabel ?></td>
                                        <td class="center">
                                            <a href="editproducts.php?editid=<?= $row['ID'] ?>" class="btn btn-mini btn-info"><i class="icon-edit"></i></a>
                                            <a href="manage-product.php?delid=<?= $row['ID'] ?>" 
                                               onclick="return confirm('Désactiver ce produit ?')" 
                                               class="btn btn-mini btn-danger"><i class="icon-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center">Aucun produit trouvé</td></tr>';
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
    // Configuration DataTables simplifiée
    $('#productTable').DataTable({
        "bJQueryUI": false,           // Désactivé pour éviter des conflits
        "sPaginationType": "full_numbers",
        "sDom": '<"top"fl>rt<"bottom"ip>',
        "aaSorting": [],              // Pas de tri par défaut
        "bAutoWidth": false,          // Désactivé pour éviter des problèmes de largeur
        "language": {
            "emptyTable": "Aucune donnée disponible dans le tableau",
            "info": "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
            "infoEmpty": "Affichage de 0 à 0 sur 0 entrées",
            "infoFiltered": "(filtré à partir de _MAX_ entrées au total)",
            "thousands": " ",
            "lengthMenu": "Afficher _MENU_ entrées",
            "loadingRecords": "Chargement...",
            "processing": "Traitement...",
            "search": "Rechercher :",
            "zeroRecords": "Aucun enregistrement correspondant trouvé",
            "paginate": {
                "first": "Premier",
                "last": "Dernier",
                "next": "Suivant",
                "previous": "Précédent"
            }
        }
    });
});
</script>
</body>
</html>