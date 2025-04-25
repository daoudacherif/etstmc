<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

/**
 * 1. Fonction pour obtenir le token d'accès OAuth2 de Nimba via cURL.
 */
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token"; // Vérifiez l'URL selon votre documentation Nimba.
    
    // Vérifiez que ces informations sont exactes et actives
    $client_id = "1608e90e20415c7edf0226bf86e7effd";      // Exemple: "1608e90e20415c7edf0226bf86e7effd"
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";  // Exemple: "4Up9v9s_Wzo6kj..."

    // Calcul de l'authentification de base en encodant "client_id:client_secret"
    $credentials = base64_encode($client_id . ":" . $client_secret);
    
    $headers = array(
        "Authorization: Basic " . $credentials,
        "Content-Type: application/x-www-form-urlencoded"
    );
    
    $postData = http_build_query(array(
        "grant_type" => "client_credentials"
    ));
    
    // Utilisation de cURL pour effectuer la requête POST
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // À utiliser en dev seulement
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === FALSE) {
        $error = curl_error($ch);
        error_log("Erreur cURL lors de l'obtention du token: " . $error);
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);

    if ($httpCode != 200) {
        error_log("Erreur lors de la récupération du token d'accès. Code HTTP: $httpCode. Réponse: $response");
        return false;
    }
    
    $decoded = json_decode($response, true);
    if (!isset($decoded['access_token'])) {
        error_log("Erreur API (token): " . print_r($decoded, true));
        return false;
    }
    return $decoded['access_token'];
}

/**
 * 2. Fonction pour envoyer un SMS via l'API Nimba.
 */
function sendSmsNotification($to, $message) {
    // URL de l'API d'envoi de SMS (vérifiez l'URL selon votre documentation Nimba)
    $url = "https://api.nimbasms.com/v1/messages";
    
    // Vérifiez que ces informations sont exactes et actives
    $service_id    = "1608e90e20415c7edf0226bf86e7effd";     // Exemple: "1608e90e20415c7edf0226bf86e7effd"
    $secret_token  = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";   // Exemple: "4Up9v9s_Wzo6kj..."
    
    // Calcul de l'authentification de base
    $authString = base64_encode($service_id . ":" . $secret_token);
    
    // Préparez le corps de la requête avec le format requis par l'API Nimba
    // Ici, on peut inclure aussi "sender_name" si nécessaire
    $postData = json_encode(array(
        "to"          => array($to),
        "message"     => $message,
        "sender_name" => "SMS 9080"  // Remplacez par le sender_name autorisé auprès de Nimba
    ));
    
    $headers = array(
        "Authorization: Basic " . $authString,
        "Content-Type: application/json"
    );
    
    // Nous utilisons file_get_contents avec un contexte HTTP, vous pouvez également passer en cURL
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
    
    // Journaliser la réponse complète pour debugging
    error_log("Réponse API SMS: " . $response);
    
    // Récupération du code HTTP depuis les en-têtes de réponse
    $http_response_header = isset($http_response_header) ? $http_response_header : array();
    $status_line = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
    $status_code = isset($match[1]) ? $match[1] : 0;
    
    if ($status_code != 201) {
        error_log("Échec de l'envoi du SMS. Code HTTP: $status_code. Détails: " . print_r(json_decode($response, true), true));
        return false;
    }
    
    return true;
}

/**
 * Reste du code de gestion du panier et du checkout
 */

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
    exit;
}

// 1) Préparer la liste de tous les produits pour le <datalist>
$allProdQuery = mysqli_query($con, "SELECT ProductName FROM tblproducts ORDER BY ProductName ASC");
$productNames = array();
while ($rowProd = mysqli_fetch_assoc($allProdQuery)) {
    $productNames[] = $rowProd['ProductName'];
}

// 2) Ajouter un produit au panier
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity  = intval($_POST['quantity']);
    $price     = floatval($_POST['price']);  // Prix saisi manuellement
    
    if ($quantity <= 0) {
        $quantity = 1;
    }
    if ($price < 0) {
        $price = 0;
    }
    
   // Vérifier le stock disponible avant l'ajout 
$productId = mysqli_real_escape_string($con, $productId);

// 1. Récupérer le stock initial depuis la table produits
$stockQuery = mysqli_query($con, "SELECT Stock FROM tblproducts WHERE ID='$productId' LIMIT 1");
$stockRow   = mysqli_fetch_assoc($stockQuery);
$initialStock = intval($stockRow['Stock'] ?? 0);

// 2. Récupérer la quantité totale vendue (checkout = 1)
$soldQuery = mysqli_query($con, 
    "SELECT SUM(Quantity) AS TotalSold 
     FROM tblcart 
     WHERE ProductID='$productId' AND IsCheckOut = 1");
$soldRow   = mysqli_fetch_assoc($soldQuery);
$totalSold = intval($soldRow['TotalSold'] ?? 0);

// 3. Calculer le stock restant après ventes
$remainingStock = max(0, $initialStock - $totalSold);

// 4. Récupérer la quantité réservée (non check-out)
$reservedQuery = mysqli_query($con, 
    "SELECT SUM(Quantity) AS TotalReserved 
     FROM tblcart 
     WHERE ProductID='$productId' AND IsCheckOut = 0");
$reservedRow   = mysqli_fetch_assoc($reservedQuery);
$totalReserved = intval($reservedRow['TotalReserved'] ?? 0);

// 5. Calculer le stock disponible (restant – réservé)
$availableStock = max(0, $remainingStock - $totalReserved);

// 6. Si pas assez de stock
if ($availableStock <= 0) {
    echo "<script>alert('Impossible d\\'ajouter ce produit : stock épuisé!');</script>";
    echo "<script>window.location.href='cart.php'</script>";
    exit;
}

// À ce point, $availableStock contient bien le stock réellement disponible


// Vérifier la quantité demandée
if ($quantity > $availableStock) {
    echo "<script>alert('Quantité demandée non disponible! Stock disponible: $availableStock');</script>";
    echo "<script>window.location.href='cart.php'</script>";
    exit;
}
    // Vérifier si ce produit est déjà dans le panier
    $checkCart = mysqli_query($con, "SELECT ID, ProductQty FROM tblcart WHERE ProductId='$productId' AND IsCheckOut=0 LIMIT 1");
    
    if (mysqli_num_rows($checkCart) > 0) {
        // Mise à jour de la quantité
        $row = mysqli_fetch_assoc($checkCart);
        $cartId = $row['ID'];
        $oldQty = $row['ProductQty'];
        $newQty = $oldQty + $quantity;
        
        // Vérifier que la nouvelle quantité ne dépasse pas le stock disponible
        if ($newQty > $availableStock) {
            echo "<script>alert('Impossible d\'ajouter cette quantité! Stock disponible: " . $availableStock . "');</script>";
            echo "<script>window.location.href='cart.php'</script>";
            exit;
        }
        
        mysqli_query($con, "UPDATE tblcart SET ProductQty='$newQty', Price='$price' WHERE ID='$cartId'");
    } else {
        // Insérer un nouvel article dans le panier
        mysqli_query($con, "INSERT INTO tblcart(ProductId, ProductQty, Price, IsCheckOut) VALUES('$productId', '$quantity', '$price', '0')");
    }
    echo "<script>alert('Produit ajouté au panier!');</script>";
    echo "<script>window.location.href='cart.php'</script>";
    exit;
}

// 3) Retirer un produit du panier
if (isset($_GET['delid'])) {
    $rid = intval($_GET['delid']);
    mysqli_query($con, "DELETE FROM tblcart WHERE ID='$rid'");
    echo "<script>alert('Produit retiré du panier');</script>";
    echo "<script>window.location.href='cart.php'</script>";
    exit;
}

// 4) Gérer la remise (discount) en session
if (isset($_POST['applyDiscount'])) {
    $_SESSION['discount'] = floatval($_POST['discount']);
    echo "<script>window.location.href='cart.php'</script>";
    exit;
}
$discount = isset($_SESSION['discount']) ? $_SESSION['discount'] : 0;

// 5) Validation du panier (checkout) & création de facture
if (isset($_POST['submit'])) {
    $custname = $_POST['customername'];
    $custmobilenum = $_POST['mobilenumber']; // Le numéro de mobile saisi par le client (doit être au format +221xxxxxxxxx)
    $modepayment = $_POST['modepayment'];
    
    // Calculer le total du panier
    $cartQuery = mysqli_query($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=0");
    $grandTotal = 0;
    while ($row = mysqli_fetch_assoc($cartQuery)) {
        $grandTotal += ($row['ProductQty'] * $row['Price']);
    }
    
    // Appliquer la remise
    $netTotal = $grandTotal - $discount;
    if ($netTotal < 0) {
        $netTotal = 0;
    }
    
    // Générer un numéro de facture unique
    $billingnum = mt_rand(100000000, 999999999);
    
    // Récupérer les produits du panier pour mise à jour du stock
    $cartItems = mysqli_query($con, "SELECT ProductId, ProductQty FROM tblcart WHERE IsCheckOut=0");
    
    // Débuter une transaction pour garantir l'intégrité des données
    mysqli_begin_transaction($con);
    
    try {
        // Marquer le panier comme validé et insérer dans tblcustomer
        $query = "INSERT INTO tblcustomer(BillingNumber, CustomerName, MobileNumber, ModeofPayment, FinalAmount) 
                 VALUES('$billingnum', '$custname', '$custmobilenum', '$modepayment', '$netTotal')";
        
        $result = mysqli_query($con, $query);
        
        if (!$result) {
            throw new Exception("Erreur lors de la création de la facture client");
        }
        
        // Marquer tous les articles du panier avec le numéro de facturation
        $updateCart = mysqli_query($con, "UPDATE tblcart SET BillingId='$billingnum', IsCheckOut=1 WHERE IsCheckOut=0");
        
        if (!$updateCart) {
            throw new Exception("Erreur lors de la mise à jour du panier");
        }
        
        // Mise à jour des stocks pour chaque produit
        while ($item = mysqli_fetch_assoc($cartItems)) {
            $prodId = $item['ProductId'];
            $qty = $item['ProductQty'];
            
            // Soustraire la quantité achetée du stock
            $updateStock = mysqli_query($con, "UPDATE tblproducts 
                                              SET Stock = Stock - $qty 
                                              WHERE ID = '$prodId'");
            
            if (!$updateStock) {
                throw new Exception("Erreur lors de la mise à jour du stock pour le produit #" . $prodId);
            }
        }
        
        // Si tout s'est bien passé, on valide la transaction
        mysqli_commit($con);
        
        $_SESSION['invoiceid'] = $billingnum;
        unset($_SESSION['discount']); // Réinitialisation de la remise
        
        // Préparer le SMS personnalisé
        $customerPhone = $custmobilenum; // Assurez-vous qu'il est au format international, ex: "+221787368793"
        $smsMessage = "Bonjour $custname, votre commande (Facture No: $billingnum) a été validée avec succès. Merci pour votre confiance.";
        
        // Envoyer le SMS via l'API Nimba
        $smsResult = sendSmsNotification($customerPhone, $smsMessage);
        if ($smsResult === true) {
            $smsMsg = "SMS envoyé avec succès";
        } else {
            $smsMsg = "Échec d'envoi SMS - Vérifier les logs serveur";
        }
        
        echo "<script>
            alert('Facture créée. Numéro : $billingnum\\n$smsMsg');
            window.location.href='invoice.php';
        </script>";
        exit;
        
    } catch (Exception $e) {
        // En cas d'erreur, on annule toutes les opérations
        mysqli_rollback($con);
        echo "<script>alert('Erreur: " . $e->getMessage() . "');</script>";
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
                SELECT p.ID, p.ProductName, p.BrandName, p.ModelNumber, p.Price, p.Stock,
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
                                    <th>Stock disponible</th>
                                    <th>Prix personnalisé</th>
                                    <th>Quantité</th>
                                    <th>Ajouter</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                while ($row = mysqli_fetch_assoc($res)) {
                                    $stock = intval($row['Stock']);
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo $row['ProductName']; ?></td>
                                        <td><?php echo $row['CategoryName']; ?></td>
                                        <td><?php echo $row['SubCategoryName']; ?></td>
                                        <td><?php echo $row['BrandName']; ?></td>
                                        <td><?php echo $row['ModelNumber']; ?></td>
                                        <td><?php echo $row['Price']; ?></td>
                                        <td><?php echo $stock; ?></td>
                                        <td>
                                            <form method="post" action="cart.php" style="margin:0;">
                                                <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>" />
                                                <input type="number" name="price" step="any" value="<?php echo $row['Price']; ?>" style="width:80px;" <?php if ($stock <= 0) echo 'disabled'; ?> />
                                        </td>
                                        <td>
                                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $stock; ?>" style="width:60px;" <?php if ($stock <= 0) echo 'disabled'; ?> />
                                        </td>
                                        <td>
                                            <button type="submit" name="addtocart" class="btn btn-success btn-small" <?php if ($stock <= 0) echo 'disabled title="Stock épuisé"'; ?>>
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