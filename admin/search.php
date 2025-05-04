<?php
session_start();
error_reporting(E_ALL);
include_once 'includes/dbconnection.php';

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// Gestion de l'ajout au panier
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart'])) {
    $pid  = filter_input(INPUT_POST, 'pid', FILTER_SANITIZE_NUMBER_INT);
    $pqty = filter_input(INPUT_POST, 'pqty', FILTER_SANITIZE_NUMBER_INT);
    $remainQty = filter_input(INPUT_POST, 'rqty', FILTER_SANITIZE_NUMBER_INT);

    if ($pqty > 0 && $pqty <= $remainQty) {
        $stmt = $con->prepare("INSERT INTO tblcart (ProductId, ProductQty, IsCheckOut) VALUES (?, ?, 0)");
        $stmt->bind_param('ii', $pid, $pqty);
        if ($stmt->execute()) {
            echo "<script>alert('L\'article a été ajouté au panier');window.location='search.php';</script>";
            exit;
        } else {
            $msg = 'Erreur lors de l\'ajout au panier.';
        }
    } else {
        $msg = 'Quantité invalide ou supérieure au stock restant.';
    }
}

// Gestion de la recherche
$sdata = '';\$results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $sdata = trim($_POST['pname']);
    $sql = "SELECT p.ID AS pid, p.ProductName, c.CategoryName, s.SubCategoryname AS subcat,
                   p.BrandName, p.ModelNumber, p.Stock,
                   COALESCE(SUM(cart.ProductQty),0) AS sold_qty
            FROM tblproducts p
            LEFT JOIN tblcategory c ON c.ID = p.CatID
            LEFT JOIN tblsubcategory s ON s.ID = p.SubcatID
            LEFT JOIN tblcart cart ON cart.ProductId = p.ID AND cart.IsCheckOut = 1
            WHERE p.ProductName LIKE ?
            GROUP BY p.ID
            ORDER BY p.ID DESC";
    $stmt = $con->prepare($sql);
    $like = "%" . $sdata . "%";
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['remaining'] = max(0, $row['Stock'] - $row['sold_qty']);
        $results[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rechercher des Articles</title>
  <?php include_once 'includes/cs.php'; ?>
  <?php include_once 'includes/responsive.php'; ?>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<?php include_once 'includes/sidebar.php'; ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
      <a href="search.php" class="current">Rechercher des Articles</a>
    </div>
    <h1>Rechercher des Articles</h1>
  </div>
  <div class="container-fluid">
    <hr>
    <?php if ($msg): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <div class="widget-box">
      <div class="widget-content nopadding">
        <form method="post" class="form-horizontal">
          <div class="control-group">
            <label class="control-label" for="pname">Nom de l'article :</label>
            <div class="controls">
              <input type="text" id="pname" name="pname" class="span11" value="<?= htmlspecialchars($sdata) ?>" required>
            </div>
          </div>
          <div class="form-actions text-center">
            <button type="submit" name="search" class="btn btn-primary">Rechercher</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (isset($_POST['search'])): ?>
      <h4 class="text-center">Résultats pour "<?= htmlspecialchars($sdata) ?>"</h4>
      <div class="widget-box">
        <div class="widget-title"><span class="icon"><i class="icon-th"></i></span><h5>Articles trouvés</h5></div>
        <div class="widget-content nopadding">
          <table class="table table-bordered data-table">
            <thead>
              <tr>
                <th>N°</th><th>Article</th><th>Catégorie</th><th>Sous-catégorie</th>
                <th>Marque</th><th>Modèle</th><th>Stock</th><th>Restant</th>
                <th>Quantité</th><th>Statut</th><th class="no-print">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($results): $cnt = 1; foreach ($results as $row): ?>
                <tr>
                  <td><?= $cnt ?></td>
                  <td><?= htmlspecialchars($row['ProductName']) ?></td>
                  <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                  <td><?= htmlspecialchars($row['subcat']) ?></td>
                  <td><?= htmlspecialchars($row['BrandName']) ?></td>
                  <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                  <td><?= $row['Stock'] ?></td>
                  <td><?= $row['remaining'] ?></td>
                  <td>
                    <form method="post" class="form-inline">
                      <input type="hidden" name="pid" value="<?= $row['pid'] ?>">
                      <input type="hidden" name="rqty" value="<?= $row['remaining'] ?>">
                      <input type="number" name="pqty" value="1" min="1" max="<?= $row['remaining'] ?>" required style="width:60px;">
                  </td>
                  <td><?= $row['Status'] == 1 ? 'Actif' : 'Inactif' ?></td>
                  <td class="no-print">
                      <button type="submit" name="cart" class="btn btn-success btn-mini" <?= $row['remaining']<1?'disabled':'' ?>>Ajouter</button>
                    </form>
                  </td>
                </tr>
              <?php $cnt++; endforeach; else: ?>
                <tr><td colspan="11" class="text-center">Aucun article trouvé.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include_once 'includes/footer.php'; ?>
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
