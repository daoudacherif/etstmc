<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifie que l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

/**
 * Obtenir un access token OAuth2 de Nimba
 */
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token";
    $client_id     = "1608e90e20415c7edf0226bf86e7effd";
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    
    $credentials = base64_encode("$client_id:$client_secret");

    $headers = [
        "Authorization: Basic $credentials",
        "Content-Type: application/x-www-form-urlencoded"
    ];

    $postData = http_build_query(["grant_type" => "client_credentials"]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    if (!$response) {
        error_log('Erreur cURL Token: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        error_log("Erreur HTTP Token ($httpCode): $response");
        return false;
    }

    $decoded = json_decode($response, true);
    return $decoded['access_token'] ?? false;
}

/**
 * Envoyer un SMS via Nimba avec Bearer Token
 */
function sendSmsNotification($to, $message) {
    $accessToken = getAccessToken();
    if (!$accessToken) return false;

    $url = "https://api.nimbasms.com/v1/messages";
    $payload = [
        "to" => [$to],
        "message" => $message,
        "sender_name" => "SMS 9080"
    ];

    $headers = [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("SMS envoyé, réponse: $response");

    return $httpCode == 201;
}

// ----------- Gestion Panier -----------

// Ajout au panier
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity  = max(1, intval($_POST['quantity']));
    $price     = max(0, floatval($_POST['price']));

    // Vérifier le stock disponible
    $stockCheck = mysqli_query($con, "SELECT Stock FROM tblproducts WHERE ID='$productId'");
    if ($row = mysqli_fetch_assoc($stockCheck)) {
        if ($row['Stock'] < $quantity) {
            echo "<script>alert('Stock insuffisant pour ce produit.'); window.location='dettecart.php';</script>";
            exit;
        }
    } else {
        echo "<script>alert('Produit introuvable.'); window.location='dettecart.php';</script>";
        exit;
    }

    $existCheck = mysqli_query($con, "SELECT ID, ProductQty FROM tblcart WHERE ProductId='$productId' AND IsCheckOut=0 LIMIT 1");
    if (mysqli_num_rows($existCheck) > 0) {
        $c = mysqli_fetch_assoc($existCheck);
        $newQty = $c['ProductQty'] + $quantity;
        if ($newQty > $row['Stock']) {
            echo "<script>alert('Quantité demandée supérieure au stock disponible.'); window.location='dettecart.php';</script>";
            exit;
        }
        mysqli_query($con, "UPDATE tblcart SET ProductQty='$newQty', Price='$price' WHERE ID='{$c['ID']}'") or die(mysqli_error($con));
    } else {
        mysqli_query($con, "INSERT INTO tblcart(ProductId, ProductQty, Price, IsCheckOut) VALUES('$productId', '$quantity', '$price', 0)") or die(mysqli_error($con));
    }

    header("Location: dettecart.php");
    exit;
}

// Supprimer un produit
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    mysqli_query($con, "DELETE FROM tblcart WHERE ID='$delid'") or die(mysqli_error($con));
    header("Location: dettecart.php");
    exit;
}

// Appliquer une remise
if (isset($_POST['applyDiscount'])) {
    $_SESSION['discount'] = max(0, floatval($_POST['discount']));
    header("Location: dettecart.php");
    exit;
}
$discount = $_SESSION['discount'] ?? 0;

// Checkout + Facturation
if (isset($_POST['submit'])) {
    $custname = mysqli_real_escape_string($con, trim($_POST['customername']));
    $custmobile = preg_replace('/[^0-9+]/', '', $_POST['mobilenumber']);
    $modepayment = mysqli_real_escape_string($con, $_POST['modepayment']);
    $paidNow = max(0, floatval($_POST['paid']));

    // Calcul total du panier
    $grandTotal = 0;
    $cartQuery = mysqli_query($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=0");
    while ($row = mysqli_fetch_assoc($cartQuery)) {
        $grandTotal += $row['ProductQty'] * $row['Price'];
    }

    $netTotal = max(0, $grandTotal - $discount);
    $dues = max(0, $netTotal - $paidNow);

    // Vérification finale du stock
    $stockCheck = mysqli_query($con, "
        SELECT p.ProductName, p.Stock, c.ProductQty
        FROM tblcart c
        JOIN tblproducts p ON p.ID = c.ProductId
        WHERE c.IsCheckOut=0
    ");
    while ($row = mysqli_fetch_assoc($stockCheck)) {
        if ($row['Stock'] < $row['ProductQty']) {
            echo "<script>alert('Stock insuffisant pour {$row['ProductName']}'); window.location='dettecart.php';</script>";
            exit;
        }
    }

    $billingnum = mt_rand(100000000, 999999999);

    // Validation du panier + Création facture
    $queries = "
        UPDATE tblcart SET BillingId='$billingnum', IsCheckOut=1 WHERE IsCheckOut=0;
        INSERT INTO tblcustomer(BillingNumber, CustomerName, MobileNumber, ModeOfPayment, BillingDate, FinalAmount, Paid, Dues)
        VALUES('$billingnum', '$custname', '$custmobile', '$modepayment', NOW(), '$netTotal', '$paidNow', '$dues');
    ";
    if (mysqli_multi_query($con, $queries)) {
        while (mysqli_more_results($con) && mysqli_next_result($con)) {}

        // Décrémentation du stock
        mysqli_query($con, "
            UPDATE tblproducts p
            JOIN tblcart c ON p.ID = c.ProductId
            SET p.Stock = p.Stock - c.ProductQty
            WHERE c.BillingId='$billingnum'
        ") or die(mysqli_error($con));

        // SMS personnalisé
        if ($dues > 0) {
            $smsMessage = "Bonjour $custname, votre commande est enregistrée. Solde dû: " . number_format($dues, 0, ',', ' ') . " GNF.";
        } else {
            $smsMessage = "Bonjour $custname, votre commande est confirmée. Merci pour votre confiance !";
        }
        sendSmsNotification($custmobile, $smsMessage);

        unset($_SESSION['discount']);
        $_SESSION['invoiceid'] = $billingnum;

        echo "<script>alert('Facture créée: $billingnum'); window.location='dettecart.php';</script>";
        exit;
    } else {
        die('Erreur SQL : ' . mysqli_error($con));
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de Gestion d'Inventaire | Panier</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    <!-- Header + Sidebar -->
    <?php include_once('includes/header.php'); ?>
    <?php include_once('includes/sidebar.php'); ?>
  
    <div id="content">
        <div id="content-header">
            <div id="breadcrumb">
                <a href="dashboard.php" class="tip-bottom">
                    <i class="icon-home"></i> Accueil
                </a>
                <a href="dettecart.php" class="current">Panier de Produits</a>
            </div>
            <h1>Panier de Produits (Vente à terme possible)</h1>
        </div>
  
        <div class="container-fluid">
            <hr>
            <!-- ====================== FORMULAIRE DE RECHERCHE (avec datalist) ====================== -->
            <div class="row-fluid">
                <div class="span12">
                    <form method="get" action="dettecart.php" class="form-inline">
                        <label>Rechercher des Produits :</label>
                        <input type="text" name="searchTerm" class="span3"
                               placeholder="Nom du produit ou modèle..." list="productsList" />
                        <datalist id="productsList">
                            <?php
                            foreach ($productNames as $pname) {
                                echo '<option value="' . htmlspecialchars($pname) . '"></option>';
                            }
                            ?>
                        </datalist>
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </form>
                </div>
            </div>
            <hr>
  
            <!-- ====================== RÉSULTATS DE RECHERCHE ====================== -->
            <?php
            if (!empty($_GET['searchTerm'])) {
                $searchTerm = mysqli_real_escape_string($con, $_GET['searchTerm']);
                $sql = "
                    SELECT p.ID, p.ProductName, p.BrandName, p.ModelNumber, p.Price,
                           c.CategoryName, s.SubCategoryName
                    FROM tblproducts p
                    LEFT JOIN tblcategory c ON c.ID = p.CatID
                    LEFT JOIN tblsubcategory s ON s.ID = p.SubcatID
                    WHERE (p.ProductName LIKE '%$searchTerm%' OR p.ModelNumber LIKE '%$searchTerm%')
                ";
                $res = mysqli_query($con, $sql);
                $count = mysqli_num_rows($res);
                ?>
                <div class="row-fluid">
                    <div class="span12">
                        <h4>Résultats de recherche pour "<em><?php echo htmlentities($searchTerm); ?></em>"</h4>
                        <?php if ($count > 0) { ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom du Produit</th>
                                        <th>Catégorie</th>
                                        <th>Sous-Catégorie</th>
                                        <th>Marque</th>
                                        <th>Modèle</th>
                                        <th>Prix par Défaut</th>
                                        <th>Prix Personnalisé</th>
                                        <th>Quantité</th>
                                        <th>Ajouter</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $i = 1;
                                while ($row = mysqli_fetch_assoc($res)) {
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo $row['ProductName']; ?></td>
                                        <td><?php echo $row['CategoryName']; ?></td>
                                        <td><?php echo $row['SubCategoryName']; ?></td>
                                        <td><?php echo $row['BrandName']; ?></td>
                                        <td><?php echo $row['ModelNumber']; ?></td>
                                        <td><?php echo $row['Price']; ?></td>
                                        <td>
                                            <form method="post" action="dettecart.php" style="margin:0;">
                                                <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>" />
                                                <input type="number" name="price" step="any" 
                                                       value="<?php echo $row['Price']; ?>" style="width:80px;" />
                                        </td>
                                        <td>
                                            <input type="number" name="quantity" value="1" min="1" style="width:60px;" />
                                        </td>
                                        <td>
                                            <button type="submit" name="addtocart" class="btn btn-success btn-small">
                                                <i class="icon-plus"></i> Ajouter
                                            </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                </tbody>
                            </table>
                        <?php } else { ?>
                            <p style="color:red;">Aucun produit correspondant trouvé.</p>
                        <?php } ?>
                    </div>
                </div>
                <hr>
            <?php } ?>
  
            <!-- ====================== AFFICHAGE DU PANIER + REMISE + CHECKOUT ====================== -->
            <div class="row-fluid">
                <div class="span12">
                    <!-- FORMULAIRE DE REMISE -->
                    <form method="post" class="form-inline" style="text-align:right;">
                        <label>Remise :</label>
                        <input type="number" name="discount" step="any" value="<?php echo $discount; ?>" style="width:80px;" />
                        <button class="btn btn-info" type="submit" name="applyDiscount">Appliquer</button>
                    </form>
                    <hr>
  
                    <!-- FORMULAIRE DE CHECKOUT (informations client + montant payé) -->
                    <form method="post" class="form-horizontal" name="submit">
                        <div class="control-group">
                            <label class="control-label">Nom du Client :</label>
                            <div class="controls">
                                <input type="text" class="span11" name="customername" required />
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label">Numéro de Mobile :</label>
                            <div class="controls">
                                <!-- Validation pour le format sénégalais : +221 suivi de 9 chiffres -->
                                <input type="tel"
                                       class="span11"
                                       name="mobilenumber"
                                       required
                                       pattern="^\+221[0-9]{9}$"
                                       placeholder="+221787368793"
                                       title="Format: +221 suivi de 9 chiffres">
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label">Mode de Paiement :</label>
                            <div class="controls">
                                <label><input type="radio" name="modepayment" value="cash" checked> Espèces</label>
                                <label><input type="radio" name="modepayment" value="card"> Carte</label>
                                <label><input type="radio" name="modepayment" value="credit"> Crédit (Terme)</label>
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label">Montant Payé Maintenant :</label>
                            <div class="controls">
                                <input type="number" name="paid" step="any" value="0" class="span11" />
                                <p style="font-size: 12px; color: #666;">(Laissez 0 si rien n'est payé maintenant)</p>
                            </div>
                        </div>
  
                        <div class="form-actions" style="text-align:center;">
                            <button class="btn btn-primary" type="submit" name="submit">
                                Valider & Créer la Facture
                            </button>
                        </div>
                    </form>
  
                    <!-- Tableau du panier -->
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5>Produits dans le Panier</h5>
                        </div>
                        <div class="widget-content nopadding">
                            <table class="table table-bordered" style="font-size: 15px">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom du Produit</th>
                                        <th>Quantité</th>
                                        <th>Prix (unité)</th>
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
                                            $pq    = $row['ProductQty'];
                                            $ppu   = $row['cartPrice'];
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
                                                    <a href="dettecart.php?delid=<?php echo $row['cid']; ?>"
                                                       onclick="return confirm('Voulez-vous vraiment supprimer cet article ?');">
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
                                            <th colspan="4" style="text-align: right; font-weight: bold;">Total Général</th>
                                            <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($grandTotal, 2); ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="4" style="text-align: right; font-weight: bold;">Remise</th>
                                            <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($discount, 2); ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="4" style="text-align: right; font-weight: bold; color: green;">Total Net</th>
                                            <th colspan="2" style="text-align: center; font-weight: bold; color: green;"><?php echo number_format($netTotal, 2); ?></th>
                                        </tr>
                                        <?php
                                    } else {
                                        ?>
                                        <tr>
                                            <td colspan="6" style="color:red; text-align:center;">Aucun article trouvé dans le panier</td>
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
