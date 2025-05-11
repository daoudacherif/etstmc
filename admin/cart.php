<?php
session_start();

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Affiche toutes les erreurs (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('includes/dbconnection.php');

/**
 * Function to obtain the OAuth2 access token from Nimba using cURL.
 */
function getAccessToken() {
    // Idéalement, ces informations devraient être dans un fichier de configuration sécurisé
    $config = [
        'url' => "https://api.nimbasms.com/v1/oauth/token",
        'client_id' => "1608e90e20415c7edf0226bf86e7effd",
        'client_secret' => "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs"
    ];
    
    // Encode the credentials in Base64 ("client_id:client_secret")
    $credentials = base64_encode($config['client_id'] . ":" . $config['client_secret']);
    
    $headers = array(
        "Authorization: Basic " . $credentials,
        "Content-Type: application/x-www-form-urlencoded"
    );
    
    $postData = http_build_query(array(
        "grant_type" => "client_credentials"
    ));
    
    // Use cURL for the POST request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Sécurité SSL activée
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
    // Idéalement, ces informations devraient être dans un fichier de configuration sécurisé
    $config = [
        'url' => "https://api.nimbasms.com/v1/messages",
        'service_id' => "1608e90e20415c7edf0226bf86e7effd",
        'secret_token' => "kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs",
        'sender_name' => "SMS 9080"
    ];
    
    // Build the Basic Auth string (Base64 of "service_id:secret_token")
    $authString = base64_encode($config['service_id'] . ":" . $config['secret_token']);
    
    // Prepare the JSON payload with recipient, message and sender_name
    $payload = array(
        "to"          => array($to),
        "message"     => $message,
        "sender_name" => $config['sender_name']
    );
    $postData = json_encode($payload);
    
    // Log the payload for debugging (check your server error logs)
    error_log("Nimba SMS Payload: " . $postData);
    
    // Version sécurisée avec cURL au lieu de file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Sécurité SSL activée
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic " . $authString,
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === FALSE) {
        $error = curl_error($ch);
        error_log("cURL error while sending SMS: " . $error);
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    
    // Log complete API response for debugging
    error_log("Nimba API SMS Response: " . $response);
    
    if ($httpCode != 201) {
        error_log("SMS send failed. HTTP Code: $httpCode. Details: " . print_r(json_decode($response, true), true));
        return false;
    }
    
    return true;
}

// Vérification CSRF pour toutes les requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed");
        echo "<script>
                alert('Erreur de sécurité. Veuillez réessayer.');
                window.location.href='cart.php';
              </script>";
        exit;
    }
}

// Appliquer une remise (en valeur absolue ou en pourcentage)
if (isset($_POST['applyDiscount'])) {
    $discountValue = max(0, floatval($_POST['discount']));
    
    // Calculer le grand total avant d'appliquer la remise avec une requête préparée
    $grandTotal = 0;
    $cartQuery = mysqli_prepare($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=0");
    mysqli_stmt_execute($cartQuery);
    $result = mysqli_stmt_get_result($cartQuery);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $grandTotal += $row['ProductQty'] * $row['Price'];
    }
    
    // Déterminer si c'est un pourcentage ou une valeur absolue
    $isPercentage = isset($_POST['discountType']) && $_POST['discountType'] === 'percentage';
    
    if ($isPercentage) {
        // Limiter le pourcentage à 100% maximum
        $discountValue = min(100, $discountValue);
        // Calculer la remise en valeur absolue basée sur le pourcentage
        $actualDiscount = ($discountValue / 100) * $grandTotal;
    } else {
        // Remise en valeur absolue (limiter à la valeur du panier)
        $actualDiscount = min($grandTotal, $discountValue);
    }
    
    // Stocker les informations de remise dans la session
    $_SESSION['discount'] = $actualDiscount;
    $_SESSION['discountType'] = $isPercentage ? 'percentage' : 'absolute';
    $_SESSION['discountValue'] = $discountValue;
    
    header("Location: cart.php");
    exit;
}

// Récupérer les informations de remise de la session
$discount = $_SESSION['discount'] ?? 0;
$discountType = $_SESSION['discountType'] ?? 'absolute';
$discountValue = $_SESSION['discountValue'] ?? 0;

// Traitement de la suppression d'un élément du panier
if (isset($_GET['delid']) && isset($_GET['csrf_token'])) {
    // Vérification CSRF pour les requêtes GET critiques
    if ($_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed for cart item deletion");
        echo "<script>
                alert('Erreur de sécurité. Veuillez réessayer.');
                window.location.href='cart.php';
              </script>";
        exit;
    }
    
    $delid = intval($_GET['delid']);
    
    $deleteStmt = mysqli_prepare($con, "DELETE FROM tblcart WHERE ID = ? AND IsCheckOut = 0");
    mysqli_stmt_bind_param($deleteStmt, "i", $delid);
    mysqli_stmt_execute($deleteStmt);
    
    if (mysqli_affected_rows($con) > 0) {
        echo "<script>
                alert('Article retiré du panier');
                window.location.href='cart.php';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Erreur lors de la suppression de l\'article');
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

    // 1) Récupérer le stock restant avec requête préparée
    $stockQuery = "
        SELECT 
            p.Stock AS initial_stock,
            p.ProductName,
            COALESCE(SUM(CASE WHEN cart.IsCheckOut = 1 THEN cart.ProductQty ELSE 0 END), 0) AS sold_qty,
            COALESCE(
                (SELECT SUM(Quantity) FROM tblreturns WHERE ProductID = p.ID),
                0
            ) AS returned_qty,
            COALESCE(
                (SELECT SUM(c.ProductQty) FROM tblcart c WHERE c.ProductId = p.ID AND c.IsCheckOut = 0)
                , 0
            ) AS in_carts_qty
        FROM tblproducts p
        LEFT JOIN tblcart cart ON cart.ProductId = p.ID
        WHERE p.ID = ?
        GROUP BY p.ID
        LIMIT 1
    ";
    
    $stmt = mysqli_prepare($con, $stockQuery);
    if (!$stmt) {
        error_log("Erreur préparation requête stock: " . mysqli_error($con));
        echo "<script>
                alert('Erreur système lors de la vérification du stock');
                window.location.href='cart.php';
              </script>";
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $stockRes = mysqli_stmt_get_result($stmt);
    
    if (!$stockRes || mysqli_num_rows($stockRes) === 0) {
        echo "<script>
                alert('Article introuvable');
                window.location.href='cart.php';
              </script>";
        exit;
    }
    
    $row = mysqli_fetch_assoc($stockRes);
    $initialStock = intval($row['initial_stock']);
    $soldQty = intval($row['sold_qty']);
    $returnedQty = intval($row['returned_qty']);
    $inCartsQty = intval($row['in_carts_qty']);
    $productName = $row['ProductName'];
    
    // Calcul du stock réellement disponible
    $remainingStock = $initialStock - $soldQty + $returnedQty;
    $availableStock = $remainingStock - $inCartsQty; // Stock disponible en tenant compte des paniers actifs
    
    // Vérifier si l'article est déjà dans le panier de l'utilisateur
    $checkCartStmt = mysqli_prepare($con, 
        "SELECT ID, ProductQty FROM tblcart WHERE ProductId = ? AND IsCheckOut = 0 LIMIT 1"
    );
    mysqli_stmt_bind_param($checkCartStmt, "i", $productId);
    mysqli_stmt_execute($checkCartStmt);
    $checkCartResult = mysqli_stmt_get_result($checkCartStmt);
    $currentCartQty = 0;
    $cartItemId = 0;
    
    if (mysqli_num_rows($checkCartResult) > 0) {
        $cartItem = mysqli_fetch_assoc($checkCartResult);
        $currentCartQty = intval($cartItem['ProductQty']);
        $cartItemId = $cartItem['ID'];
    }
    
    // Ajuster le stock disponible en ajoutant ce qui est déjà dans le panier de l'utilisateur
    $availableForUser = $availableStock + $currentCartQty;
    
    // 2) Interdire si stock épuisé ou négatif
    if ($remainingStock <= 0) {
        echo "<script>
                alert('Désolé, cet article \"" . htmlspecialchars($productName) . "\" est en rupture de stock.');
                window.location.href='cart.php';
              </script>";
        exit;
    }

    // 3) Interdire si quantité demandée > stock disponible pour l'utilisateur
    if ($quantity > $availableForUser) {
        echo "<script>
                alert('Vous avez demandé $quantity exemplaire(s) de \"" . htmlspecialchars($productName) . "\", il n\'en reste que $availableForUser disponible(s).');
                window.location.href='cart.php';
              </script>";
        exit;
    }

    // 4) INSERT ou UPDATE dans tblcart avec requêtes préparées
    mysqli_begin_transaction($con); // Démarrer une transaction pour garantir l'intégrité des données
    
    try {
        if ($cartItemId > 0) {
            // Mise à jour de la quantité si l'article est déjà dans le panier
            $newQty = $currentCartQty + $quantity;
            
            $updateStmt = mysqli_prepare($con, 
                "UPDATE tblcart SET ProductQty = ?, Price = ? WHERE ID = ?"
            );
            mysqli_stmt_bind_param($updateStmt, "idi", $newQty, $price, $cartItemId);
            $updateSuccess = mysqli_stmt_execute($updateStmt);
            
            if (!$updateSuccess) {
                throw new Exception("Erreur lors de la mise à jour du panier: " . mysqli_error($con));
            }
        } else {
            // Insertion d'un nouvel article dans le panier
            $insertStmt = mysqli_prepare($con, 
                "INSERT INTO tblcart(ProductId, ProductQty, Price, IsCheckOut) VALUES(?, ?, ?, 0)"
            );
            mysqli_stmt_bind_param($insertStmt, "iid", $productId, $quantity, $price);
            $insertSuccess = mysqli_stmt_execute($insertStmt);
            
            if (!$insertSuccess) {
                throw new Exception("Erreur lors de l'ajout au panier: " . mysqli_error($con));
            }
        }
        
        mysqli_commit($con);
        
        echo "<script>
                alert('Article \"" . htmlspecialchars($productName) . "\" ajouté au panier !');
                window.location.href='cart.php';
              </script>";
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($con);
        error_log($e->getMessage());
        echo "<script>
                alert('Une erreur est survenue lors de l\'ajout au panier. Veuillez réessayer.');
                window.location.href='cart.php';
              </script>";
        exit;
    }
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

    // Calcul du total avec requête préparée
    $totalStmt = mysqli_prepare($con, "SELECT ProductId, ProductQty, Price FROM tblcart WHERE IsCheckOut=0");
    mysqli_stmt_execute($totalStmt);
    $cartItems = mysqli_stmt_get_result($totalStmt);
    
    $grand = 0;
    $cartProducts = [];
    
    while ($r = mysqli_fetch_assoc($cartItems)) {
        $grand += $r['ProductQty'] * $r['Price'];
        $cartProducts[] = [
            'id' => $r['ProductId'],
            'qty' => $r['ProductQty']
        ];
    }
    
    $netTotal = max(0, $grand - $discount);

    // Si le panier est vide, rediriger
    if (empty($cartProducts)) {
        echo "<script>
                alert('Votre panier est vide. Veuillez ajouter des articles avant de procéder au paiement.');
                window.location.href='cart.php';
              </script>";
        exit;
    }

    // Début d'une transaction pour le checkout complet
    mysqli_begin_transaction($con);
    
    try {
        // Vérifier le stock pour chaque article avec la logique améliorée
        $stockErrors = [];
        
        foreach ($cartProducts as $product) {
            // Utiliser FOR UPDATE pour verrouiller les lignes pendant la vérification
            $stockCheckQuery = "
                SELECT 
                    p.ID,
                    p.ProductName,
                    p.Stock AS initial_stock,
                    COALESCE(SUM(CASE WHEN c.IsCheckOut = 1 THEN c.ProductQty ELSE 0 END), 0) AS sold_qty,
                    COALESCE(
                        (SELECT SUM(r.Quantity) FROM tblreturns r WHERE r.ProductID = p.ID),
                        0
                    ) AS returned_qty
                FROM tblproducts p
                LEFT JOIN tblcart c ON c.ProductId = p.ID
                WHERE p.ID = ?
                GROUP BY p.ID
                FOR UPDATE
            ";
            
            $stockStmt = mysqli_prepare($con, $stockCheckQuery);
            mysqli_stmt_bind_param($stockStmt, "i", $product['id']);
            mysqli_stmt_execute($stockStmt);
            $stockResult = mysqli_stmt_get_result($stockStmt);
            
            if ($stockRow = mysqli_fetch_assoc($stockResult)) {
                $initialStock = intval($stockRow['initial_stock']);
                $soldQty = intval($stockRow['sold_qty']);
                $returnedQty = intval($stockRow['returned_qty']);
                $availableStock = $initialStock - $soldQty + $returnedQty;
                
                if ($product['qty'] > $availableStock) {
                    $stockErrors[] = "Article '{$stockRow['ProductName']}': Quantité demandée ({$product['qty']}) supérieure au stock disponible ({$availableStock})";
                }
            } else {
                $stockErrors[] = "Article non trouvé dans l'inventaire (ID: {$product['id']})";
            }
        }
        
        if (!empty($stockErrors)) {
            // Annuler la transaction en cas d'erreur de stock
            mysqli_rollback($con);
            $errorMsg = "Problèmes de stock identifiés:\\n" . implode("\\n", $stockErrors);
            echo "<script>alert('$errorMsg'); window.location.href='cart.php';</script>";
            exit;
        }

        // Générer un numéro de facture unique
        $billingnum = mt_rand(100000000, 999999999);
        
        // Mise à jour du panier avec requête préparée
        $updateCartStmt = mysqli_prepare($con, 
            "UPDATE tblcart SET BillingId = ?, IsCheckOut = 1 WHERE IsCheckOut = 0"
        );
        mysqli_stmt_bind_param($updateCartStmt, "s", $billingnum);
        
        if (!mysqli_stmt_execute($updateCartStmt)) {
            throw new Exception("Erreur lors de la mise à jour du panier: " . mysqli_error($con));
        }
        
        // Ajout du client avec requête préparée
        $addCustomerStmt = mysqli_prepare($con, 
            "INSERT INTO tblcustomer (BillingNumber, CustomerName, MobileNumber, ModeofPayment, FinalAmount) 
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($addCustomerStmt, "ssssd", $billingnum, $custname, $custmobile, $modepayment, $netTotal);
        
        if (!mysqli_stmt_execute($addCustomerStmt)) {
            throw new Exception("Erreur lors de l'ajout du client: " . mysqli_error($con));
        }
        
        // Mise à jour du stock pour chaque article avec protection contre le stock négatif
        $updateStockStmt = mysqli_prepare($con, 
            "UPDATE tblproducts 
             SET Stock = Stock - ? 
             WHERE ID = ? AND Stock >= ?"
        );
        
        foreach ($cartProducts as $product) {
            mysqli_stmt_bind_param($updateStockStmt, "iii", $product['qty'], $product['id'], $product['qty']);
            mysqli_stmt_execute($updateStockStmt);
            
            // Vérifier si la mise à jour a réussi (qu'il y avait assez de stock)
            if (mysqli_affected_rows($con) == 0) {
                // Vérifier pourquoi l'update a échoué - probablement un stock insuffisant
                $stockCheckStmt = mysqli_prepare($con, "SELECT ProductName, Stock FROM tblproducts WHERE ID = ?");
                mysqli_stmt_bind_param($stockCheckStmt, "i", $product['id']);
                mysqli_stmt_execute($stockCheckStmt);
                $stockResult = mysqli_stmt_get_result($stockCheckStmt);
                $stockData = mysqli_fetch_assoc($stockResult);
                
                // Erreur spécifique avec le nom du produit et le stock disponible
                throw new Exception("Stock insuffisant pour '{$stockData['ProductName']}' - Demandé: {$product['qty']}, Disponible: {$stockData['Stock']}");
            }
        }
        
        // Confirmer toutes les modifications
        mysqli_commit($con);
        
        // Envoi du SMS de confirmation
        $smsMessage = "Bonjour " . htmlspecialchars($custname) . ", votre commande (Facture No:" . $billingnum . ") est confirmée. Merci !";
        $smsResult  = sendSmsNotification($custmobile, $smsMessage);
        $smsMsg     = $smsResult ? "SMS envoyé" : "Échec SMS";
        
        // Stockage de l'ID de facture et nettoyage de la session
        $_SESSION['invoiceid'] = $billingnum;
        unset($_SESSION['discount']);
        unset($_SESSION['discountType']);
        unset($_SESSION['discountValue']);
        
        echo "<script>
                alert('Facture créée : $billingnum\\n$smsMsg');
                window.location.href='invoice.php?print=auto';
              </script>";
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($con);
        error_log("Erreur lors de la validation du panier: " . $e->getMessage());
        echo "<script>
                alert('Erreur lors du paiement: " . addslashes($e->getMessage()) . "');
                window.location.href='cart.php';
              </script>";
        exit;
    }
}

// Récupérer les noms de Articles pour le datalist avec requête préparée
$stmtProductNames = mysqli_prepare($con, "SELECT DISTINCT ProductName FROM tblproducts ORDER BY ProductName ASC");
mysqli_stmt_execute($stmtProductNames);
$productNamesResult = mysqli_stmt_get_result($stmtProductNames);
$productNames = [];

if ($productNamesResult) {
    while ($row = mysqli_fetch_assoc($productNamesResult)) {
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
    <style>
        /* Styles pour les indicateurs de stock */
        .stock-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .stock-ok {
            background-color: #dff0d8;
            color: #3c763d;
        }
        
        .stock-warning {
            background-color: #fcf8e3;
            color: #8a6d3b;
        }
        
        .stock-danger {
            background-color: #f2dede;
            color: #a94442;
        }
        
        tr.stock-error {
            background-color: #f2dede !important;
        }
        
        tr.stock-warning {
            background-color: #fcf8e3 !important;
        }
        
        .price-modified {
            color: #f89406;
            font-weight: bold;
        }
        
        .price-variation {
            font-size: 11px;
            color: #666;
        }
    </style>
</head>
<body>
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
                        ON c.ID = p.CatID
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
            ?>

            <div class="row-fluid">
                <div class="span12">
                    <h4>Résultats de recherche pour "<em><?= htmlspecialchars($_GET['searchTerm']) ?></em>"</h4>

                    <?php if ($count > 0) { ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom du Article</th>
                                        <th>Catégorie</th>
                                        <th>Modèle</th>
                                        <th>Prix par Défaut</th>
                                        <th>Stock</th>
                                        <th>Prix Personnalisé</th>
                                        <th>Quantité</th>
                                        <th>Ajouter</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $i = 1;
                                while ($row = mysqli_fetch_assoc($res)) {
                                    // Vérifier le stock disponible réel avec la nouvelle méthode
                                    $productId = $row['ID'];
                                    $stockCheckStmt = mysqli_prepare($con, "
                                        SELECT 
                                            p.Stock AS initial_stock,
                                            COALESCE(SUM(CASE WHEN c.IsCheckOut = 1 THEN c.ProductQty ELSE 0 END), 0) AS sold_qty,
                                            COALESCE((SELECT SUM(r.Quantity) FROM tblreturns r WHERE r.ProductID = p.ID), 0) AS returned_qty,
                                            COALESCE((
                                                SELECT SUM(cart.ProductQty) 
                                                FROM tblcart cart 
                                                WHERE cart.ProductId = p.ID AND cart.IsCheckOut = 0
                                            ), 0) AS in_carts_qty
                                        FROM tblproducts p
                                        LEFT JOIN tblcart c ON c.ProductId = p.ID
                                        WHERE p.ID = ?
                                        GROUP BY p.ID
                                    ");
                                    mysqli_stmt_bind_param($stockCheckStmt, "i", $productId);
                                    mysqli_stmt_execute($stockCheckStmt);
                                    $stockCheckResult = mysqli_stmt_get_result($stockCheckStmt);
                                    $stockData = mysqli_fetch_assoc($stockCheckResult);
                                    
                                    $initialStock = intval($stockData['initial_stock'] ?? 0);
                                    $soldQty = intval($stockData['sold_qty'] ?? 0);
                                    $returnedQty = intval($stockData['returned_qty'] ?? 0);
                                    $inCartsQty = intval($stockData['in_carts_qty'] ?? 0);
                                    
                                    $realStock = $initialStock - $soldQty + $returnedQty;
                                    $availableStock = $realStock - $inCartsQty; // Stock disponible hors paniers
                                    
                                    $disableAdd = ($availableStock <= 0);
                                    $rowClass = '';
                                    $stockStatusClass = '';
                                    $stockStatusText = '';
                                    
                                    if ($availableStock <= 0) {
                                        $rowClass = 'class="stock-error"';
                                        $stockStatusClass = 'stock-danger';
                                        $stockStatusText = 'RUPTURE';
                                    } elseif ($availableStock < 5) {
                                        $rowClass = 'class="stock-warning"';
                                        $stockStatusClass = 'stock-warning';
                                        $stockStatusText = 'FAIBLE';
                                    } else {
                                        $stockStatusClass = 'stock-ok';
                                        $stockStatusText = 'DISPONIBLE';
                                    }
                                    ?>
                                    <tr <?php echo $rowClass; ?>>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['CategoryName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['ModelNumber']); ?></td>
                                        <td><?php echo number_format($row['Price'], 2); ?> GNF</td>
                                        <td>
                                            <?php echo $availableStock; ?> / <?php echo $realStock; ?>
                                            <div class="stock-status <?php echo $stockStatusClass; ?>">
                                                <?php echo $stockStatusText; ?>
                                                <?php if ($inCartsQty > 0): ?>
                                                    (<?php echo $inCartsQty; ?> réservés)
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <form method="post" action="cart.php" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
                                                <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>" />
                                                <input type="number" name="price" step="any" 
                                                       value="<?php echo $row['Price']; ?>" style="width:80px;" />
                                        </td>
                                        <td>
                                            <input type="number" class="quantity-input" name="quantity" value="1" min="1" max="<?php echo $availableStock; ?>" style="width:60px;" <?php echo $disableAdd ? 'disabled' : ''; ?> 
                                                   data-max="<?php echo $availableStock; ?>" />
                                        </td>
                                        <td>
                                            <button type="submit" name="addtocart" class="btn btn-success btn-small" <?php echo $disableAdd ? 'disabled' : ''; ?>>
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
                            <p style="color:red;">Aucun Article correspondant trouvé.</p>
                        <?php } ?>
                    </div>
                </div>
                <hr>
            <?php } ?>

           <!-- ========== PANIER + REMISE + PAIEMENT ========== -->
            <div class="row-fluid">
                <div class="span12">
                    <!-- FORMULAIRE DE REMISE avec option pour pourcentage -->
                    <form method="post" class="form-inline" style="text-align:right;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
                        <label>Remise :</label>
                        <input type="number" name="discount" step="any" value="<?php echo htmlspecialchars($discountValue); ?>" style="width:80px;" />
                        
                        <select name="discountType" style="width:120px; margin-left:5px;">
                            <option value="absolute" <?php echo ($discountType == 'absolute') ? 'selected' : ''; ?>>Valeur absolue</option>
                            <option value="percentage" <?php echo ($discountType == 'percentage') ? 'selected' : ''; ?>>Pourcentage (%)</option>
                        </select>
                        
                        <button class="btn btn-info" type="submit" name="applyDiscount" style="margin-left:5px;">Appliquer</button>
                    </form>
                    <hr>

                    <!-- Formulaire checkout (informations client) -->
                    <form method="post" class="form-horizontal" id="checkoutForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
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
                                <label><input type="radio" name="modepayment" value="credit"> Crédit (Terme)</label>
                            </div>
                        </div>
                        <div class="text-center">
                            <button class="btn btn-primary" type="submit" name="submit" id="submitCheckout">
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
                                        <th>Prix de base</th>
                                        <th>Prix appliqué</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Requête améliorée pour obtenir les articles du panier
                                    $cartQuery = "
                                      SELECT 
                                        c.ID as cid,
                                        c.ProductId,
                                        c.ProductQty,
                                        c.Price as cartPrice,
                                        p.ProductName,
                                        p.Stock as initial_stock,
                                        p.Price as basePrice,
                                        COALESCE(SUM(CASE WHEN sold.IsCheckOut = 1 THEN sold.ProductQty ELSE 0 END), 0) AS sold_qty,
                                        COALESCE(
                                            (SELECT SUM(r.Quantity) FROM tblreturns r WHERE r.ProductID = p.ID),
                                            0
                                        ) AS returned_qty,
                                        (
                                            SELECT COALESCE(SUM(other_cart.ProductQty), 0) 
                                            FROM tblcart other_cart 
                                            WHERE other_cart.ProductId = p.ID 
                                            AND other_cart.IsCheckOut = 0 
                                            AND other_cart.ID != c.ID
                                        ) AS other_carts_qty
                                      FROM tblcart c
                                      LEFT JOIN tblproducts p ON p.ID = c.ProductId
                                      LEFT JOIN tblcart sold ON sold.ProductId = p.ID
                                      WHERE c.IsCheckOut = 0
                                      GROUP BY c.ID
                                      ORDER BY c.ID ASC
                                    ";
                                    
                                    $stmt = mysqli_prepare($con, $cartQuery);
                                    mysqli_stmt_execute($stmt);
                                    $cartResult = mysqli_stmt_get_result($stmt);
                                    
                                    $cnt = 1;
                                    $grandTotal = 0;
                                    $num = mysqli_num_rows($cartResult);
                                    $stockWarning = false;
                                    
                                    if ($num > 0) {
                                        while ($row = mysqli_fetch_array($cartResult)) {
                                            $pq = $row['ProductQty'];
                                            $ppu = $row['cartPrice'];
                                            $basePrice = $row['basePrice'];
                                            $initialStock = intval($row['initial_stock']);
                                            $soldQty = intval($row['sold_qty']);
                                            $returnedQty = intval($row['returned_qty']);
                                            $otherCartsQty = intval($row['other_carts_qty']);
                                            $lineTotal = $pq * $ppu;
                                            $grandTotal += $lineTotal;
                                            
                                            // Calcul du stock réellement disponible
                                            $remainingStock = $initialStock - $soldQty + $returnedQty;
                                            $trueAvailableStock = $remainingStock - $otherCartsQty;
                                            
                                            // Vérifier si le stock actuel est suffisant
                                            $stockSuffisant = $remainingStock >= $pq;
                                            if (!$stockSuffisant) {
                                                $stockWarning = true;
                                            }
                                            
                                            // Déterminer si le prix a été modifié par rapport au prix de base
                                            $prixModifie = ($ppu != $basePrice);
                                            $rowClass = '';
                                            
                                            if (!$stockSuffisant) {
                                                $rowClass = 'class="error"';
                                            } elseif ($trueAvailableStock < 5) {
                                                $rowClass = 'class="warning"';
                                            }
                                            ?>
                                            <tr <?php echo $rowClass; ?>>
                                                <td><?php echo $cnt; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['ProductName']); ?>
                                                    <?php if (!$stockSuffisant): ?>
                                                        <br><span class="label label-important">Stock insuffisant! (Disponible: <?php echo $remainingStock; ?>)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $pq; ?>
                                                    <?php
                                                    // Affichage d'information sur le stock disponible pour cet article
                                                    $stockStatus = '';
                                                    $stockStatusClass = '';
                                                    
                                                    if ($remainingStock <= 0) {
                                                        $stockStatus = 'RUPTURE';
                                                        $stockStatusClass = 'label-important';
                                                    } elseif ($trueAvailableStock < $pq) {
                                                        $stockStatus = 'INSUFFISANT';
                                                        $stockStatusClass = 'label-important';
                                                    } elseif ($trueAvailableStock < 5) {
                                                        $stockStatus = 'FAIBLE';
                                                        $stockStatusClass = 'label-warning';
                                                    } else {
                                                        $stockStatus = 'DISPONIBLE';
                                                        $stockStatusClass = 'label-success';
                                                    }
                                                    ?>
                                                    <br>
                                                    <span class="label <?php echo $stockStatusClass; ?>">
                                                        <?php echo $stockStatus; ?>
                                                        (<?php echo $trueAvailableStock; ?> / <?php echo $remainingStock; ?>)
                                                    </span>
                                                    <?php if ($otherCartsQty > 0): ?>
                                                        <br><small>(<?php echo $otherCartsQty; ?> dans d'autres paniers)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo number_format($basePrice, 2); ?> GNF
                                                </td>
                                                <td <?php echo $prixModifie ? 'class="price-modified"' : ''; ?>>
                                                    <?php echo number_format($ppu, 2); ?> GNF
                                                    <?php if ($prixModifie): ?>
                                                        <?php 
                                                        $variation = (($ppu / $basePrice) - 1) * 100;
                                                        $symbole = $variation >= 0 ? '+' : '';
                                                        echo '<br><span class="price-variation">(' . $symbole . number_format($variation, 1) . '%)</span>'; 
                                                        ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($lineTotal, 2); ?> GNF</td>
                                                <td>
                                                    <a href="cart.php?delid=<?php echo $row['cid']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>"
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
                                        ?><tr>
                                        <th colspan="5" style="text-align: right; font-weight: bold;">Total Général</th>
                                        <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($grandTotal, 2); ?> GNF</th>
                                    </tr>
                                    <tr>
                                        <th colspan="5" style="text-align: right; font-weight: bold;">
                                            Remise
                                            <?php if ($discountType == 'percentage'): ?>
                                                (<?php echo htmlspecialchars($discountValue); ?>%)
                                            <?php endif; ?>
                                        </th>
                                        <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($discount, 2); ?> GNF</th>
                                    </tr>
                                    <tr>
                                        <th colspan="5" style="text-align: right; font-weight: bold; color: green;">Total Net</th>
                                        <th colspan="2" style="text-align: center; font-weight: bold; color: green;"><?php echo number_format($netTotal, 2); ?> GNF</th>
                                    </tr>
                                    <?php
                                    // Ajouter un message d'avertissement si des Articles ont un stock insuffisant
                                    if ($stockWarning): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: red; font-weight: bold;">
                                            Attention! Certains Articles n'ont pas un stock suffisant. Veuillez ajuster votre panier.
                                        </td>
                                    </tr>
                                    <script>
                                        // Désactiver le bouton de paiement si stock insuffisant
                                        document.addEventListener('DOMContentLoaded', function() {
                                            document.getElementById('submitCheckout').disabled = true;
                                            document.getElementById('submitCheckout').title = "Impossible de finaliser: stock insuffisant";
                                        });
                                    </script>
                                    <?php endif; ?>
                                    <?php
                                } else {
                                    ?>
                                    <tr>
                                        <td colspan="7" style="color:red; text-align:center">Aucun article trouvé dans le panier</td>
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

<!-- Script pour validation en temps réel du stock -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation des quantités lors de la saisie
    const qtyInputs = document.querySelectorAll('.quantity-input');
    qtyInputs.forEach(input => {
        input.addEventListener('change', function() {
            const max = parseInt(this.getAttribute('data-max'));
            const val = parseInt(this.value);
            
            if (val > max) {
                alert(`Stock insuffisant. Maximum disponible: ${max}`);
                this.value = max;
            }
        });
    });
    
    // Validation du panier avant checkout
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Vérifier si le panier contient des articles
            const hasItems = <?php echo ($num > 0) ? 'true' : 'false'; ?>;
            if (!hasItems) {
                e.preventDefault();
                alert('Votre panier est vide. Veuillez ajouter des articles avant de procéder au paiement.');
                return false;
            }
            
            // Vérifier s'il y a des problèmes de stock
            const stockWarning = <?php echo $stockWarning ? 'true' : 'false'; ?>;
            if (stockWarning) {
                e.preventDefault();
                alert('Il y a des problèmes de stock avec certains articles dans votre panier. Veuillez les ajuster avant de continuer.');
                return false;
            }
            
            // Confirmation finale
            if (!confirm('Confirmer la commande ? Cela réduira le stock disponible.')) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>
</body>
</html>