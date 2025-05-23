<?php
// Version TRÈS SIMPLIFIÉE pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('../includes/dbconnection.php');

// Headers JSON
header('Content-Type: application/json; charset=utf-8');

// Log pour debug
error_log("=== GET PRODUCT DETAILS DEBUG START ===");

try {
    // Vérification session
    if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
        error_log("❌ Session invalide");
        echo json_encode([
            'success' => false, 
            'message' => 'Session invalide'
        ]);
        exit;
    }

    // Vérification paramètres
    if (!isset($_POST['productid']) || !isset($_POST['billingnumber'])) {
        error_log("❌ Paramètres manquants");
        echo json_encode([
            'success' => false,
            'message' => 'Paramètres manquants'
        ]);
        exit;
    }

    $productID = intval($_POST['productid']);
    $billingNumber = trim($_POST['billingnumber']);
    
    error_log("🔍 Test détails - Produit: " . $productID . " | Facture: " . $billingNumber);

    if ($productID <= 0 || empty($billingNumber)) {
        error_log("❌ Paramètres invalides");
        echo json_encode([
            'success' => false,
            'message' => 'Paramètres invalides'
        ]);
        exit;
    }

    // Déterminer la table à utiliser
    $cartQuery = "SELECT COUNT(*) as count FROM tblcart WHERE BillingId = '" . mysqli_real_escape_string($con, $billingNumber) . "'";
    $cartResult = mysqli_query($con, $cartQuery);
    $cartCount = mysqli_fetch_assoc($cartResult)['count'];

    $creditQuery = "SELECT COUNT(*) as count FROM tblcreditcart WHERE BillingId = '" . mysqli_real_escape_string($con, $billingNumber) . "'";
    $creditResult = mysqli_query($con, $creditQuery);
    $creditCount = mysqli_fetch_assoc($creditResult)['count'];

    $useTable = ($creditCount > 0) ? 'tblcreditcart' : 'tblcart';
    
    error_log("📊 Table à utiliser: " . $useTable . " (cart: $cartCount, credit: $creditCount)");

    // Récupérer les détails du produit
    $detailsQuery = "SELECT 
                        p.ProductName,
                        p.CompanyName,
                        p.Stock,
                        cart.ProductQty,
                        COALESCE(cart.Price, p.Price) as Price
                    FROM {$useTable} cart
                    JOIN tblproducts p ON cart.ProductId = p.ID
                    WHERE cart.BillingId = '" . mysqli_real_escape_string($con, $billingNumber) . "'
                    AND cart.ProductId = " . $productID;

    $detailsResult = mysqli_query($con, $detailsQuery);
    
    error_log("🔎 Requête détails: " . $detailsQuery);
    error_log("🔎 Résultats détails: " . mysqli_num_rows($detailsResult));

    if (mysqli_num_rows($detailsResult) == 0) {
        error_log("❌ Produit non trouvé");
        echo json_encode([
            'success' => false,
            'message' => "Produit non trouvé dans {$useTable}",
            'debug' => [
                'query' => $detailsQuery,
                'useTable' => $useTable
            ]
        ]);
        exit;
    }

    $product = mysqli_fetch_assoc($detailsResult);
    
    error_log("✅ Produit trouvé: " . $product['ProductName']);

    // Vérifier les retours existants
    $returnsQuery = "SELECT COALESCE(SUM(Quantity), 0) as TotalReturned 
                    FROM tblreturns 
                    WHERE BillingNumber = '" . mysqli_real_escape_string($con, $billingNumber) . "'
                    AND ProductID = " . $productID;
    
    $returnsResult = mysqli_query($con, $returnsQuery);
    $alreadyReturned = mysqli_fetch_assoc($returnsResult)['TotalReturned'];
    
    error_log("📊 Déjà retourné: " . $alreadyReturned);

    // Calculs
    $originalQty = intval($product['ProductQty']);
    $availableToReturn = $originalQty - $alreadyReturned;
    $originalPrice = floatval($product['Price']);
    
    error_log("📊 Calculs - Original: $originalQty | Retourné: $alreadyReturned | Disponible: $availableToReturn");

    // Construire l'affichage
    $details = "<div style='background: #d1ecf1; padding: 10px; border-radius: 5px;'>";
    $details .= "<h5>" . htmlspecialchars($product['ProductName']) . "</h5>";
    $details .= "<strong>Marque:</strong> " . htmlspecialchars($product['CompanyName'] ?: 'Non spécifiée') . "<br>";
    $details .= "<strong>Quantité vendue:</strong> {$originalQty}<br>";
    $details .= "<strong>Déjà retourné:</strong> {$alreadyReturned}<br>";
    $details .= "<strong>Disponible pour retour:</strong> {$availableToReturn}<br>";
    $details .= "<strong>Prix unitaire:</strong> " . number_format($originalPrice, 2) . " GNF<br>";
    $details .= "<strong>Stock actuel:</strong> " . $product['Stock'] . "<br>";
    $details .= "<strong>Table utilisée:</strong> {$useTable}";
    $details .= "</div>";

    if ($availableToReturn <= 0) {
        $details .= "<div style='color: red; margin-top: 10px;'>⚠️ Aucune quantité disponible pour retour</div>";
    }

    error_log("✅ Succès - Détails générés");

    // Réponse de succès
    echo json_encode([
        'success' => true,
        'details' => $details,
        'data' => [
            'productName' => $product['ProductName'],
            'originalQty' => $originalQty,
            'alreadyReturned' => $alreadyReturned,
            'maxReturn' => $availableToReturn,
            'originalPrice' => $originalPrice,
            'currentStock' => intval($product['Stock']),
            'canReturn' => $availableToReturn > 0,
            'useTable' => $useTable
        ]
    ]);

} catch (Exception $e) {
    error_log("❌ Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'debug' => $e->getTraceAsString()
    ]);
}

error_log("=== GET PRODUCT DETAILS DEBUG END ===");
?>