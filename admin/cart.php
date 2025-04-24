<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// -- Obtenir un token OAuth et le mettre en cache --
function getAccessToken() {
    if (isset($_SESSION['nimba_token']) && $_SESSION['nimba_token_expire'] > time()) {
        return $_SESSION['nimba_token'];
    }

    $url = "https://api.nimbasms.com/v1/oauth/token";
    $client_id = "1608e90e20415c7edf0226bf86e7effd";
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    $credentials = base64_encode("{$client_id}:{$client_secret}");

    $headers = [
        "Authorization: Basic {$credentials}",
        "Content-Type: application/x-www-form-urlencoded"
    ];

    $postData = http_build_query(['grant_type' => 'client_credentials']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Erreur token HTTP {$httpCode}: {$response}");
        return false;
    }

    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        $_SESSION['nimba_token'] = $data['access_token'];
        $_SESSION['nimba_token_expire'] = time() + $data['expires_in'] - 60;
        return $data['access_token'];
    }

    return false;
}

// -- Envoi du SMS avec cURL --
function sendSmsNotification($to, $message) {
    $token = getAccessToken();
    if (!$token) return false;

    $url = "https://api.nimbasms.com/v1/messages";
    $payload = json_encode([
        'to'         => [$to],
        'message'    => $message,
        'sender_name'=> 'SMS 9080'
    ]);
    $headers = [
        "Authorization: Bearer {$token}",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 201;
}

// -- Sécurité session --
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// -- Logique panier (ajout, suppression, remise) --
if (isset($_POST['addtocart'])) {
    // Gérer ajout au panier ici...
}
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    mysqli_query($con, "DELETE FROM tblcart WHERE ID=$delid");
}
if (isset($_POST['applyDiscount'])) {
    $_SESSION['discount'] = floatval($_POST['discount']);
}

// -- Paiement et génération facture --
if (isset($_POST['submit'])) {
    $custname = mysqli_real_escape_string($con, trim($_POST['customername']));
    $raw = trim($_POST['mobilenumber']);
    $custmobile = preg_replace('/[^\d+]/', '', $raw);

    // Normalisation numéro
    if (preg_match('/^0(\d{8,9})$/', $custmobile, $m)) {
        $custmobile = '+224' . $m[1];
    } elseif (preg_match('/^224(\d{8,9})$/', $custmobile, $m)) {
        $custmobile = '+224' . $m[1];
    } elseif (!preg_match('/^\+224\d{8,9}$/', $custmobile)) {
        echo "<script>alert('Format de numéro invalide');window.location.href='cart.php';</script>";
        exit;
    }

    $modepayment = mysqli_real_escape_string($con, $_POST['modepayment']);
    $discount = $_SESSION['discount'] ?? 0;

    $cartQ = mysqli_query($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut = 0");
    $grand = 0;
    while ($r = mysqli_fetch_assoc($cartQ)) {
        $grand += $r['ProductQty'] * $r['Price'];
    }
    $netTotal = max(0, $grand - $discount);
    $billNum = mt_rand(100000000, 999999999);

    mysqli_begin_transaction($con);
    try {
        mysqli_query($con, "UPDATE tblcart SET BillingId='{$billNum}',IsCheckOut=1 WHERE IsCheckOut=0");
        mysqli_query($con, "INSERT INTO tblcustomer(BillingNumber,CustomerName,MobileNumber,ModeofPayment,FinalAmount)
            VALUES('{$billNum}','{$custname}','{$custmobile}','{$modepayment}','{$netTotal}')");
        mysqli_commit($con);

        $_SESSION['invoiceid'] = $billNum;
        unset($_SESSION['discount']);

        $smsOK = sendSmsNotification($custmobile, "Bonjour {$custname}, facture No: {$billNum} validée.");
        $msg = $smsOK ? 'SMS envoyé' : 'Échec SMS';
        echo "<script>alert('Facture {$billNum}\n{$msg}');location='invoice.php';</script>";
        exit;

    } catch (Exception $e) {
        mysqli_rollback($con);
        error_log('Erreur transaction: ' . $e->getMessage());
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

        <!-- ========== AFFICHAGE DES RÉSULTATS DE RECHERCHE ========== -->
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
                                    <th>Nom du produit</th>
                                    <th>Catégorie</th>
                                    <th>Sous-catégorie</th>
                                    <th>Marque</th>
                                    <th>Modèle</th>
                                    <th>Prix par défaut</th>
                                    <th>Prix personnalisé</th>
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
                                            <form method="post" action="cart.php" style="margin:0;">
                                                <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>" />
                                                <input type="number" name="price" step="any" value="<?php echo $row['Price']; ?>" style="width:80px;" />
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