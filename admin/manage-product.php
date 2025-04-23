<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
    exit;
}

// Désactivation (ou suppression) d'un produit
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    // Désactivation (Statut = 0)
    mysqli_query($con, "UPDATE tblproducts SET Status=0 WHERE ID=$delid");
    // Pour suppression physique, décommentez :
    // mysqli_query($con, "DELETE FROM tblproducts WHERE ID=$delid");

    header('location:manage-product.php');
    exit;
}

// Récupération des produits + noms catégories/sous-catégories
$sql = "
SELECT
  p.ID AS pid,
  p.ProductName,
  c.CategoryName,
  sc.SubCategoryName,
  p.BrandName,
  p.ModelNumber,
  p.Stock,
  p.Price,
  p.Status,
  p.CreationDate
FROM tblproducts p
LEFT JOIN tblcategory c ON c.ID = p.CatID
LEFT JOIN tblsubcategory sc ON sc.ID = p.SubcatID
ORDER BY p.ID DESC
";
$ret = mysqli_query($con, $sql) or die('Erreur SQL : '.mysqli_error($con));
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
    <div class="container-fluid">
        <hr>
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-th"></i></span>
                        <h5>Liste des Produits</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <table class="table table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Produit</th>
                                    <th>Catégorie</th>
                                    <th>Sous-catégorie</th>
                                    <th>Marque</th>
                                    <th>Modèle</th>
                                    <th>Stock</th>
                                    <th>Prix</th>
                                    <th>Statut</th>
                                    <th>Créé le</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $cnt = 1;
                            while ($row = mysqli_fetch_assoc($ret)) {
                                $statusLabel = $row['Status'] == 1
                                    ? '<span class="label label-success">Actif</span>'
                                    : '<span class="label label-danger">Inactif</span>';
                                echo '<tr class="gradeX">';
                                echo '<td>'. $cnt++ .'</td>';
                                echo '<td>'. htmlspecialchars($row['ProductName']) .'</td>';
                                echo '<td>'. htmlspecialchars($row['CategoryName'] ?: '—') .'</td>';
                                echo '<td>'. htmlspecialchars($row['SubCategoryName'] ?: '—') .'</td>';
                                echo '<td>'. htmlspecialchars($row['BrandName']) .'</td>';
                                echo '<td>'. htmlspecialchars($row['ModelNumber']) .'</td>';
                                echo '<td>'. intval($row['Stock']) .'</td>';
                                echo '<td>'. number_format($row['Price'], 2) .'</td>';
                                echo '<td class="center">'. $statusLabel .'</td>';
                                echo '<td>'. date('d/m/Y H:i', strtotime($row['CreationDate'])) .'</td>';
                                echo '<td class="center">';
                                echo '<a href="editproducts.php?editid='. $row['pid'] .'" class="btn btn-mini btn-info"><i class="icon-edit"></i></a> ';
                                echo '<a href="manage-product.php?delid='. $row['pid'] .'" onclick="return confirm(\'Désactiver ce produit ?\')" class="btn btn-mini btn-danger"><i class="icon-trash"></i></a>';
                                echo '</td>';
                                echo '</tr>';
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
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.tables.js"></script>
</body>
</html>
