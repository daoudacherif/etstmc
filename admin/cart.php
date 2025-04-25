<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Récupère un token OAuth pour l’API SMS
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token";
    $client_id = "1608e90e20415c7edf0226bf86e7effd";
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    $credentials = base64_encode("$client_id:$client_secret");
    $headers = [
        "Authorization: Basic $credentials",
        "Content-Type: application/x-www-form-urlencoded"
    ];
    $postData = http_build_query([ "grant_type" => "client_credentials" ]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        error_log("Erreur token HTTP $httpCode : $response");
        return false;
    }
    $decoded = json_decode($response, true);
    return $decoded['access_token'] ?? false;
}

// Envoie un SMS via l’API Nimba
function sendSmsNotification($to, $message) {
    $token = getAccessToken();
    if (!$token) return false;
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
    $options = [
        "http" => [
            "method"       => "POST",
            "header"       => implode("\r\n", $headers),
            "content"      => $postData,
            "ignore_errors"=> true
        ]
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $m);
    return (($m[1] ?? 0) == 201);
}

if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// ========== AJOUT AU PANIER AVEC VÉRIF STOCK ==========
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity  = max(1, intval($_POST['quantity']));
    $price     = max(0, floatval($_POST['price']));
    $userId    = intval($_SESSION['user_id']);

    // 1) Stock initial
    $stmt = $con->prepare("SELECT Stock FROM tblproducts WHERE ID = ? LIMIT 1");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $stmt->bind_result($initialStock);
    if (!$stmt->fetch()) {
        $stmt->close();
        echo "<script>alert('Produit introuvable');window.location='cart.php';</script>";
        exit;
    }
    $stmt->close();

    // 2) Quantité déjà en panier (non validé)
    $stmt = $con->prepare("
        SELECT COALESCE(SUM(ProductQty),0)
        FROM tblcart
        WHERE ProductId = ? AND UserID = ? AND IsCheckOut = 0
    ");
    $stmt->bind_param("ii", $productId, $userId);
    $stmt->execute();
    $stmt->bind_result($inCart);
    $stmt->fetch();
    $stmt->close();

    // 3) Calcul stock disponible
    $available = max(0, intval($initialStock) - intval($inCart));

    // 4) Blocage si épuisé
    if ($available <= 0) {
        echo "<script>alert('Stock épuisé pour ce produit.');window.location='cart.php';</script>";
        exit;
    }
    // 5) Blocage si demande trop élevée
    if ($quantity > $available) {
        echo "<script>
            alert('Il ne reste que {$available} exemplaire(s) disponible(s).');
            window.location='cart.php';
        </script>";
        exit;
    }

    // 6) Ajout / MAJ du panier
    $stmt = $con->prepare("
        SELECT ID, ProductQty
        FROM tblcart
        WHERE ProductId = ? AND UserID = ? AND IsCheckOut = 0
        LIMIT 1
    ");
    $stmt->bind_param("ii", $productId, $userId);
    $stmt->execute();
    $stmt->bind_result($cartId, $oldQty);
    if ($stmt->fetch()) {
        $stmt->close();
        $newQty = $oldQty + $quantity;
        $upd = $con->prepare("
            UPDATE tblcart
            SET ProductQty = ?, Price = ?
            WHERE ID = ?
        ");
        $upd->bind_param("idi", $newQty, $price, $cartId);
        $upd->execute();
        $upd->close();
    } else {
        $stmt->close();
        $ins = $con->prepare("
            INSERT INTO tblcart
                (ProductId, ProductQty, Price, UserID, IsCheckOut)
            VALUES (?, ?, ?, ?, 0)
        ");
        $ins->bind_param("iidi", $productId, $quantity, $price, $userId);
        $ins->execute();
        $ins->close();
    }

    echo "<script>alert('Produit ajouté au panier !');window.location='cart.php';</script>";
    exit;
}

// ========== SUPPRESSION D’UN ARTICLE ==========
if (isset($_GET['delid'])) {
    $rid = intval($_GET['delid']);
    $del = $con->prepare("DELETE FROM tblcart WHERE ID = ?");
    $del->bind_param("i", $rid);
    $del->execute();
    echo "<script>alert('Produit retiré du panier');window.location='cart.php';</script>";
    exit;
}

// ========== APPLICATION D’UNE REMISE ==========
if (isset($_POST['applyDiscount'])) {
    $_SESSION['discount'] = floatval($_POST['discount']);
    echo "<script>window.location='cart.php';</script>";
    exit;
}

// ========== VALIDATION & PAIEMENT ==========
if (isset($_POST['submit'])) {
    $custname    = mysqli_real_escape_string($con, trim($_POST['customername']));
    $custmobile  = preg_replace('/[^0-9\+]/', '', $_POST['mobilenumber']);
    $modepayment = mysqli_real_escape_string($con, $_POST['modepayment']);
    $discount    = $_SESSION['discount'] ?? 0;

    // Calcul du total brut
    $cartQ = $con->query("SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=0");
    $grand = 0;
    while ($r = $cartQ->fetch_assoc()) {
        $grand += $r['ProductQty'] * $r['Price'];
    }
    $netTotal = max(0, $grand - $discount);
    $billingnum = mt_rand(100000000, 999999999);

    // Mise à jour du panier + insertion client
    $multi  = "UPDATE tblcart SET BillingId='$billingnum', IsCheckOut=1 WHERE IsCheckOut=0;";
    $multi .= "INSERT INTO tblcustomer
        (BillingNumber, CustomerName, MobileNumber, ModeofPayment, FinalAmount)
        VALUES ('$billingnum','$custname','$custmobile','$modepayment','$netTotal');";
    if ($con->multi_query($multi)) {
        $_SESSION['invoiceid'] = $billingnum;
        unset($_SESSION['discount']);
        $smsMsg = sendSmsNotification($custmobile,
            "Bonjour $custname, votre commande (Facture No: $billingnum) a été validée. Merci !")
            ? "SMS envoyé avec succès"
            : "Échec d'envoi SMS";

        echo "<script>
            alert('Facture créée : $billingnum\\n$smsMsg');
            window.location='invoice.php';
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
            <a href="cart.php" class="current">Panier</a>
        </div>
        <h1>Panier de produits</h1>
    </div>
    <div class="container-fluid">
        <hr>
        <!-- Recherche produits -->
        <div class="row-fluid">
            <div class="span12">
                <form method="get" action="cart.php" class="form-inline">
                    <label>Rechercher :</label>
                    <input type="text" name="searchTerm" class="span3" placeholder="Nom du produit..." list="productsList"/>
                    <datalist id="productsList">
                        <?php foreach ($productNames as $p) {
                            echo '<option value="'.htmlspecialchars($p).'">'; } ?>
                    </datalist>
                    <button class="btn btn-primary">Rechercher</button>
                </form>
            </div>
        </div>
        <hr>
        <?php if (!empty($_GET['searchTerm'])):
            $term = mysqli_real_escape_string($con, $_GET['searchTerm']);
            $sql = "
                SELECT p.ID,p.ProductName,p.BrandName,p.ModelNumber,p.Price,p.Stock,
                       c.CategoryName,s.SubCategoryName
                FROM tblproducts p
                LEFT JOIN tblcategory c ON c.ID=p.CatID
                LEFT JOIN tblsubcategory s ON s.ID=p.SubcatID
                WHERE p.ProductName LIKE '%$term%' OR p.ModelNumber LIKE '%$term%'";
            $res = $con->query($sql);
            $count = $res->num_rows;
        ?>
        <div class="row-fluid"><div class="span12">
            <h4>Résultats pour “<em><?php echo htmlentities($term); ?></em>”</h4>
            <?php if ($count>0): ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th><th>Produit</th><th>Catégorie</th><th>Sous-catégorie</th>
                        <th>Marque</th><th>Modèle</th><th>Prix</th><th>Stock</th>
                        <th>Prix perso</th><th>Qté</th><th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; while($row=$res->fetch_assoc()):
                    $stock = intval($row['Stock']);
                ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo $row['ProductName']; ?></td>
                        <td><?php echo $row['CategoryName']; ?></td>
                        <td><?php echo $row['SubCategoryName']; ?></td>
                        <td><?php echo $row['BrandName']; ?></td>
                        <td><?php echo $row['ModelNumber']; ?></td>
                        <td><?php echo number_format($row['Price'],2); ?></td>
                        <td><?php echo $stock; ?></td>
                        <td>
                            <form method="post" action="cart.php" style="margin:0;">
                                <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>"/>
                                <input type="number" name="price" step="0.01" value="<?php echo $row['Price']; ?>"
                                  style="width:80px;" <?php if($stock<=0)echo'disabled';?>/>
                        </td>
                        <td>
                                <input type="number" name="quantity" min="1" max="<?php echo $stock; ?>" value="1"
                                  style="width:60px;" <?php if($stock<=0)echo'disabled';?>/>
                        </td>
                        <td>
                                <button name="addtocart" class="btn btn-success btn-mini"
                                  <?php if($stock<=0)echo'disabled title="Stock épuisé"';?>>
                                  <i class="icon-plus"></i> Ajouter
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="color:red;">Aucun produit trouvé.</p>
            <?php endif; ?>
        </div></div><hr>
        <?php endif; ?>

        <!-- Remise & Paiement -->
        <div class="row-fluid"><div class="span12">
            <form method="post" class="form-inline" style="text-align:right;">
                <label>Remise :</label>
                <input type="number" name="discount" step="0.01"
                       value="<?php echo $_SESSION['discount'] ?? 0; ?>" style="width:80px;"/>
                <button name="applyDiscount" class="btn btn-info">Appliquer</button>
            </form><hr>

            <form method="post" class="form-horizontal">
                <div class="control-group">
                    <label class="control-label">Nom :</label>
                    <div class="controls">
                        <input type="text" name="customername" class="span11" required/>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Mobile :</label>
                    <div class="controls">
                        <input type="tel" name="mobilenumber" class="span11"
                            pattern="^\+[0-9]{8,15}$" placeholder="+221..." required/>
                    </div>
                </div>
                <div class="control-group">
                    <label class="control-label">Paiement :</label>
                    <div class="controls">
                        <label><input type="radio" name="modepayment" value="cash" checked/> Espèces</label>
                        <label><input type="radio" name="modepayment" value="card"/> Carte</label>
                    </div>
                </div>
                <div class="text-center">
                    <button name="submit" class="btn btn-primary">Paiement & Facture</button>
                </div>
            </form>

            <!-- Tableau du panier -->
            <div class="widget-box">
                <div class="widget-title"><span class="icon"><i class="icon-th"></i></span>
                    <h5>Votre Panier</h5>
                </div>
                <div class="widget-content nopadding">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th><th>Produit</th><th>Qté</th>
                                <th>Prix U.</th><th>Total</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $ret = $con->query("
                          SELECT c.ID cid, c.ProductQty, c.Price cartPrice, p.ProductName
                          FROM tblcart c
                          JOIN tblproducts p ON p.ID=c.ProductId
                          WHERE c.IsCheckOut=0
                          ORDER BY c.ID
                        ");
                        $cnt = 1; $grand=0;
                        if($ret->num_rows>0):
                            while($r=$ret->fetch_assoc()):
                                $line = $r['ProductQty']*$r['cartPrice'];
                                $grand += $line;
                        ?>
                            <tr>
                                <td><?php echo $cnt++; ?></td>
                                <td><?php echo $r['ProductName']; ?></td>
                                <td><?php echo $r['ProductQty']; ?></td>
                                <td><?php echo number_format($r['cartPrice'],2); ?></td>
                                <td><?php echo number_format($line,2); ?></td>
                                <td>
                                    <a href="cart.php?delid=<?php echo $r['cid']; ?>"
                                       onclick="return confirm('Supprimer ?');">
                                       <i class="icon-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile;
                            $discount = $_SESSION['discount'] ?? 0;
                            $net = max(0, $grand - $discount);
                        ?>
                            <tr>
                                <th colspan="4" class="text-right">Total brut</th>
                                <th colspan="2"><?php echo number_format($grand,2); ?></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-right">Remise</th>
                                <th colspan="2"><?php echo number_format($discount,2); ?></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-right" style="color:green">Total net</th>
                                <th colspan="2" style="color:green"><?php echo number_format($net,2); ?></th>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center" style="color:red">
                                Panier vide
                            </td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div></div>
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
