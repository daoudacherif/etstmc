<?php
session_start();
error_reporting(E_ALL);
include_once 'includes/dbconnection.php';

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// Récupération et nettoyage des dates
$fdate = filter_input(INPUT_POST, 'fromdate', FILTER_SANITIZE_STRING);
$tdate = filter_input(INPUT_POST, 'todate', FILTER_SANITIZE_STRING);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Système de Gestion des Inventaires | Rapport entre deux dates</title>
    <?php include_once 'includes/cs.php'; ?>
    <?php include_once 'includes/responsive.php'; ?>
</head>
<body>
<?php include_once 'includes/header.php'; ?>
<?php include_once 'includes/sidebar.php'; ?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" title="Accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a href="stock-report.php" class="current">Rapport entre deux dates</a>
        </div>
        <h1>Détails des Produits</h1>
    </div>
    <div class="container-fluid">
        <hr />

        <?php if ($fdate && $tdate): ?>

            <div class="row-fluid">
                <div class="span12">
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5 class="text-center text-blue">
                                Rapport d'inventaire du <?= htmlspecialchars($fdate) ?> au <?= htmlspecialchars($tdate) ?>
                            </h5>
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
                                        <th>Stock initial</th>
                                        <th>Stock restant</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                // Préparer la requête pour éviter injection SQL
                                $stmt = $con->prepare(
                                    "SELECT p.ID, p.ProductName, c.CategoryName, s.SubCategoryname, p.BrandName,
                                            p.ModelNumber, p.Stock, p.Status,
                                            COALESCE(SUM(cart.ProductQty), 0) AS soldQty
                                     FROM tblproducts p
                                     JOIN tblcategory c ON c.ID = p.CatID
                                     JOIN tblsubcategory s ON s.ID = p.SubcatID
                                     LEFT JOIN tblcart cart ON cart.ProductId = p.ID
                                     WHERE DATE(p.CreationDate) BETWEEN ? AND ?
                                     GROUP BY p.ID
                                     ORDER BY p.ID DESC"
                                );
                                $stmt->bind_param('ss', $fdate, $tdate);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $cnt = 1;

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $initial = (int)$row['Stock'];
                                        $sold = (int)$row['soldQty'];
                                        $remain = $initial - $sold;
                                        ?>
                                        <tr>
                                            <td><?= $cnt ?></td>
                                            <td><?= htmlspecialchars($row['ProductName']) ?></td>
                                            <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                                            <td><?= htmlspecialchars($row['SubCategoryname']) ?></td>
                                            <td><?= htmlspecialchars($row['BrandName']) ?></td>
                                            <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                                            <td><?= $initial ?></td>
                                            <td><?= $remain ?></td>
                                            <td><?= $row['Status'] === '1' ? 'Actif' : 'Inactif' ?></td>
                                        </tr>
                                        <?php
                                        $cnt++;
                                    }
                                } else {
                                    echo '<tr><td colspan="9" class="text-center text-red">Aucun enregistrement trouvé.</td></tr>';
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
            <p class="text-center text-red">Veuillez sélectionner les dates de début et de fin.</p>
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
