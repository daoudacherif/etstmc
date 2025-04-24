<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// 1. Récupérer token OAuth2 (Nimba)
function getAccessToken() {
    $url           = "https://api.nimbasms.com/v1/oauth/token";
    $client_id     = getenv('NIMBA_CLIENT_ID');
    $client_secret = getenv('NIMBA_CLIENT_SECRET');
    $credentials   = base64_encode("{$client_id}:{$client_secret}");

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['grant_type'=>'client_credentials']),
        CURLOPT_HTTPHEADER     => ["Authorization: Basic {$credentials}", "Content-Type: application/x-www-form-urlencoded"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return false;
    }
    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

// 2. Envoyer SMS (Nimba)
function sendSmsNotification($to, $message) {
    $url           = "https://api.nimbasms.com/v1/messages";
    $service_id    = getenv('NIMBA_CLIENT_ID');
    $secret_token  = getenv('NIMBA_CLIENT_SECRET');
    $auth          = base64_encode("{$service_id}:{$secret_token}");

    $payload = json_encode([
        'to'          => [$to],
        'message'     => $message,
        'sender_name' => 'SMS 9080',
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: Basic {$auth}\r\nContent-Type: application/json",
            'content' => $payload,
            'ignore_errors' => true,
        ],
    ];
    $resp = file_get_contents($url, false, stream_context_create($opts));
    foreach ($http_response_header as $hdr) {
        if (preg_match('{HTTP\/\S+\s(\d{3})}', $hdr, $m)) {
            return ($m[1] === '201');
        }
    }
    return false;
}

// Session admin
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// Liste produits (datalist)
$prodQ = mysqli_query($con, "SELECT ID, ProductName, Stock FROM tblproducts ORDER BY ProductName");
$productNames = mysqli_fetch_all($prodQ, MYSQLI_ASSOC);

// Ajout au panier
if (isset($_POST['addtocart'])) {
    $pid   = intval($_POST['productid']);
    $qty   = max(1, intval($_POST['quantity']));
    $price = max(0, floatval($_POST['price']));

    // Contrôle stock
    $sr = mysqli_query($con, "SELECT Stock FROM tblproducts WHERE ID=$pid");
    $stock = intval(mysqli_fetch_assoc($sr)['Stock'] ?? 0);
    if ($stock <= 0) {
        echo "<script>alert('Produit en rupture de stock');location='cart.php';</script>";
        exit;
    }
    if ($qty > $stock) {
        echo "<script>alert('Quantité demandée supérieur au stock');location='cart.php';</script>";
        exit;
    }

    // Insert/update panier
    $chk = mysqli_query($con, "SELECT ID, ProductQty FROM tblcart WHERE ProductId=$pid AND IsCheckOut=0");
    if (mysqli_num_rows($chk)) {
        $c = mysqli_fetch_assoc($chk);
        $newQty = $c['ProductQty'] + $qty;
        mysqli_query($con, "UPDATE tblcart SET ProductQty=$newQty, Price=$price WHERE ID={$c['ID']}");
    } else {
        mysqli_query($con, "INSERT INTO tblcart(ProductId,ProductQty,Price,IsCheckOut) VALUES($pid,$qty,$price,0)");
    }
    echo "<script>alert('Produit ajouté au panier');location='cart.php';</script>";
    exit;
}

// Retirer du panier
if (isset($_GET['delid'])) {
    $rid = intval($_GET['delid']);
    mysqli_query($con, "DELETE FROM tblcart WHERE ID=$rid");
    echo "<script>alert('Produit retiré');location='cart.php';</script>";
    exit;
}

// Appliquer remise
if (isset($_POST['applyDiscount'])) {
    $_SESSION['discount'] = max(0, floatval($_POST['discount']));
    header('location:cart.php');
    exit;
}
$discount = $_SESSION['discount'] ?? 0;

// Checkout
if (isset($_POST['submit'])) {
    $name   = mysqli_real_escape_string($con, $_POST['customername']);
    $phone  = mysqli_real_escape_string($con, $_POST['mobilenumber']);
    $mode   = mysqli_real_escape_string($con, $_POST['modepayment']);

    $cq = mysqli_query($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=0");
    $total = 0;
    while ($r = mysqli_fetch_assoc($cq)) {
        $total += $r['ProductQty'] * $r['Price'];
    }
    $net = max(0, $total - $discount);
    $bill = mt_rand(100000000,999999999);

    $sql  = "UPDATE tblcart SET BillingId=$bill,IsCheckOut=1 WHERE IsCheckOut=0;";
    $sql .= "INSERT INTO tblcustomer(BillingNumber,CustomerName,MobileNumber,ModeofPayment,FinalAmount) ";
    $sql .= "VALUES($bill,'$name','$phone','$mode',$net);";

    if (mysqli_multi_query($con, $sql)) {
        $_SESSION['invoiceid'] = $bill;
        unset($_SESSION['discount']);
        $smsOK = sendSmsNotification($phone, "Bonjour $name, commande #$bill validée.");
        $msg = $smsOK ? 'SMS envoyé' : 'Échec SMS';
        echo "<script>alert('Facture $bill - $msg');location='invoice.php';</script>";
        exit;
    } else {
        echo "<script>alert('Erreur paiement');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panier de produits</title>
    <?php include('includes/cs.php'); ?>
    <?php include('includes/responsive.php'); ?>
</head>
<body>
    <?php include('includes/header.php'); ?>
    <?php include('includes/sidebar.php'); ?>
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
            <form method="get" class="form-inline mb-3">
                <label>Rechercher :</label>
                <input list="productsList" name="searchTerm" class="span3" placeholder="Nom produit...">
                <datalist id="productsList">
                    <?php foreach ($productNames as $p): ?>
                        <option data-stock="<?= $p['Stock'] ?>" value="<?= htmlspecialchars($p['ProductName']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <button class="btn btn-primary">Rechercher</button>
            </form>

            <?php if (!empty($_GET['searchTerm'])):
                $t = mysqli_real_escape_string($con, $_GET['searchTerm']);
                $qr = mysqli_query($con, "SELECT p.ID,p.ProductName,p.BrandName,p.ModelNumber,p.Price,p.Stock,c.CategoryName,s.SubCategoryName
                    FROM tblproducts p
                    LEFT JOIN tblcategory c ON c.ID=p.CatID
                    LEFT JOIN tblsubcategory s ON s.ID=p.SubcatID
                    WHERE p.ProductName LIKE '%$t%' OR p.ModelNumber LIKE '%$t%'"
                );
                $n = mysqli_num_rows($qr);
            ?>
                <h4>Résultats (<?= $n ?>)</h4>
                <?php if ($n>0): ?>
                    <table class="table table-bordered table-striped">
                        <thead><tr>
                            <th>#</th><th>Produit</th><th>Catégorie</th><th>Sous-cat</th><th>Marque</th>
                            <th>Modèle</th><th>Prix</th><th>Stock</th><th>Quantité</th><th>+</th>
                        </tr></thead>
                        <tbody>
                        <?php $i=1; while($r=mysqli_fetch_assoc($qr)): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($r['ProductName']) ?></td>
                                <td><?= htmlspecialchars($r['CategoryName']) ?></td>
                                <td><?= htmlspecialchars($r['SubCategoryName']) ?></td>
                                <td><?= htmlspecialchars($r['BrandName']) ?></td>
                                <td><?= htmlspecialchars($r['ModelNumber']) ?></td>
                                <td><?= number_format($r['Price'],2) ?></td>
                                <td><?= intval($r['Stock']) ?></td>
                                <?php if($r['Stock']>0): ?>
                                <td colspan="2">
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="productid" value="<?= $r['ID'] ?>">
                                        <input type="number" name="price" value="<?= $r['Price'] ?>" step="any" class="span2">
                                        <input type="number" name="quantity" value="1" min="1" max="<?= $r['Stock'] ?>" class="span1">
                                        <button name="addtocart" class="btn btn-success btn-small"><i class="icon-plus"></i></button>
                                    </form>
                                </td>
                                <?php else: ?>
                                <td colspan="2"><span class="text-danger">Rupture</span></td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-danger">Aucun produit trouvé.</p>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Remise & Checkout & Panier actuel -->
            <?php include('includes/cart_footer.php'); ?>
        </div>
    </div>
    <?php include('includes/footer.php'); ?>
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/matrix.js"></script>
    <script src="js/matrix.tables.js"></script>
</body>
</html>
