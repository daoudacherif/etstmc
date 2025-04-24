<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Récupère tous les produits avec leur stock pour les utiliser dans le champ de recherche (datalist)
$productNames = [];
$prodRes = mysqli_query($con, "SELECT ID, ProductName, Stock FROM tblproducts ORDER BY ProductName ASC");
while ($p = mysqli_fetch_assoc($prodRes)) {
    $productNames[] = $p;
}

function getAccessToken() {
    // Fonction pour obtenir un jeton d'accès (non affichée ici)
}

function sendSmsNotification($to, $message) {
    // Fonction pour envoyer une notification SMS (non affichée ici)
}

// Redirige vers la page de déconnexion si l'utilisateur n'est pas connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php'); exit;
}

// Gestion de l'ajout au panier
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity  = max(1, intval($_POST['quantity']));
    $price     = max(0, floatval($_POST['price']));

    // Vérifie le stock du produit sélectionné
    $resStock = mysqli_query($con, "SELECT Stock FROM tblproducts WHERE ID=$productId");
    $stockRow = mysqli_fetch_assoc($resStock);
    $stock    = intval($stockRow['Stock'] ?? 0);

    if ($stock <= 0) {
        echo "<script>alert('Produit en rupture de stock');location='cart.php';</script>";
        exit;
    }

    if ($quantity > $stock) {
        echo "<script>alert('Quantité demandée supérieure au stock disponible');location='cart.php';</script>";
        exit;
    }

    // Vérifie si le produit est déjà dans le panier (non encore validé)
    $chk = mysqli_query($con, "SELECT ID,ProductQty FROM tblcart WHERE ProductId=$productId AND IsCheckOut=0");
    if (mysqli_num_rows($chk)) {
        // Si oui, on met à jour la quantité
        $c = mysqli_fetch_assoc($chk);
        $newQty = $c['ProductQty'] + $quantity;
        mysqli_query($con, "UPDATE tblcart SET ProductQty=$newQty,Price=$price WHERE ID={$c['ID']}");
    } else {
        // Sinon, on insère une nouvelle entrée dans le panier
        mysqli_query($con, "INSERT INTO tblcart(ProductId,ProductQty,Price,IsCheckOut) VALUES($productId,$quantity,$price,0)");
    }

    // Message de confirmation
    echo "<script>alert('Produit ajouté au panier');location='cart.php';</script>";
    exit;
}

// Auto remove, discount, checkout logic (non affiché ici)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panier de produits</title>
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
            <a class="current">Panier</a>
        </div>
        <h1>Panier de produits</h1>
    </div>
    <div class="container-fluid">
        <hr>
        <!-- Formulaire de recherche de produits -->
        <form method="get" class="form-inline">
            <label>Rechercher :</label>
            <input list="productsList" name="searchTerm" class="span3" placeholder="Nom produit...">
            <datalist id="productsList">
            <?php
            $all = mysqli_query($con, "SELECT ID,ProductName,Stock FROM tblproducts ORDER BY ProductName");
            while($p = mysqli_fetch_assoc($all)) {
                echo '<option data-stock="'.$p['Stock'].'" value="'.htmlspecialchars($p['ProductName']).'">';
            }
            ?>
            </datalist>
            <button class="btn btn-primary">Rechercher</button>
        </form>
        <hr>
        <!-- Résultats de la recherche -->
        <?php if(!empty($_GET['searchTerm'])):
            $term = mysqli_real_escape_string($con, $_GET['searchTerm']);
            $res = mysqli_query($con,
                "SELECT p.ID,p.ProductName,p.BrandName,p.ModelNumber,p.Price,p.Stock,c.CategoryName,s.SubCategoryName
                FROM tblproducts p
                LEFT JOIN tblcategory c ON c.ID=p.CatID
                LEFT JOIN tblsubcategory s ON s.ID=p.SubcatID
                WHERE p.ProductName LIKE '%$term%'");
        ?>
        <h4>Résultats: <?=mysqli_num_rows($res)?></h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nom du produit</th>
                    <th>Catégorie</th>
                    <th>Sous-catégorie</th>
                    <th>Marque</th>
                    <th>Modèle</th>
                    <th>Prix</th>
                    <th>Stock</th>
                    <th>Quantité</th>
                    <th>Ajouter</th>
                </tr>
            </thead>
            <tbody>
            <?php $i=1; while($r=mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($r['ProductName']) ?></td>
                    <td><?= htmlspecialchars($r['CategoryName']) ?></td>
                    <td><?= htmlspecialchars($r['SubCategoryName']) ?></td>
                    <td><?= htmlspecialchars($r['BrandName']) ?></td>
                    <td><?= htmlspecialchars($r['ModelNumber']) ?></td>
                    <td><?= number_format($r['Price'], 2) ?> €</td>
                    <td><?= intval($r['Stock']) ?></td>
                    <td>
                        <?php if($r['Stock']>0): ?>
                        <form method="post" class="form-inline">
                            <input type="hidden" name="productid" value="<?=$r['ID']?>">
                            <input type="number" name="quantity" value="1" min="1" max="<?=$r['Stock']?>" style="width:60px;">
                            <input type="number" name="price" value="<?=$r['Price']?>" step="any" style="width:80px;">
                            <button name="addtocart" class="btn btn-success">Ajouter</button>
                        </form>
                        <?php else: ?>
                        <span class="text-danger">Rupture de stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="color:red;">Aucun produit correspondant trouvé.</p>
                <?php endif; ?>
            </div>
        </div>
        <hr>

        <!-- ========== PANIER + REMISE + PAIEMENT ========== -->
        <div class="row-fluid">
            <div class="span12">
                <form method="post" class="form-inline" style="text-align:right;">
                    <label>Remise :</label>
                    <input type="number" name="discount" step="any" value="<?php echo $discount; ?>" style="width:80px;" />
                    <button class="btn btn-info" type="submit" name="applyDiscount">Appliquer</button>
                </form>
                <hr>

                <!-- Formulaire checkout (informations client) -->
                <form method="post" class="form-horizontal" name="submit">
                    <div class="control-group">
                        <label class="control-label">Nom du client :</label>
                        <div class="controls">
                            <input type="text" class="span11" id="customername" name="customername" required />
                        </div>
                    </div>
                    <div class="control-group">
                        <label class="control-label">Numéro de mobile du client :</label>
                        <div class="controls">
                            <input type="tel"
                                   class="span11"
                                   id="mobilenumber"
                                   name="mobilenumber"
                                   required
                                   pattern="^\+224[0-9]{9}$"
                                   placeholder="+224xxxxxxxxx"
                                   title="Format: +224 suivi de 9 chiffres">
                        </div>
                    </div>
                    <div class="control-group">
                        <label class="control-label">Mode de paiement :</label>
                        <div class="controls">
                            <label><input type="radio" name="modepayment" value="cash" checked> Espèces</label>
                            <label><input type="radio" name="modepayment" value="card"> Carte</label>
                        </div>
                    </div>
                    <div class="text-center">
                        <button class="btn btn-primary" type="submit" name="submit">
                            Paiement & Créer une facture
                        </button>
                    </div>
                </form>

                <!-- Tableau du panier -->
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-th"></i></span>
                        <h5>Produits dans le panier</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <table class="table table-bordered" style="font-size: 15px">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Nom du produit</th>
                                    <th>Quantité</th>
                                    <th>Prix (par unité)</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $ret = mysqli_query($con, "
                                  SELECT 
                                    tblcart.ID as cid,
                                    tblcart.ProductQty,
                                    tblcart.Price as cartPrice,
                                    tblproducts.ProductName
                                  FROM tblcart
                                  LEFT JOIN tblproducts ON tblproducts.ID = tblcart.ProductId
                                  WHERE tblcart.IsCheckOut = 0
                                  ORDER BY tblcart.ID ASC
                                ");
                                $cnt = 1;
                                $grandTotal = 0;
                                $num = mysqli_num_rows($ret);
                                if ($num > 0) {
                                    while ($row = mysqli_fetch_array($ret)) {
                                        $pq = $row['ProductQty'];
                                        $ppu = $row['cartPrice'];
                                        $lineTotal = $pq * $ppu;
                                        $grandTotal += $lineTotal;
                                        ?>
                                        <tr class="gradeX">
                                            <td><?php echo $cnt; ?></td>
                                            <td><?php echo $row['ProductName']; ?></td>
                                            <td><?php echo $pq; ?></td>
                                            <td><?php echo number_format($ppu, 2); ?></td>
                                            <td><?php echo number_format($lineTotal, 2); ?></td>
                                            <td>
                                                <a href="cart.php?delid=<?php echo $row['cid']; ?>"
                                                   onclick="return confirm('Voulez-vous vraiment retirer cet article?');">
                                                    <i class="icon-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php
                                        $cnt++;
                                    }
                                    $netTotal = $grandTotal - $discount;
                                    if ($netTotal < 0) {
                                        $netTotal = 0;
                                    }
                                    ?>
                                    <tr>
                                        <th colspan="4" style="text-align: right; font-weight: bold;">Total général</th>
                                        <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($grandTotal, 2); ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="4" style="text-align: right; font-weight: bold;">Remise</th>
                                        <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($discount, 2); ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="4" style="text-align: right; font-weight: bold; color: green;">Total net</th>
                                        <th colspan="2" style="text-align: center; font-weight: bold; color: green;"><?php echo number_format($netTotal, 2); ?></th>
                                    </tr>
                                    <?php
                                } else {
                                    ?>
                                    <tr>
                                        <td colspan="6" style="color:red; text-align:center">Aucun article trouvé dans le panier</td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div><!-- widget-content -->
                </div><!-- widget-box -->
            </div>
        </div><!-- row-fluid -->
    </div><!-- container-fluid -->
</div><!-- content -->

<!-- Footer -->
<?php include_once('includes/footer.php'); ?>

<!-- SCRIPTS -->
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