<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid']) == 0) {
  header('location:logout.php');
  exit;
}

// Requête SQL : LEFT JOIN pour inclure les produits même si CatID ou SubcatID est 0 / NULL
$ret = mysqli_query($con, "
    SELECT 
      p.ID AS pid,
      p.ProductName,
      p.BrandName,
      p.ModelNumber,
      p.Stock,
      p.Status,
      p.CreationDate,
      c.CategoryName,
      sc.SubCategoryname AS subcat
    FROM tblproducts p
    LEFT JOIN tblcategory c ON c.ID = p.CatID
    LEFT JOIN tblsubcategory sc ON sc.ID = p.SubcatID
    ORDER BY p.ID DESC
");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Système de Gestion des Stocks || Gérer les Produits</title>
  <?php include_once('includes/cs.php'); ?>
</head>
<body>

<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <a href="manage-product.php" class="tip-bottom">Gérer les Produits</a>
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
            <h5>Gérer les Produits</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom du Produit</th>
                  <th>Catégorie</th>
                  <th>Sous-Catégorie</th>
                  <th>Marque</th>
                  <th>Modèle</th>
                  <th>Stock</th>
                  <th>Statut</th>
                  <th>Date de Création</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $cnt = 1;
                while ($row = mysqli_fetch_assoc($ret)) {
                  // Si CategoryName ou SubCategoryname est NULL => on peut afficher 'Inconnue' si besoin
                  $catName   = $row['CategoryName'] ?: 'Inconnue';
                  $subcatName= $row['subcat'] ?: 'Inconnue';

                  // Statut (1 => Actif, 0 => Inactif)
                  $status = ($row['Status'] == "1") ? "Actif" : "Inactif";
                  ?>
                  <tr class="gradeX">
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo $row['ProductName']; ?></td>
                    <td><?php echo $catName; ?></td>
                    <td><?php echo $subcatName; ?></td>
                    <td><?php echo $row['BrandName']; ?></td>
                    <td><?php echo $row['ModelNumber']; ?></td>
                    <td><?php echo $row['Stock']; ?></td>
                    <td><?php echo $status; ?></td>
                    <td><?php echo $row['CreationDate']; ?></td>
                    <td class="center">
                      <a href="editproducts.php?editid=<?php echo $row['pid']; ?>">
                        <i class="icon-edit"></i>
                      </a>
                    </td>
                  </tr>
                  <?php 
                  $cnt++;
                }
                ?>
              </tbody>
            </table>
          </div><!-- widget-content nopadding -->
        </div><!-- widget-box -->
      </div><!-- span12 -->
    </div><!-- row-fluid -->
  </div><!-- container-fluid -->
</div><!-- content -->

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
