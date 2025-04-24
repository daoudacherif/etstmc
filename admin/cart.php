<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

/**
 * Fonction pour obtenir le token d'accès OAuth2 de Nimba via cURL
 */
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token";
    $client_id = "1608e90e20415c7edf0226bf86e7effd";
    $client_secret = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";

    $credentials = base64_encode($client_id . ":" . $client_secret);
    
    $headers = array(
        "Authorization: Basic " . $credentials,
        "Content-Type: application/x-www-form-urlencoded"
    );
    
    $postData = http_build_query(array("grant_type" => "client_credentials"));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === FALSE) {
        error_log("Erreur cURL: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);

    if ($httpCode != 200) {
        error_log("Erreur API. Code HTTP: $httpCode. Réponse: $response");
        return false;
    }
    
    $decoded = json_decode($response, true);
    return $decoded['access_token'] ?? false;
}

/**
 * Fonction pour envoyer un SMS via l'API Nimba
 */
function sendSmsNotification($to, $message) {
    $url = "https://api.nimbasms.com/v1/messages";
    $service_id = "1608e90e20415c7edf0226bf86e7effd";
    $secret_token = "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs";
    
    $authString = base64_encode($service_id . ":" . $secret_token);
    $postData = json_encode(array(
        "to" => array($to),
        "message" => $message,
        "sender_name" => "SMS 9080"
    ));
    
    $headers = array(
        "Authorization: Basic " . $authString,
        "Content-Type: application/json"
    );
    
    $options = array(
        "http" => array(
            "method" => "POST",
            "header" => implode("\r\n", $headers),
            "content" => $postData,
            "ignore_errors" => true
        )
    );
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    $status_line = $http_response_header[0] ?? '';
    preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
    $status_code = $match[1] ?? 0;
    
    return $status_code == 201;
}

// Vérification de la session admin
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
    exit;
}

// Récupération de la liste des produits
$allProdQuery = mysqli_query($con, "SELECT ProductName FROM tblproducts ORDER BY ProductName ASC");
$productNames = array();
while ($rowProd = mysqli_fetch_assoc($allProdQuery)) {
    $productNames[] = $rowProd['ProductName'];
}

// Gestion de l'ajout au panier avec vérification du stock
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);

    // Vérification du stock
    $stockQuery = mysqli_query($con, "SELECT StockQty FROM tblproducts WHERE ID='$productId'");
    $stockData = mysqli_fetch_assoc($stockQuery);
    $stockQty = $stockData['StockQty'];

    if ($stockQty <= 0) {
        echo "<script>alert('Ce produit est en rupture de stock!');</script>";
        echo "<script>window.location.href='cart.php'</script>";
        exit;
    }

    if ($quantity > $stockQty) {
        echo "<script>alert('Quantité demandée non disponible. Stock restant: $stockQty');</script>";
        echo "<script>window.location.href='cart.php'</script>";
        exit;
    }

    // Vérification si le produit est déjà dans le panier
    $checkCart = mysqli_query($con, "SELECT ID, ProductQty FROM tblcart WHERE ProductId='$productId' AND IsCheckOut=0 LIMIT 1");
    
    if (mysqli_num_rows($checkCart) > 0) {
        $row = mysqli_fetch_assoc($checkCart);
        $newQty = $row['ProductQty'] + $quantity;
        
        if ($newQty > $stockQty) {
            echo "<script>alert('Vous avez déjà ce produit dans votre panier. Quantité totale demandée dépasse le stock disponible.');</script>";
            echo "<script>window.location.href='cart.php'</script>";
            exit;
        }
        
        mysqli_query($con, "UPDATE tblcart SET ProductQty='$newQty', Price='$price' WHERE ID='{$row['ID']}'");
    } else {
        mysqli_query($con, "INSERT INTO tblcart(ProductId, ProductQty, Price, IsCheckOut) VALUES('$productId','$quantity','$price','0')");
    }
    
    echo "<script>alert('Produit ajouté au panier avec succès!');</script>";
    echo "<script>window.location.href='cart.php'</script>";
    exit;
}

// ... [Le reste du code pour la suppression, remise et checkout reste inchangé] ...
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion des stocks | Panier</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css">
    <link rel="stylesheet" href="css/matrix-style.css">
    <link rel="stylesheet" href="css/matrix-media.css">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
</head>
<body>

<!-- Header -->
<div id="header">
    <h1><a href="dashboard.php">Système de Gestion</a></h1>
</div>

<!-- Sidebar -->
<?php include_once('includes/sidebar.php'); ?>

<!-- Main Content -->
<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a href="cart.php" class="current">Panier</a>
        </div>
        <h1>Gestion du Panier</h1>
    </div>

    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span12">
                <!-- Formulaire de recherche -->
                <form method="get" action="cart.php" class="form-inline">
                    <input type="text" name="searchTerm" placeholder="Rechercher un produit..." list="productsList" class="span3">
                    <datalist id="productsList">
                        <?php foreach ($productNames as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <button type="submit" class="btn btn-primary">Rechercher</button>
                </form>

                <!-- Résultats de recherche -->
                <?php if (!empty($_GET['searchTerm'])): ?>
                    <?php
                    $searchTerm = mysqli_real_escape_string($con, $_GET['searchTerm']);
                    $query = "SELECT p.*, c.CategoryName, s.SubCategoryName 
                              FROM tblproducts p
                              LEFT JOIN tblcategory c ON c.ID = p.CatID
                              LEFT JOIN tblsubcategory s ON s.ID = p.SubcatID
                              WHERE p.ProductName LIKE '%$searchTerm%' OR p.ModelNumber LIKE '%$searchTerm%'";
                    $result = mysqli_query($con, $query);
                    ?>
                    
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th>Sous-catégorie</th>
                                <th>Marque</th>
                                <th>Modèle</th>
                                <th>Prix</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while ($product = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($product['ProductName']) ?></td>
                                    <td><?= htmlspecialchars($product['CategoryName']) ?></td>
                                    <td><?= htmlspecialchars($product['SubCategoryName']) ?></td>
                                    <td><?= htmlspecialchars($product['BrandName']) ?></td>
                                    <td><?= htmlspecialchars($product['ModelNumber']) ?></td>
                                    <td><?= number_format($product['Price'], 2) ?></td>
                                    <td><?= $product['StockQty'] ?></td>
                                    <td>
                                        <?php if ($product['StockQty'] > 0): ?>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="productid" value="<?= $product['ID'] ?>">
                                                <input type="number" name="price" value="<?= $product['Price'] ?>" step="0.01" min="0" style="width:80px;">
                                                <input type="number" name="quantity" value="1" min="1" max="<?= $product['StockQty'] ?>" style="width:60px;">
                                                <button type="submit" name="addtocart" class="btn btn-success btn-small">
                                                    <i class="icon-plus"></i> Ajouter
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="label label-important">Rupture</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Gestion du panier -->
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-shopping-cart"></i></span>
                        <h5>Votre Panier</h5>
                    </div>
                    <div class="widget-content">
                        <!-- ... [Code existant pour afficher le panier] ... -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<div id="footer">
    <div class="container-fluid">
        <div class="row-fluid">
            <div class="span12">
                &copy; <?= date('Y') ?> Système de Gestion. Tous droits réservés.
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/matrix.js"></script>
</body>
</html>