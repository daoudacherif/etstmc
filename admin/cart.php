<?php
session_start();
// Affiche toutes les erreurs (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('includes/dbconnection.php');

/**
 * Function to obtain the OAuth2 access token from Nimba using cURL.
 */
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token";  // Verify this URL with your Nimba documentation.
    
    // Replace with your real credentials
    $client_id     = "1608e90e20415c7edf0226bf86e7effd";      
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    
    // Encode the credentials in Base64 ("client_id:client_secret")
    $credentials = base64_encode($client_id . ":" . $client_secret);
    
    $headers = array(
        "Authorization: Basic " . $credentials,
        "Content-Type: application/x-www-form-urlencoded"
    );
    
    $postData = http_build_query(array(
        "grant_type" => "client_credentials"
    ));
    
    // Use cURL for the POST request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // For development only!
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === FALSE) {
        $error = curl_error($ch);
        error_log("cURL error while obtaining token: " . $error);
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    
    if ($httpCode != 200) {
        error_log("Error obtaining access token. HTTP Code: $httpCode. Response: $response");
        return false;
    }
    
    $decoded = json_decode($response, true);
    if (!isset($decoded['access_token'])) {
        error_log("API error (token): " . print_r($decoded, true));
        return false;
    }
    return $decoded['access_token'];
}

/**
 * Function to send an SMS via the Nimba API.
 * The message content is passed via the $message parameter.
 * The payload sent is logged so you can verify the SMS content.
 */
function sendSmsNotification($to, $message) {
    // Nimba API endpoint for sending SMS
    $url = "https://api.nimbasms.com/v1/messages";
    
    // Replace with your actual service credentials (as provided by Nimba)
    $service_id    = "1608e90e20415c7edf0226bf86e7effd";    
    $secret_token  = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    
    // Build the Basic Auth string (Base64 of "service_id:secret_token")
    $authString = base64_encode($service_id . ":" . $secret_token);
    
    // Prepare the JSON payload with recipient, message and sender_name
    $payload = array(
        "to"          => array($to),
        "message"     => $message,
        "sender_name" => "SMS 9080"   // Replace with your approved sender name with Nimba
    );
    $postData = json_encode($payload);
    
    // Log the payload for debugging (check your server error logs)
    error_log("Nimba SMS Payload: " . $postData);
    
    $headers = array(
        "Authorization: Basic " . $authString,
        "Content-Type: application/json"
    );
    
    $options = array(
        "http" => array(
            "method"        => "POST",
            "header"        => implode("\r\n", $headers),
            "content"       => $postData,
            "ignore_errors" => true
        )
    );
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    // Log complete API response for debugging
    error_log("Nimba API SMS Response: " . $response);
    
    // Retrieve HTTP status code from response headers
    $http_response_header = isset($http_response_header) ? $http_response_header : array();
    $status_line = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
    $status_code = isset($match[1]) ? $match[1] : 0;
    
    if ($status_code != 201) {
        error_log("SMS send failed. HTTP Code: $status_code. Details: " . print_r(json_decode($response, true), true));
        return false;
    }
    
    return true;
}

// Initialiser la variable de remise
$discount = isset($_SESSION['discount']) ? $_SESSION['discount'] : 0;

// Traitement de l'application de remise
if (isset($_POST['applyDiscount'])) {
    $discount = max(0, floatval($_POST['discount']));
    $_SESSION['discount'] = $discount;
    // Redirection pour éviter re-soumission du formulaire
    header("Location: cart.php");
    exit;
}

// Traitement de la suppression d'un élément du panier
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    $deleteQuery = mysqli_query($con, "DELETE FROM tblcart WHERE ID = $delid AND IsCheckOut = 0");
    if ($deleteQuery) {
        echo "<script>
                alert('Article retiré du panier');
                window.location.href='cart.php';
              </script>";
        exit;
    }
}

//////////////////////////////////////////////////////////////////////////
// GESTION DE L'AJOUT AU PANIER
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
                alert('Article introuvable');
                window.location.href='cart.php';
              </script>";
        exit;
    }
    $row   = mysqli_fetch_assoc($stockRes);
    $stock = intval($row['Stock']);

    // 2) Interdire si stock épuisé ou négatif
    if ($stock <= 0) {
        echo "<script>
                alert('Désolé, ce Article est en rupture de stock.');
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
        
        // Vérifier à nouveau que le nouveau total ne dépasse pas le stock disponible
        if ($newQty > $stock) {
            echo "<script>
                    alert('Vous avez déjà ' + {$c['ProductQty']} + ' exemplaire(s) dans votre panier. Ajouter ' + $quantity + ' de plus dépasserait le stock disponible.');
                    window.location.href='cart.php';
                  </script>";
            exit;
        }
        
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
            alert('Article ajouté au panier !');
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

    // Vérifier à nouveau les stocks avant validation
    $stockCheck = mysqli_query($con, "
        SELECT c.ProductId, c.ProductQty, p.Stock, p.ProductName
        FROM tblcart c
        JOIN tblproducts p ON p.ID = c.ProductId
        WHERE c.IsCheckOut = 0
    ");
    
    $stockError = false;
    $errorMessages = [];
    
    while ($item = mysqli_fetch_assoc($stockCheck)) {
        if ($item['ProductQty'] > $item['Stock']) {
            $stockError = true;
            $errorMessages[] = "Article '{$item['ProductName']}': Quantité demandée ({$item['ProductQty']}) supérieure au stock disponible ({$item['Stock']})";
        }
        
        if ($item['Stock'] <= 0) {
            $stockError = true;
            $errorMessages[] = "Article '{$item['ProductName']}' est en rupture de stock";
        }
    }
    
    if ($stockError) {
        $errorMsg = "Problèmes de stock identifiés:\\n" . implode("\\n", $errorMessages);
        echo "<script>alert('$errorMsg');</script>";
        exit;
    }

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

// Récupérer les noms de Articles pour le datalist
$productNamesQuery = mysqli_query($con, "SELECT DISTINCT ProductName FROM tblproducts ORDER BY ProductName ASC");
$productNames = array();
if ($productNamesQuery) {
    while ($row = mysqli_fetch_assoc($productNamesQuery)) {
        $productNames[] = $row['ProductName'];
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
            <a href="cart.php" class="current">Panier de Articles</a>
        </div>
        <h1>Panier de Articles</h1>
    </div>

    <div class="container-fluid">
        <hr>
        <!-- ========== FORMULAIRE DE RECHERCHE (avec datalist) ========== -->
        <div class="row-fluid">
            <div class="span12">
                <form method="get" action="cart.php" class="form-inline">
                    <label>Rechercher des Articles :</label>
                    <input type="text" name="searchTerm" class="span3" placeholder="Nom du Article..." list="productsList" />
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
            c.CategoryName
        FROM tblproducts p
        LEFT JOIN tblcategory c 
            ON p.ID = c.ID
        WHERE 
            p.ProductName LIKE ?
            OR p.ModelNumber LIKE ?
    ";

    // Utiliser les requêtes préparées pour prévenir les injections SQL
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        die("MySQL prepare error: " . mysqli_error($con));
    }
    
    $searchParam = "%$searchTerm%";
    mysqli_stmt_bind_param($stmt, "ss", $searchParam, $searchParam);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    
    if (!$res) {
        die("MySQL error: " . mysqli_error($con));
    }

    $count = mysqli_num_rows($res);
}
?>

    <div class="row-fluid">
        <div class="span12">
            <?php if (!empty($_GET['searchTerm'])): ?>
            <h4>Résultats de recherche pour "<em><?= htmlspecialchars($_GET['searchTerm']) ?></em>"</h4>

            <?php if ($count > 0): ?>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom du Article</th>
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
                <p style="color:red;">Aucun Article correspondant trouvé.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

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
                        <h5>Articles dans le panier</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <table class="table table-bordered" style="font-size: 15px">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Nom du Article</th>
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
                                    tblproducts.ProductName,
                                    tblproducts.Stock
                                  FROM tblcart
                                  LEFT JOIN tblproducts ON tblproducts.ID = tblcart.ProductId
                                  WHERE tblcart.IsCheckOut = 0
                                  ORDER BY tblcart.ID ASC
                                ");
                                $cnt = 1;
                                $grandTotal = 0;
                                $num = mysqli_num_rows($ret);
                                $stockWarning = false;
                                
                                if ($num > 0) {
                                    while ($row = mysqli_fetch_array($ret)) {
                                        $pq = $row['ProductQty'];
                                        $ppu = $row['cartPrice'];
                                        $lineTotal = $pq * $ppu;
                                        $grandTotal += $lineTotal;
                                        
                                        // Vérifier si le stock actuel est suffisant
                                        $stockSuffisant = $row['Stock'] >= $pq;
                                        if (!$stockSuffisant) {
                                            $stockWarning = true;
                                        }
                                        ?>
                                        <tr class="gradeX <?php echo !$stockSuffisant ? 'error' : ''; ?>">
                                            <td><?php echo $cnt; ?></td>
                                            <td>
                                                <?php echo $row['ProductName']; ?>
                                                <?php if (!$stockSuffisant): ?>
                                                    <br><span class="label label-important">Stock insuffisant!</span>
                                                <?php endif; ?>
                                            </td>
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
                                    // Ajouter un message d'avertissement si des Articles ont un stock insuffisant
                                    if ($stockWarning): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: red; font-weight: bold;">
                                            Attention! Certains Articles n'ont pas un stock suffisant. Veuillez ajuster votre panier.
                                        </td>
                                    </tr>
                                    <script>
                                        // Désactiver le bouton de paiement si stock insuffisant
                                        document.addEventListener('DOMContentLoaded', function() {
                                            document.querySelector('button[name="submit"]').disabled = true;
                                            document.querySelector('button[name="submit"]').title = "Impossible de finaliser: stock insuffisant";
                                        });
                                    </script>
                                    <?php endif; ?>
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