<?php
// Version TRÈS SIMPLIFIÉE pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('../includes/dbconnection.php');

// Headers JSON
header('Content-Type: application/json; charset=utf-8');

// Log pour debug
error_log("=== VALIDATE BILLING DEBUG START ===");

try {
    // Vérification session
    if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
        error_log("❌ Session invalide");
        echo json_encode([
            'valid' => false, 
            'message' => 'Session invalide',
            'debug' => 'Session not set'
        ]);
        exit;
    }

    // Vérification paramètre
    if (!isset($_POST['billingnumber'])) {
        error_log("❌ Paramètre billingnumber manquant");
        echo json_encode([
            'valid' => false,
            'message' => 'Paramètre manquant',
            'debug' => 'billingnumber not set'
        ]);
        exit;
    }

    $billingNumber = trim($_POST['billingnumber']);
    error_log("🔍 Test pour facture: " . $billingNumber);

    if (empty($billingNumber)) {
        error_log("❌ Numéro de facture vide");
        echo json_encode([
            'valid' => false,
            'message' => 'Numéro de facture vide'
        ]);
        exit;
    }

    // Test 1: Vérifier tblcustomer
    $customerQuery = "SELECT * FROM tblcustomer WHERE BillingNumber = '" . mysqli_real_escape_string($con, $billingNumber) . "'";
    $customerResult = mysqli_query($con, $customerQuery);
    
    error_log("🔎 Requête customer: " . $customerQuery);
    error_log("🔎 Résultats customer: " . mysqli_num_rows($customerResult));

    if (mysqli_num_rows($customerResult) == 0) {
        error_log("❌ Facture non trouvée dans tblcustomer");
        echo json_encode([
            'valid' => false,
            'message' => 'Facture non trouvée dans tblcustomer'
        ]);
        exit;
    }

    $customer = mysqli_fetch_assoc($customerResult);
    error_log("✅ Client trouvé: " . $customer['CustomerName']);

    // Test 2: Compter dans tblcart
    $cartQuery = "SELECT COUNT(*) as count FROM tblcart WHERE BillingId = '" . mysqli_real_escape_string($con, $billingNumber) . "'";
    $cartResult = mysqli_query($con, $cartQuery);
    $cartCount = mysqli_fetch_assoc($cartResult)['count'];
    
    error_log("🛒 Produits dans tblcart: " . $cartCount);

    // Test 3: Compter dans tblcreditcart
    $creditQuery = "SELECT COUNT(*) as count FROM tblcreditcart WHERE BillingId = '" . mysqli_real_escape_string($con, $billingNumber) . "'";
    $creditResult = mysqli_query($con, $creditQuery);
    $creditCount = mysqli_fetch_assoc($creditResult)['count'];
    
    error_log("💳 Produits dans tblcreditcart: " . $creditCount);

    // Déterminer la table
    $useTable = ($creditCount > 0) ? 'tblcreditcart' : 'tblcart';
    $totalProducts = $cartCount + $creditCount;
    
    error_log("📊 Table à utiliser: " . $useTable . " | Total produits: " . $totalProducts);

    if ($totalProducts == 0) {
        error_log("❌ Aucun produit trouvé");
        echo json_encode([
            'valid' => false,
            'message' => 'Aucun produit trouvé dans cette facture',
            'debug' => [
                'cartCount' => $cartCount,
                'creditCount' => $creditCount,
                'useTable' => $useTable
            ]
        ]);
        exit;
    }

    // Récupérer les produits
    $productsQuery = "SELECT 
                        p.ID as ProductId,
                        p.ProductName,
                        cart.ProductQty
                    FROM {$useTable} cart
                    JOIN tblproducts p ON cart.ProductId = p.ID
                    WHERE cart.BillingId = '" . mysqli_real_escape_string($con, $billingNumber) . "'
                    ORDER BY p.ProductName";
    
    $productsResult = mysqli_query($con, $productsQuery);
    
    error_log("🔎 Requête produits: " . $productsQuery);
    error_log("🔎 Résultats produits: " . mysqli_num_rows($productsResult));

    if (mysqli_num_rows($productsResult) == 0) {
        error_log("❌ Aucun produit trouvé avec JOIN");
        echo json_encode([
            'valid' => false,
            'message' => 'Aucun produit trouvé avec JOIN',
            'debug' => [
                'query' => $productsQuery,
                'useTable' => $useTable
            ]
        ]);
        exit;
    }

    // Construire les options
    $productOptions = '<option value="">-- Sélectionner un produit --</option>';
    $productCount = 0;
    
    while ($product = mysqli_fetch_assoc($productsResult)) {
        $productCount++;
        $productOptions .= '<option value="' . $product['ProductId'] . '">' . 
                          htmlspecialchars($product['ProductName']) . 
                          ' (Qté: ' . $product['ProductQty'] . ')' .
                          '</option>';
        
        error_log("📦 Produit ajouté: " . $product['ProductName'] . " (ID: " . $product['ProductId'] . ")");
    }

    // Informations client simplifiées
    $customerInfo = "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
    $customerInfo .= "<strong>✅ Facture Validée</strong><br>";
    $customerInfo .= "<strong>Client:</strong> " . htmlspecialchars($customer['CustomerName']) . "<br>";
    $customerInfo .= "<strong>Téléphone:</strong> " . htmlspecialchars($customer['MobileNumber']) . "<br>";
    $customerInfo .= "<strong>Table utilisée:</strong> {$useTable}<br>";
    $customerInfo .= "<strong>Produits trouvés:</strong> {$productCount}";
    $customerInfo .= "</div>";

    error_log("✅ Succès - " . $productCount . " produits trouvés");

    // Réponse de succès
    echo json_encode([
        'valid' => true,
        'customerInfo' => $customerInfo,
        'productOptions' => $productOptions,
        'debug' => [
            'useTable' => $useTable,
            'cartCount' => $cartCount,
            'creditCount' => $creditCount,
            'productCount' => $productCount,
            'customerName' => $customer['CustomerName']
        ]
    ]);

} catch (Exception $e) {
    error_log("❌ Exception: " . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'debug' => $e->getTraceAsString()
    ]);
}

error_log("=== VALIDATE BILLING DEBUG END ===");
?>