<?php
session_start();
// Affiche toutes les erreurs (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('includes/dbconnection.php');

/**
 * Récupère un token d'accès OAuth2 depuis NimbaSMS
 */
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token";
    $client_id     = "1608e90e20415c7edf0226bf86e7effd";
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    $credentials   = base64_encode("$client_id:$client_secret");

    $headers = [
        "Authorization: Basic $credentials",
        "Content-Type: application/x-www-form-urlencoded"
    ];
    $postData = http_build_query([ "grant_type" => "client_credentials" ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        error_log("Erreur token HTTP $httpCode : $response");
        return false;
    }

    $decoded = json_decode($response, true);
    return $decoded['access_token'] ?? false;
}

/**
 * Envoie un SMS via NimbaSMS
 */
function sendSmsNotification($to, $message) {
    $token = getAccessToken();
    if (!$token) {
        return false;
    }

    $url = "https://api.nimbasms.com/v1/messages";
    $postData = json_encode([
        "to"          => [$to],
        "message"     => $message,
        "sender_name" => "SMS 9080"
    ]);
    $headers = [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($status === 201);
}

//////////////////////////////////////////////////////////////////////////
// GESTION DE L’AJOUT AU PANIER
//////////////////////////////////////////////////////////////////////////
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity  = max(1, intval($_POST['quantity']));
    $price     = max(0, floatval($_POST['price']));

    // 1) Récupérer le stock actuel
    $stockRes = mysqli_query($con, "
        SELECT Stock 
        FROM tblproducts 
        WHERE ID = '$productId' 
        LIMIT 1
    ");
    if (!$stockRes || mysqli_num_rows($stockRes) === 0) {
        echo "<script>
                alert('Produit introuvable');
                window.location.href='cart.php';
              </script>";
        exit;
    }
    $row   = mysqli_fetch_assoc($stockRes);
    $stock = intval($row['Stock']);

    // 2) Interdire si stock épuisé
    if ($stock <= 0) {
        echo "<script>
                alert('Désolé, ce produit est en rupture de stock.');
                window.location.href='cart.php';
              </script>";
        exit;
    }

    // 3) Interdire si quantité demandée > stock
    if ($quantity > $stock) {
        echo "<script>
                alert('Vous avez demandé $quantity exemplaire(s), il ne reste que $stock en stock.');
                window.location.href='cart.php';
              </script>";
        exit;
    }

    // 4) INSERT ou UPDATE dans tblcart
    $checkCart = mysqli_query($con, "
        SELECT ID, ProductQty 
        FROM tblcart 
        WHERE ProductId='$productId' AND IsCheckOut=0 
        LIMIT 1
    ");
    if (mysqli_num_rows($checkCart) > 0) {
        $c      = mysqli_fetch_assoc($checkCart);
        $newQty = $c['ProductQty'] + $quantity;
        mysqli_query($con, "
            UPDATE tblcart 
            SET ProductQty='$newQty', Price='$price' 
            WHERE ID='{$c['ID']}'
        ");
    } else {
        mysqli_query($con, "
            INSERT INTO tblcart(ProductId, ProductQty, Price, IsCheckOut) 
            VALUES('$productId','$quantity','$price','0')
        ");
    }

    echo "<script>
            alert('Produit ajouté au panier !');
            window.location.href='cart.php';
          </script>";
    exit;
}

//////////////////////////////////////////////////////////////////////////
// VALIDATION DU PANIER / CHECKOUT
//////////////////////////////////////////////////////////////////////////
if (isset($_POST['submit'])) {
    // Récupération des infos client
    $custname     = mysqli_real_escape_string($con, trim($_POST['customername']));
    $custmobile   = preg_replace('/[^0-9+]/','', $_POST['mobilenumber']);
    $modepayment  = mysqli_real_escape_string($con, $_POST['modepayment']);
    $discount     = $_SESSION['discount'] ?? 0;

    // Calcul du total
    $cartQ = mysqli_query($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=0");
    $grand = 0;
    while ($r = mysqli_fetch_assoc($cartQ)) {
        $grand += $r['ProductQty'] * $r['Price'];
    }
    $netTotal = max(0, $grand - $discount);

    // Générer un numéro de facture unique
    $billingnum = mt_rand(100000000, 999999999);

    // Mise à jour du panier + insertion client
    $query  = "UPDATE tblcart SET BillingId='$billingnum', IsCheckOut=1 WHERE IsCheckOut=0;";
    $query .= "INSERT INTO tblcustomer
                 (BillingNumber, CustomerName, MobileNumber, ModeofPayment, FinalAmount)
               VALUES
                 ('$billingnum','$custname','$custmobile','$modepayment','$netTotal');";
    $result = mysqli_multi_query($con, $query);

    if ($result) {
        // Vider les résultats de la multi_query pour éviter le 'out of sync'
        while (mysqli_more_results($con) && mysqli_next_result($con)) {
            // rien à faire, on boucle juste
        }

        // Décrémenter le stock dans tblproducts
        $updateStockSql = "
            UPDATE tblproducts p
            JOIN tblcart c ON p.ID = c.ProductId
            SET p.Stock = p.Stock - c.ProductQty
            WHERE c.BillingId = '$billingnum'
              AND c.IsCheckOut  = 1
        ";
        mysqli_query($con, $updateStockSql);

        // Envoi du SMS de confirmation
        $smsMessage = "Bonjour $custname, votre commande (Facture No:$billingnum) est confirmée. Merci !";
        $smsResult  = sendSmsNotification($custmobile, $smsMessage);
        $smsMsg     = $smsResult ? "SMS envoyé" : "Échec SMS";

        $_SESSION['invoiceid'] = $billingnum;
        unset($_SESSION['discount']);

        echo "<script>
                alert('Facture créée : $billingnum\\n$smsMsg');
                window.location.href='invoice.php';
              </script>";
        exit;
    } else {
        echo "<script>alert('Erreur lors du paiement');</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion des stocks | Panier</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>

<!-- Header + Sidebar -->
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
                <i class="icon-home"></i> Accueil
            </a>
            <a href="cart.php" class="current">Panier de produits</a>
        </div>
        <h1>Panier de produits</h1>
    </div>

    <div class="container-fluid">
        <hr>
  <!-- ========== FORMULAIRE DE RECHERCHE (avec datalist) ========== -->
<div class="row-fluid">
    <div class="span12">
        <form method="get" action="cart.php" class="form-inline">
            <label>Rechercher des produits :</label>
            <input type="text" name="searchTerm" class="span3" placeholder="Nom du produit..." list="productsList" />
            <datalist id="productsList">
                <?php foreach ($productNames as $pname): ?>
                    <option value="<?= htmlspecialchars($pname) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <button type="submit" class="btn btn-primary">Rechercher</button>
        </form>
    </div>
</div>
<hr>

<?php
if (!empty($_GET['searchTerm'])) {
    $searchTerm = mysqli_real_escape_string($con, $_GET['searchTerm']);
    $sql = "
        SELECT
            p.ID,
            p.ProductName,
            p.ModelNumber,
            p.Price,
            p.Stock,
            p.CategoryName
        FROM tblproducts p
        WHERE
            p.ProductName LIKE '%$searchTerm%'
            OR p.ModelNumber LIKE '%$searchTerm%'
    ";

    $res   = mysqli_query($con, $sql);
    $count = mysqli_num_rows($res);
    ?>

    <div class="row-fluid">
        <div class="span12">
            <h4>Résultats de recherche pour "<em><?= htmlspecialchars($searchTerm) ?></em>"</h4>

            <?php if ($count > 0): ?>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom du produit</th>
                            <th>Catégorie</th>
                            <th>Modèle</th>
                            <th>Prix par défaut</th>
                            <th>Stock disponible</th>
                            <th>Quantité &amp; Ajouter</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($res)) {
                        $stock = (int) $row['Stock'];
                        ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['ProductName']); ?></td>
                            <td><?= htmlspecialchars($row['CategoryName']); ?></td>
                            <td><?= htmlspecialchars($row['ModelNumber']); ?></td>
                            <td><?= number_format($row['Price'], 2, ',', ' '); ?> €</td>
                            <td><?= $stock; ?></td>

                            <td>
                                <?php if ($stock > 0): ?>
                                    <form method="post" action="cart.php" class="d-flex align-items-center" style="gap:8px; margin:0;">
                                        <input type="hidden" name="productid" value="<?= $row['ID']; ?>">
                                        <input type="hidden" name="price"     value="<?= $row['Price']; ?>">

                                        <input
                                            type="number"
                                            name="quantity"
                                            value="1"
                                            min="1"
                                            max="<?= $stock; ?>"
                                            style="width:60px;"
                                            required
                                        >

                                        <button
                                            type="submit"
                                            name="addtocart"
                                            class="btn btn-success btn-sm"
                                        >
                                            <i class="icon-plus"></i> Ajouter
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">Rupture de stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:red;">Aucun produit correspondant trouvé.</p>
            <?php endif; ?>
        </div>
    </div>
<hr>

<div class="row-fluid">
    <div class="span12">
        <h4>Résultats de recherche pour "<em><?= htmlspecialchars($searchTerm) ?></em>"</h4>

        <?php if ($count > 0): ?>
            <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom du produit</th>
                            <th>Catégorie</th>
                            <th>Modèle</th>
                            <th>Prix par défaut</th>
                            <th>Stock disponible</th>
                            <th>Quantité &amp; Ajouter</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($res)) {
                        $stock = (int) $row['Stock'];
                        ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['ProductName']); ?></td>
                            <td><?= htmlspecialchars($row['CategoryName']); ?></td>
                            <td><?= htmlspecialchars($row['ModelNumber']); ?></td>
                            <td><?= number_format($row['Price'], 2, ',', ' '); ?> €</td>
                            <td><?= $stock; ?></td>

                            <td>
                                <?php if ($stock > 0): ?>
                                    <form method="post" action="cart.php" class="d-flex align-items-center" style="gap:8px; margin:0;">
                                        <input type="hidden" name="productid" value="<?= $row['ID']; ?>">
                                        <input type="hidden" name="price"     value="<?= $row['Price']; ?>">

                                        <input
                                            type="number"
                                            name="quantity"
                                            value="1"
                                            min="1"
                                            max="<?= $stock; ?>"
                                            style="width:60px;"
                                            required
                                        >

                                        <button
                                            type="submit"
                                            name="addtocart"
                                            class="btn btn-success btn-sm"
                                        >
                                            <i class="icon-plus"></i> Ajouter
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">Rupture de stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color:red;">Aucun produit correspondant trouvé.</p>
            <?php endif; ?>
        </div>
    </div>
    <hr>
<?php } ?>


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