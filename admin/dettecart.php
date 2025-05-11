<?php
session_start();

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifie que l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

/**
 * Function to manage stock movements with logging
 * @param mysqli $con Database connection
 * @param int $productId Product ID
 * @param int $quantity Quantity (negative for decrease)
 * @param string $reference Reference number (invoice/order number)
 * @return bool Success status
 */
function updateProductStock($con, $productId, $quantity, $reference = null) {
    // Get current stock for logging
    $checkStmt = mysqli_prepare($con, "SELECT Stock, ProductName FROM tblproducts WHERE ID = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "i", $productId);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        error_log("Stock update failed: Product ID $productId not found");
        return false;
    }
    
    $oldStock = $row['ProductName'];
    $productName = $row['ProductName'];
    $newStock = max(0, $oldStock + $quantity); // Ensure stock never goes negative
    
    // Update the stock
    $updateStmt = mysqli_prepare($con, "UPDATE tblproducts SET Stock = ? WHERE ID = ?");
    mysqli_stmt_bind_param($updateStmt, "ii", $newStock, $productId);
    $success = mysqli_stmt_execute($updateStmt);
    
    if (!$success || mysqli_affected_rows($con) == 0) {
        error_log("Stock update failed for product: $productName (ID: $productId)");
        return false;
    }
    
    // Log the stock change
    $direction = ($quantity < 0) ? "decreased" : "increased";
    $absQuantity = abs($quantity);
    error_log("Stock $direction by $absQuantity for product: $productName (ID: $productId). Old: $oldStock, New: $newStock. Reference: $reference");
    
    return true;
}

/**
 * Obtenir un access token OAuth2 de Nimba using cURL.
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
    
    // Version sécurisée avec cURL au lieu de file_get_contents
    $ch = curl_init();
    
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
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
}

// ----------- Gestion Panier -----------

// Ajout au panier
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity  = max(1, intval($_POST['quantity']));
    $price     = max(0, floatval($_POST['price']));

    // Vérifier si l'article est déjà dans le panier de l'utilisateur
    $checkCartStmt = mysqli_prepare($con, 
        "SELECT ID, ProductQty FROM tblcreditcart WHERE ProductId = ? AND IsCheckOut = 0 LIMIT 1"
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

    // Vérifier le stock disponible avec requête préparée
    $stockQuery = "
        SELECT 
            p.Stock AS initial_stock,
            p.ProductName,
            (
                SELECT COALESCE(SUM(cc.ProductQty), 0)
                FROM tblcreditcart cc
                WHERE cc.ProductId = p.ID AND cc.IsCheckOut = 1
            ) AS sold_qty,
            (
                SELECT COALESCE(SUM(r.Quantity), 0)
                FROM tblreturns r
                WHERE r.ProductID = p.ID
            ) AS returned_qty,
            (
                SELECT COALESCE(SUM(cc.ProductQty), 0)
                FROM tblcreditcart cc
                WHERE cc.ProductId = p.ID AND cc.IsCheckOut = 0 AND cc.ID != ?
            ) AS other_carts_qty
        FROM tblproducts p
        WHERE p.ID = ?
        LIMIT 1
    ";
    
    $stmt = mysqli_prepare($con, $stockQuery);
    if (!$stmt) {
        error_log("Erreur préparation requête stock: " . mysqli_error($con));
        echo "<script>
                alert('Erreur système lors de la vérification du stock');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $cartItemId, $productId);
    mysqli_stmt_execute($stmt);
    $stockResult = mysqli_stmt_get_result($stmt);
    
    if (!$stockResult || mysqli_num_rows($stockResult) === 0) {
        echo "<script>
                alert('Article introuvable');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
    
    $row = mysqli_fetch_assoc($stockResult);
    $initialStock = intval($row['initial_stock']);
    $soldQty = intval($row['sold_qty']);
    $returnedQty = intval($row['returned_qty']);
    $otherCartsQty = intval($row['other_carts_qty']);
    $productName = $row['ProductName'];
    
    // Calcul du stock disponible
    // Stock initial - vendu + retourné - réservé dans d'autres paniers
    $availableStock = $initialStock - $soldQty + $returnedQty - $otherCartsQty;
    
    // Vérification que le stock est strictement supérieur à 0
    if ($availableStock <= 0) {
        echo "<script>
                alert('Article \"" . htmlspecialchars($productName) . "\" en rupture de stock.');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
    
    // Vérification que la quantité demandée est disponible
    // Pour un article déjà dans le panier, nous devons vérifier si la nouvelle quantité totale est disponible
    $newTotalQty = $quantity;
    if ($cartItemId > 0) {
        $newTotalQty = $currentCartQty + $quantity;
    }
    
    if ($newTotalQty > $availableStock) {
        echo "<script>
                alert('Vous avez demandé " . $newTotalQty . " exemplaire(s) de \"" . htmlspecialchars($productName) . "\", il n\'en reste que " . $availableStock . " disponible(s).');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }

    // INSERT ou UPDATE avec requêtes préparées
    mysqli_begin_transaction($con); // Démarrer une transaction pour garantir l'intégrité des données
    
    try {
        if ($cartItemId > 0) {
            // Mise à jour de la quantité si l'article est déjà dans le panier
            $updateStmt = mysqli_prepare($con, 
                "UPDATE tblcreditcart SET ProductQty = ?, Price = ? WHERE ID = ?"
            );
            mysqli_stmt_bind_param($updateStmt, "idi", $newTotalQty, $price, $cartItemId);
            $updateSuccess = mysqli_stmt_execute($updateStmt);
            
            if (!$updateSuccess) {
                throw new Exception("Erreur lors de la mise à jour du panier: " . mysqli_error($con));
            }
        } else {
            // Insertion d'un nouvel article dans le panier
            $insertStmt = mysqli_prepare($con, 
                "INSERT INTO tblcreditcart(ProductId, ProductQty, Price, IsCheckOut) VALUES(?, ?, ?, 0)"
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
                window.location.href='dettecart.php';
              </script>";
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($con);
        error_log($e->getMessage());
        echo "<script>
                alert('Une erreur est survenue lors de l\'ajout au panier. Veuillez réessayer.');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
}

// Supprimer un Article
if (isset($_GET['delid']) && isset($_GET['csrf_token'])) {
    // Vérification CSRF pour les requêtes GET critiques
    if ($_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed for cart item deletion");
        echo "<script>
                alert('Erreur de sécurité. Veuillez réessayer.');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
    
    $delid = intval($_GET['delid']);
    
    $deleteStmt = mysqli_prepare($con, "DELETE FROM tblcreditcart WHERE ID = ? AND IsCheckOut = 0");
    mysqli_stmt_bind_param($deleteStmt, "i", $delid);
    mysqli_stmt_execute($deleteStmt);
    
    if (mysqli_affected_rows($con) > 0) {
        echo "<script>
                alert('Article retiré du panier');
                window.location.href='dettecart.php';
              </script>";
        exit;
    } else {
        echo "<script>
                alert('Erreur lors de la suppression de l\'article');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
}

// Appliquer une remise (en valeur absolue ou en pourcentage)
if (isset($_POST['applyDiscount'])) {
    $discountValue = max(0, floatval($_POST['discount']));
    
    // Calculer le grand total avant d'appliquer la remise avec une requête préparée
    $grandTotal = 0;
    $cartQuery = mysqli_prepare($con, "SELECT ProductQty, Price FROM tblcreditcart WHERE IsCheckOut=0");
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
    $_SESSION['credit_discount'] = $actualDiscount;
    $_SESSION['credit_discountType'] = $isPercentage ? 'percentage' : 'absolute';
    $_SESSION['credit_discountValue'] = $discountValue;
    
    header("Location: dettecart.php");
    exit;
}

// Récupérer les informations de remise de la session
$discount = $_SESSION['credit_discount'] ?? 0;
$discountType = $_SESSION['credit_discountType'] ?? 'absolute';
$discountValue = $_SESSION['credit_discountValue'] ?? 0;

// Vérifier les stocks pour l'affichage
$hasStockIssue = false;
$stockIssueProducts = [];

// Récupérer la liste des noms de Articles pour la datalist avec requête préparée
$productNames = [];
$productStmt = mysqli_prepare($con, "SELECT ProductName FROM tblproducts ORDER BY ProductName");
mysqli_stmt_execute($productStmt);
$productResult = mysqli_stmt_get_result($productStmt);

while ($row = mysqli_fetch_assoc($productResult)) {
    $productNames[] = $row['ProductName'];
}

// Checkout + Facturation
if (isset($_POST['submit'])) {
    $custname = mysqli_real_escape_string($con, trim($_POST['customername']));
    $custmobile = preg_replace('/[^0-9+]/', '', $_POST['mobilenumber']);
    $modepayment = mysqli_real_escape_string($con, $_POST['modepayment']);
    $paidNow = max(0, floatval($_POST['paid']));

    // Récupérer les articles du panier pour calcul et vérification
    $cartItemsStmt = mysqli_prepare($con, "
        SELECT 
            c.ID,
            c.ProductId,
            c.ProductQty,
            c.Price,
            p.ProductName,
            p.Stock
        FROM tblcreditcart c
        JOIN tblproducts p ON p.ID = c.ProductId
        WHERE c.IsCheckOut = 0
    ");
    mysqli_stmt_execute($cartItemsStmt);
    $cartItems = mysqli_stmt_get_result($cartItemsStmt);
    
    $grandTotal = 0;
    $cartProducts = [];
    $initialStocks = []; // Pour garder trace du stock initial avant modification
    
    while ($item = mysqli_fetch_assoc($cartItems)) {
        $grandTotal += $item['ProductQty'] * $item['Price'];
        $cartProducts[] = [
            'id' => $item['ProductId'],
            'qty' => $item['ProductQty'],
            'cart_id' => $item['ID'],
            'name' => $item['ProductName']
        ];
        $initialStocks[$item['ProductId']] = $item['Stock'];
    }

    $netTotal = max(0, $grandTotal - $discount);
    $dues = max(0, $netTotal - $paidNow);

    // Si le panier est vide, rediriger
    if (empty($cartProducts)) {
        echo "<script>
                alert('Votre panier est vide. Veuillez ajouter des articles avant de procéder au paiement.');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }

    // Vérification finale du stock pour chaque article
    $stockErrors = [];
    
    foreach ($cartProducts as $product) {
        // Vérifier directement avec le stock actuel dans tblproducts
        if ($initialStocks[$product['id']] < $product['qty']) {
            $stockErrors[] = "Stock insuffisant pour {$product['name']} (demandé: {$product['qty']}, disponible: {$initialStocks[$product['id']]})";
        }
    }
    
    if (!empty($stockErrors)) {
        $errorMsg = "Impossible de finaliser la commande:\n- " . implode("\n- ", $stockErrors);
        echo "<script>alert(" . json_encode($errorMsg) . "); window.location='dettecart.php';</script>";
        exit;
    }

    // Début de la transaction pour le checkout
    mysqli_begin_transaction($con);
    
    try {
        // Générer un numéro de facture unique
        $billingnum = mt_rand(100000000, 999999999);
        
        // Log de début de transaction
        error_log("CHECKOUT: Starting transaction for order #$billingnum with " . count($cartProducts) . " products");
        
        // Mise à jour du panier avec requête préparée
        $updateCartStmt = mysqli_prepare($con, 
            "UPDATE tblcreditcart SET BillingId = ?, IsCheckOut = 1 WHERE IsCheckOut = 0"
        );
        mysqli_stmt_bind_param($updateCartStmt, "s", $billingnum);
        
        if (!mysqli_stmt_execute($updateCartStmt) || mysqli_affected_rows($con) != count($cartProducts)) {
            throw new Exception("Erreur lors de la mise à jour du panier: " . mysqli_error($con));
        }
        
        error_log("CHECKOUT: Cart items marked as checked out: " . mysqli_affected_rows($con));
        
        // Ajout du client avec requête préparée
        $addCustomerStmt = mysqli_prepare($con, 
            "INSERT INTO tblcustomer (BillingNumber, CustomerName, MobileNumber, ModeOfPayment, BillingDate, FinalAmount, Paid, Dues) 
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)"
        );
        mysqli_stmt_bind_param($addCustomerStmt, "ssssddd", $billingnum, $custname, $custmobile, $modepayment, $netTotal, $paidNow, $dues);
        
        if (!mysqli_stmt_execute($addCustomerStmt)) {
            throw new Exception("Erreur lors de l'ajout du client: " . mysqli_error($con));
        }
        
        error_log("CHECKOUT: Customer added to database for order #$billingnum");
        
        // SOLUTION AMÉLIORÉE: Mise à jour du stock pour chaque article
        $stockUpdateErrors = [];
        $stockUpdates = [];
        
        // Préparation de la requête d'update stock
        $updateStockStmt = mysqli_prepare($con, 
            "UPDATE tblproducts SET Stock = Stock - ? WHERE ID = ? AND Stock >= ?"
        );
        
        foreach ($cartProducts as $product) {
            // Vérifier le stock actuel une dernière fois
            $stockCheckStmt = mysqli_prepare($con, "SELECT Stock FROM tblproducts WHERE ID = ?");
            mysqli_stmt_bind_param($stockCheckStmt, "i", $product['id']);
            mysqli_stmt_execute($stockCheckStmt);
            $stockCheck = mysqli_stmt_get_result($stockCheckStmt);
            $currentStock = mysqli_fetch_assoc($stockCheck)['Stock'];
            
            // Décrémenter le stock seulement si suffisant
            if ($currentStock >= $product['qty']) {
                mysqli_stmt_bind_param($updateStockStmt, "iii", $product['qty'], $product['id'], $product['qty']);
                $updateResult = mysqli_stmt_execute($updateStockStmt);
                
                if (!$updateResult || mysqli_affected_rows($con) == 0) {
                    $stockUpdateErrors[] = "Échec de mise à jour du stock pour le produit {$product['name']} (ID: {$product['id']})";
                    continue;
                }
                
                // Enregistrer l'opération pour vérification
                $stockUpdates[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'before' => $currentStock,
                    'after' => $currentStock - $product['qty'],
                    'qty' => $product['qty']
                ];
                
                error_log("CHECKOUT: Stock updated for product '{$product['name']}' (ID: {$product['id']}). Before: $currentStock, After: " . ($currentStock - $product['qty']));
            } else {
                $stockUpdateErrors[] = "Stock insuffisant pour {$product['name']} lors de la mise à jour (demandé: {$product['qty']}, disponible: $currentStock)";
            }
        }
        
        // Vérifier s'il y a eu des erreurs de mise à jour de stock
        if (!empty($stockUpdateErrors)) {
            throw new Exception("Erreurs lors de la mise à jour du stock:\n- " . implode("\n- ", $stockUpdateErrors));
        }
        
        // Vérification finale que TOUS les stocks ont été mis à jour
        if (count($stockUpdates) != count($cartProducts)) {
            throw new Exception("Certains produits n'ont pas eu leur stock mis à jour correctement");
        }
        
        // Vérification que les stocks ont bien été décrémentés
        $verificationErrors = [];
        foreach ($stockUpdates as $update) {
            $verifyStmt = mysqli_prepare($con, "SELECT Stock FROM tblproducts WHERE ID = ?");
            mysqli_stmt_bind_param($verifyStmt, "i", $update['id']);
            mysqli_stmt_execute($verifyStmt);
            $verifyResult = mysqli_stmt_get_result($verifyStmt);
            $actualStock = mysqli_fetch_assoc($verifyResult)['Stock'];
            
            if ($actualStock != $update['after']) {
                $verificationErrors[] = "Le stock pour '{$update['name']}' devrait être {$update['after']} mais est $actualStock";
            }
        }
        
        if (!empty($verificationErrors)) {
            error_log("STOCK VERIFICATION ERRORS: " . implode(", ", $verificationErrors));
            // Tenter de corriger automatiquement
            foreach ($stockUpdates as $update) {
                $fixStmt = mysqli_prepare($con, "UPDATE tblproducts SET Stock = ? WHERE ID = ?");
                mysqli_stmt_bind_param($fixStmt, "ii", $update['after'], $update['id']);
                mysqli_stmt_execute($fixStmt);
                error_log("STOCK FIX: Corrected stock for '{$update['name']}' to {$update['after']}");
            }
        }
        
        // Confirmer toutes les modifications
        mysqli_commit($con);
        error_log("CHECKOUT: Transaction successfully committed for order #$billingnum");
        
        // Préparation du SMS en fonction du solde dû
        if ($dues > 0) {
            $smsMessage = "Bonjour " . htmlspecialchars($custname) . ", votre commande (Facture: $billingnum) est enregistrée. Solde dû: " . number_format($dues, 0, ',', ' ') . " GNF.";
        } else {
            $smsMessage = "Bonjour " . htmlspecialchars($custname) . ", votre commande (Facture: $billingnum) est confirmée. Merci pour votre confiance !";
        }
        
        // Envoyer le SMS et stocker le résultat
        $smsResult = sendSmsNotification($custmobile, $smsMessage);
        
        // Journal de l'envoi SMS (si la table existe)
        $tableExistsStmt = mysqli_prepare($con, "SHOW TABLES LIKE 'tbl_sms_logs'");
        mysqli_stmt_execute($tableExistsStmt);
        $tableExists = mysqli_stmt_get_result($tableExistsStmt);
        
        if (mysqli_num_rows($tableExists) > 0) {
            $escapedMessage = mysqli_real_escape_string($con, $smsMessage);
            $smsStatus = $smsResult ? 1 : 0;
            
            $smsLogStmt = mysqli_prepare($con, 
                "INSERT INTO tbl_sms_logs (recipient, message, status, send_date) VALUES (?, ?, ?, NOW())"
            );
            mysqli_stmt_bind_param($smsLogStmt, "ssi", $custmobile, $escapedMessage, $smsStatus);
            mysqli_stmt_execute($smsLogStmt);
        }
        
        // Nettoyage de la session et redirection
        unset($_SESSION['credit_discount']);
        unset($_SESSION['credit_discountType']);
        unset($_SESSION['credit_discountValue']);
        $_SESSION['invoiceid'] = $billingnum;
        
        // Résumé des articles achetés pour le message
        $productSummary = "";
        foreach ($cartProducts as $index => $product) {
            if ($index < 3) { // Limiter à 3 produits pour l'alerte
                $productSummary .= "- {$product['name']} (Qté: {$product['qty']})\n";
            }
        }
        if (count($cartProducts) > 3) {
            $productSummary .= "- et " . (count($cartProducts) - 3) . " autre(s) article(s)";
        }
        
        // Afficher le statut de l'envoi SMS dans le message d'alerte
        if ($smsResult) {
            echo "<script>
                    alert('Facture créée: $billingnum - SMS envoyé avec succès\\n\\nArticles:\\n$productSummary');
                    window.location='invoice_dettecard.php?print=auto';
                  </script>";
        } else {
            echo "<script>
                    alert('Facture créée: $billingnum - ÉCHEC de l\\'envoi du SMS\\n\\nArticles:\\n$productSummary');
                    window.location='invoice_dettecard.php?print=auto';
                  </script>";
        }
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($con);
        error_log("CHECKOUT ERROR: " . $e->getMessage());
        echo "<script>
                alert('Erreur lors du paiement: " . addslashes($e->getMessage()) . "');
                window.location.href='dettecart.php';
              </script>";
        exit;
    }
}

// Vérifier à nouveau les stocks pour l'affichage du panier
$cartQuery = mysqli_prepare($con, "
    SELECT 
        c.ID, 
        c.ProductId, 
        c.ProductQty, 
        p.ProductName,
        p.Stock AS initial_stock
    FROM tblcreditcart c
    JOIN tblproducts p ON p.ID = c.ProductId
    WHERE c.IsCheckOut = 0
");
mysqli_stmt_execute($cartQuery);
$cartResult = mysqli_stmt_get_result($cartQuery);
$cartItems = [];

while ($item = mysqli_fetch_assoc($cartResult)) {
    $cartItems[] = $item;
}

// Vérifier le stock pour chaque article dans le panier
foreach ($cartItems as $item) {
    // Requête pour obtenir les données de stock réel
    $stockQuery = "
        SELECT 
            (
                SELECT COALESCE(SUM(cc.ProductQty), 0)
                FROM tblcreditcart cc
                WHERE cc.ProductId = ? AND cc.IsCheckOut = 1
            ) AS sold_qty,
            (
                SELECT COALESCE(SUM(r.Quantity), 0)
                FROM tblreturns r
                WHERE r.ProductID = ?
            ) AS returned_qty,
            (
                SELECT COALESCE(SUM(cc.ProductQty), 0)
                FROM tblcreditcart cc
                WHERE cc.ProductId = ? AND cc.IsCheckOut = 0 AND cc.ID != ?
            ) AS other_carts_qty
    ";
    
    $stockStmt = mysqli_prepare($con, $stockQuery);
    mysqli_stmt_bind_param($stockStmt, "iiii", $item['ProductId'], $item['ProductId'], $item['ProductId'], $item['ID']);
    mysqli_stmt_execute($stockStmt);
    $stockResult = mysqli_stmt_get_result($stockStmt);
    $stockData = mysqli_fetch_assoc($stockResult);
    
    $soldQty = intval($stockData['sold_qty']);
    $returnedQty = intval($stockData['returned_qty']);
    $otherCartsQty = intval($stockData['other_carts_qty']);
    
    // Calcul correct du stock disponible
    $availableStock = $item['initial_stock'] - $soldQty + $returnedQty - $otherCartsQty;
    
    if ($availableStock < $item['ProductQty']) {
        $hasStockIssue = true;
        $stockIssueProducts[] = $item['ProductName'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de Gestion d'Inventaire | Panier à Terme</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    
    <!-- Style pour les problèmes de stock -->
    <style>
        .stock-warning {
            color: #d9534f;
            font-weight: bold;
            margin-left: 5px;
        }
        
        tr.stock-error {
            background-color: #f2dede !important;
        }
        
        .global-warning {
            background-color: #f2dede;
            border: 1px solid #ebccd1;
            color: #a94442;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .stock-status {
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
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
    </style>
</head>
<body>
    <!-- Header + Sidebar -->
    <?php include_once('includes/header.php'); ?>
    <?php include_once('includes/sidebar.php'); ?>
  
    <div id="content">
        <div id="content-header">
            <div id="breadcrumb">
                <a href="dashboard.php" class="tip-bottom">
                    <i class="icon-home"></i> Accueil
                </a>
                <a href="dettecart.php" class="current">Panier à Terme</a>
            </div>
            <h1>Panier à Terme (Vente à crédit possible)</h1>
        </div>
  
        <div class="container-fluid">
            <hr>
            
            <!-- Message d'alerte si problème de stock -->
            <?php if ($hasStockIssue): ?>
            <div class="global-warning">
                <strong><i class="icon-warning-sign"></i> Attention !</strong> Certains Articles dans votre panier ont des problèmes de stock :
                <ul>
                    <?php foreach($stockIssueProducts as $product): ?>
                    <li><?php echo htmlspecialchars($product); ?></li>
                    <?php endforeach; ?>
                </ul>
                Veuillez ajuster les quantités ou supprimer ces Articles avant de finaliser la commande.
            </div>
            
            <!-- Script pour désactiver le bouton de paiement en cas de problème de stock -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Désactiver le bouton de validation
                    var submitBtn = document.querySelector('button[name="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.title = "Veuillez d'abord résoudre les problèmes de stock";
                        submitBtn.style.opacity = "0.5";
                        submitBtn.style.cursor = "not-allowed";
                    }
                });
            </script>
            <?php endif; ?>
            
            <!-- ====================== FORMULAIRE DE RECHERCHE (avec datalist) ====================== -->
            <div class="row-fluid">
                <div class="span12">
                    <form method="get" action="dettecart.php" class="form-inline">
                        <label>Rechercher des Articles :</label>
                        <input type="text" name="searchTerm" class="span3"
                               placeholder="Nom du Article ou modèle..." list="productsList" />
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
                $searchSql = "
                    SELECT 
                        p.ID, p.ProductName, p.BrandName, p.ModelNumber, p.Price, p.Stock,
                        c.CategoryName, s.SubCategoryName
                    FROM tblproducts p
                    LEFT JOIN tblcategory c ON c.ID = p.CatID
                    LEFT JOIN tblsubcategory s ON s.ID = p.SubcatID
                    WHERE 
                        p.ProductName LIKE ? OR p.ModelNumber LIKE ?
                ";
                
                $searchStmt = mysqli_prepare($con, $searchSql);
                $searchParam = "%$searchTerm%";
                mysqli_stmt_bind_param($searchStmt, "ss", $searchParam, $searchParam);
                mysqli_stmt_execute($searchStmt);
                $searchResult = mysqli_stmt_get_result($searchStmt);
                $count = mysqli_num_rows($searchResult);
                ?>
                <div class="row-fluid">
                    <div class="span12">
                        <h4>Résultats de recherche pour "<em><?php echo htmlspecialchars($searchTerm); ?></em>"</h4>
                        <?php if ($count > 0) { ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom du Article</th>
                                        <th>Catégorie</th>
                                        <th>Sous-Catégorie</th>
                                        <th>Marque</th>
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
                                while ($row = mysqli_fetch_assoc($searchResult)) {
                                    // Vérifier le stock disponible réel avec la nouvelle méthode
                                    $productId = $row['ID'];
                                    
                                    // Trouver d'abord si l'article est déjà dans le panier
                                    $cartCheckStmt = mysqli_prepare($con, "
                                        SELECT ID, ProductQty 
                                        FROM tblcreditcart 
                                        WHERE ProductId = ? AND IsCheckOut = 0
                                    ");
                                    mysqli_stmt_bind_param($cartCheckStmt, "i", $productId);
                                    mysqli_stmt_execute($cartCheckStmt);
                                    $cartCheckResult = mysqli_stmt_get_result($cartCheckStmt);
                                    
                                    $cartItemId = 0;
                                    $cartItemQty = 0;
                                    if (mysqli_num_rows($cartCheckResult) > 0) {
                                        $cartItem = mysqli_fetch_assoc($cartCheckResult);
                                        $cartItemId = $cartItem['ID'];
                                        $cartItemQty = $cartItem['ProductQty'];
                                    }
                                    
                                    // Obtenir les données de stock
                                    $stockCheckStmt = mysqli_prepare($con, "
                                        SELECT 
                                            p.Stock AS initial_stock,
                                            (
                                                SELECT COALESCE(SUM(cc.ProductQty), 0)
                                                FROM tblcreditcart cc
                                                WHERE cc.ProductId = p.ID AND cc.IsCheckOut = 1
                                            ) AS sold_qty,
                                            (
                                                SELECT COALESCE(SUM(r.Quantity), 0)
                                                FROM tblreturns r
                                                WHERE r.ProductID = p.ID
                                            ) AS returned_qty,
                                            (
                                                SELECT COALESCE(SUM(cc.ProductQty), 0)
                                                FROM tblcreditcart cc
                                                WHERE cc.ProductId = p.ID AND cc.IsCheckOut = 0 AND cc.ID != ?
                                            ) AS other_carts_qty
                                        FROM tblproducts p
                                        WHERE p.ID = ?
                                    ");
                                    mysqli_stmt_bind_param($stockCheckStmt, "ii", $cartItemId, $productId);
                                    mysqli_stmt_execute($stockCheckStmt);
                                    $stockCheckResult = mysqli_stmt_get_result($stockCheckStmt);
                                    $stockData = mysqli_fetch_assoc($stockCheckResult);
                                    
                                    $initialStock = intval($stockData['initial_stock'] ?? 0);
                                    $soldQty = intval($stockData['sold_qty'] ?? 0);
                                    $returnedQty = intval($stockData['returned_qty'] ?? 0);
                                    $otherCartsQty = intval($stockData['other_carts_qty'] ?? 0);
                                    
                                    // Calcul correct du stock disponible
                                    $realStock = $initialStock - $soldQty + $returnedQty - $otherCartsQty;
                                    $realStock = max(0, $realStock);
                                    
                                    $disableAdd = ($realStock <= 0);
                                    $rowClass = $disableAdd ? 'class="stock-error"' : '';
                                    $stockStatus = '';
                                    
                                    if ($realStock <= 0) {
                                        $stockStatus = '<span class="stock-status stock-danger">Rupture</span>';
                                    } elseif ($realStock < 5) {
                                        $stockStatus = '<span class="stock-status stock-warning">Faible</span>';
                                    } else {
                                        $stockStatus = '<span class="stock-status stock-ok">Disponible</span>';
                                    }
                                    ?>
                                    <tr <?php echo $rowClass; ?>>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['CategoryName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['SubCategoryName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['BrandName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['ModelNumber']); ?></td>
                                        <td><?php echo number_format($row['Price'], 2); ?></td>
                                        <td><?php echo $realStock . ' ' . $stockStatus; ?></td>
                                        <td>
                                            <form method="post" action="dettecart.php" style="margin:0;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
                                                <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>" />
                                                <input type="number" name="price" step="any" 
                                                       value="<?php echo $row['Price']; ?>" style="width:80px;" />
                                        </td>
                                        <td>
                                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $realStock; ?>" style="width:60px;" <?php echo $disableAdd ? 'disabled' : ''; ?> />
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
  
            <!-- ====================== AFFICHAGE DU PANIER + REMISE + CHECKOUT ====================== -->
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

                    <!-- FORMULAIRE DE CHECKOUT (informations client + montant payé) -->
                    <form method="post" class="form-horizontal" id="checkoutForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>" />
                        <div class="control-group">
                            <label class="control-label">Nom du Client :</label>
                            <div class="controls">
                                <input type="text" class="span11" name="customername" required />
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label">Numéro de Mobile :</label>
                            <div class="controls">
                                <!-- Validation pour le format guinéen : +224 suivi de 9 chiffres -->
                                <input type="tel"
                                       class="span11"
                                       name="mobilenumber"
                                       required
                                       pattern="^\+224[0-9]{9}$"
                                       placeholder="+224-XXXXXXXXX"
                                       title="Format: +224 suivi de 9 chiffres">
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
                            <button class="btn btn-primary" type="submit" name="submit" id="submitCheckout" <?php echo $hasStockIssue ? 'disabled' : ''; ?>>
                                Valider & Créer la Facture
                            </button>
                        </div>
                    </form>
  
                    <!-- Tableau du panier -->
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5>Articles dans le Panier</h5>
                        </div>
                        <div class="widget-content nopadding">
                            <table class="table table-bordered" style="font-size: 15px">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom du Article</th>
                                        <th>Quantité</th>
                                        <th>Stock</th>
                                        <th>Prix (unité)</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Requête améliorée pour afficher le panier avec stock réel
                                    $cartQuery = "
                                      SELECT 
                                        c.ID as cid,
                                        c.ProductId,
                                        c.ProductQty,
                                        c.Price as cartPrice,
                                        p.ProductName,
                                        p.Stock as initial_stock
                                      FROM tblcreditcart c
                                      LEFT JOIN tblproducts p ON p.ID = c.ProductId
                                      WHERE c.IsCheckOut = 0
                                      ORDER BY c.ID ASC
                                    ";
                                    
                                    $stmt = mysqli_prepare($con, $cartQuery);
                                    mysqli_stmt_execute($stmt);
                                    $ret = mysqli_stmt_get_result($stmt);
                                    
                                    $cnt = 1;
                                    $grandTotal = 0;
                                    $num = mysqli_num_rows($ret);
                                    if ($num > 0) {
                                        while ($row = mysqli_fetch_array($ret)) {
                                            $cartId = $row['cid'];
                                            $productId = $row['ProductId'];
                                            $pq = $row['ProductQty'];
                                            $ppu = $row['cartPrice'];
                                            $initialStock = intval($row['initial_stock']);
                                            
                                            // Obtenir les données pour le calcul du stock
                                            $stockStmt = mysqli_prepare($con, "
                                                SELECT 
                                                    (
                                                        SELECT COALESCE(SUM(cc.ProductQty), 0)
                                                        FROM tblcreditcart cc
                                                        WHERE cc.ProductId = ? AND cc.IsCheckOut = 1
                                                    ) AS sold_qty,
                                                    (
                                                        SELECT COALESCE(SUM(r.Quantity), 0)
                                                        FROM tblreturns r
                                                        WHERE r.ProductID = ?
                                                    ) AS returned_qty,
                                                    (
                                                        SELECT COALESCE(SUM(cc.ProductQty), 0)
                                                        FROM tblcreditcart cc
                                                        WHERE cc.ProductId = ? AND cc.IsCheckOut = 0 AND cc.ID != ?
                                                    ) AS other_carts_qty
                                            ");
                                            mysqli_stmt_bind_param($stockStmt, "iiii", $productId, $productId, $productId, $cartId);
                                            mysqli_stmt_execute($stockStmt);
                                            $stockResult = mysqli_stmt_get_result($stockStmt);
                                            $stockData = mysqli_fetch_assoc($stockResult);
                                            
                                            $soldQty = intval($stockData['sold_qty']);
                                            $returnedQty = intval($stockData['returned_qty']);
                                            $otherCartsQty = intval($stockData['other_carts_qty']);
                                            
                                            // Calcul correct du stock réellement disponible
                                            $realStock = $initialStock - $soldQty + $returnedQty - $otherCartsQty;
                                            $lineTotal = $pq * $ppu;
                                            $grandTotal += $lineTotal;
                                            
                                            // Vérification du stock pour cette ligne
                                            $stockIssue = ($realStock < $pq);
                                            $rowClass = $stockIssue ? 'class="stock-error"' : '';
                                            $stockStatus = '';
                                            
                                            if ($realStock <= 0) {
                                                $stockStatus = '<span class="stock-warning">RUPTURE</span>';
                                            } elseif ($realStock < $pq) {
                                                $stockStatus = '<span class="stock-warning">INSUFFISANT</span>';
                                            }
                                            ?>
                                            <tr <?php echo $rowClass; ?>>
                                                <td><?php echo $cnt; ?></td>
                                                <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                                <td><?php echo $pq; ?></td>
                                                <td>
                                                    <?php echo $realStock; ?>
                                                    <?php echo $stockStatus; ?>
                                                </td>
                                                <td><?php echo number_format($ppu, 2); ?></td>
                                                <td><?php echo number_format($lineTotal, 2); ?></td>
                                                <td>
                                                    <a href="dettecart.php?delid=<?php echo $row['cid']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>"
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
                                       <!-- Affichage de la remise dans le tableau des totaux -->
                                        <tr>
                                            <th colspan="5" style="text-align: right; font-weight: bold;">Total Général</th>
                                            <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($grandTotal, 2); ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="5" style="text-align: right; font-weight: bold;">
                                                Remise
                                                <?php if ($discountType == 'percentage'): ?>
                                                    (<?php echo htmlspecialchars($discountValue); ?>%)
                                                <?php endif; ?>
                                            </th>
                                            <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($discount, 2); ?></th>
                                        </tr>
                                        <tr>
                                            <th colspan="5" style="text-align: right; font-weight: bold; color: green;">Total Net</th>
                                            <th colspan="2" style="text-align: center; font-weight: bold; color: green;"><?php echo number_format($netTotal, 2); ?></th>
                                        </tr>
                                        <?php
                                    } else {
                                        ?>
                                        <tr>
                                            <td colspan="7" style="color:red; text-align:center;">Aucun article trouvé dans le panier</td>
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Vérifier la présence d'articles dans le panier avant de permettre la validation
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
                const stockWarning = <?php echo $hasStockIssue ? 'true' : 'false'; ?>;
                if (stockWarning) {
                    e.preventDefault();
                    alert('Il y a des problèmes de stock avec certains articles dans votre panier. Veuillez les ajuster avant de continuer.');
                    return false;
                }
            });
        }
    });
    </script>
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