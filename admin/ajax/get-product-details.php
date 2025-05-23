<?php
// File: ajax/get-product-details.php - VERSION CORRIGÉE BASÉE SUR invoice-search.php
session_start();
include('../includes/dbconnection.php');

// Définir le type de contenu JSON
header('Content-Type: application/json; charset=utf-8');

// Fonction pour retourner une réponse JSON et terminer
function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Vérification de la session admin
if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
    jsonResponse([
        'success' => false, 
        'message' => 'Session expirée. Veuillez vous reconnecter.'
    ]);
}

// Vérification des paramètres requis
if (!isset($_POST['productid']) || !isset($_POST['billingnumber'])) {
    jsonResponse([
        'success' => false,
        'message' => 'Paramètres manquants (productid ou billingnumber).'
    ]);
}

// Validation et nettoyage des entrées
$productID = intval($_POST['productid']);
$billingNumber = trim($_POST['billingnumber']);

if ($productID <= 0) {
    jsonResponse([
        'success' => false,
        'message' => 'ID de produit invalide.'
    ]);
}

if (empty($billingNumber)) {
    jsonResponse([
        'success' => false,
        'message' => 'Numéro de facture invalide.'
    ]);
}

try {
    // ========================================
    // 1. Déterminer quelle table utiliser - MÊME LOGIQUE QUE invoice-search.php
    // ========================================
    $checkCreditCart = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcreditcart WHERE BillingId='$billingNumber'");
    $checkRegularCart = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcart WHERE BillingId='$billingNumber'");
    
    $creditItems = 0;
    $regularItems = 0;
    
    if ($rowCredit = mysqli_fetch_assoc($checkCreditCart)) {
        $creditItems = $rowCredit['count'];
    }
    
    if ($rowRegular = mysqli_fetch_assoc($checkRegularCart)) {
        $regularItems = $rowRegular['count'];
    }
    
    // Déterminer quelle table utiliser
    $useTable = ($creditItems > 0) ? 'tblcreditcart' : 'tblcart';
    $saleType = ($creditItems > 0) ? 'Vente à Terme' : 'Vente Cash';
    
    // ========================================
    // 2. Récupérer les détails du produit et de la vente originale
    // ========================================
    $stmt = $con->prepare("
        SELECT 
            p.ProductName,
            p.CompanyName,
            p.ModelNumber,
            p.Stock as CurrentStock,
            cart.ProductQty as OriginalQty,
            COALESCE(cart.Price, p.Price) as OriginalPrice,
            cust.CustomerName,
            cust.BillingDate as SaleDate,
            cust.ModeofPayment,
            cust.Dues,
            cust.Paid
        FROM {$useTable} cart
        INNER JOIN tblproducts p ON p.ID = cart.ProductId
        INNER JOIN tblcustomer cust ON cust.BillingNumber = cart.BillingId
        WHERE cart.BillingId = ? AND cart.ProductId = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("si", $billingNumber, $productID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        jsonResponse([
            'success' => false,
            'message' => "Ce produit n'a pas été vendu dans cette facture (vérification dans {$useTable}).",
            'debug' => [
                'useTable' => $useTable,
                'creditItems' => $creditItems,
                'regularItems' => $regularItems
            ]
        ]);
    }
    
    $saleData = $result->fetch_assoc();
    $stmt->close();
    
    // ========================================
    // 3. Calculer les quantités déjà retournées
    // ========================================
    $stmt2 = $con->prepare("
        SELECT 
            COALESCE(SUM(Quantity), 0) as TotalReturned,
            COUNT(*) as ReturnCount,
            MAX(ReturnDate) as LastReturnDate
        FROM tblreturns 
        WHERE BillingNumber = ? AND ProductID = ?
    ");
    
    $stmt2->bind_param("si", $billingNumber, $productID);
    $stmt2->execute();
    $returnResult = $stmt2->get_result();
    $returnData = $returnResult->fetch_assoc();
    $stmt2->close();
    
    // ========================================
    // 4. Calculer les quantités disponibles
    // ========================================
    $originalQty = intval($saleData['OriginalQty']);
    $alreadyReturned = intval($returnData['TotalReturned']);
    $availableToReturn = $originalQty - $alreadyReturned;
    $originalPrice = floatval($saleData['OriginalPrice']);
    
    // ========================================
    // 5. Construire l'affichage des détails
    // ========================================
    $badgeClass = $availableToReturn > 0 ? 'badge-success' : 'badge-important';
    $statusText = $availableToReturn > 0 ? 'Disponible' : 'Épuisé';
    
    // Déterminer si c'est une vente à crédit
    $isCredit = ($saleData['Dues'] > 0 || $saleData['ModeofPayment'] == 'credit');
    $creditInfo = $isCredit ? ' (Vente à Crédit)' : ' (Vente Cash)';
    
    $details = "
        <div style='padding: 10px; border-left: 4px solid #2c5aa0;'>
            <h5 style='margin-top: 0; color: #2c5aa0;'>" . htmlspecialchars($saleData['ProductName']) . "</h5>
            
            <div class='row-fluid'>
                <div class='span6'>
                    <strong>📦 Marque:</strong> " . htmlspecialchars($saleData['CompanyName'] ?: 'Non spécifiée') . "<br>
                    <strong>🔖 Référence:</strong> " . htmlspecialchars($saleData['ModelNumber'] ?: 'Non spécifiée') . "<br>
                    <strong>👤 Client:</strong> " . htmlspecialchars($saleData['CustomerName']) . "<br>
                    <strong>📅 Date de vente:</strong> " . date('d/m/Y', strtotime($saleData['SaleDate'])) . "<br>
                    <strong>💰 Prix unitaire:</strong> " . number_format($originalPrice, 2) . " GNF
                </div>
                <div class='span6'>
                    <strong>📊 Vendu:</strong> <span class='badge badge-info'>{$originalQty}</span><br>
                    <strong>↩️ Retourné:</strong> <span class='badge badge-warning'>{$alreadyReturned}</span><br>
                    <strong>✅ Disponible:</strong> <span class='badge {$badgeClass}'>{$availableToReturn}</span><br>
                    <strong>📦 Stock actuel:</strong> <span class='badge'>" . intval($saleData['CurrentStock']) . "</span><br>
                    <strong>💳 Type:</strong> {$saleType}
                </div>
            </div>";
    
    // Ajouter des informations sur les retours précédents s'il y en a
    if ($returnData['ReturnCount'] > 0) {
        $details .= "
            <div style='margin-top: 10px; padding: 8px; background: #f9f9f9; border-radius: 3px;'>
                <small>
                    <strong>ℹ️ Historique:</strong> {$returnData['ReturnCount']} retour(s) effectué(s). 
                    Dernier retour le " . date('d/m/Y', strtotime($returnData['LastReturnDate'])) . "
                </small>
            </div>";
    }
    
    // Informations sur la vente à crédit si applicable
    if ($isCredit) {
        $details .= "
            <div style='margin-top: 10px; padding: 8px; background: #fff3cd; border-radius: 3px; border: 1px solid #ffeeba;'>
                <small>
                    <strong>💳 Vente à Crédit:</strong> 
                    Payé: " . number_format($saleData['Paid'], 2) . " GNF | 
                    Reste dû: " . number_format($saleData['Dues'], 2) . " GNF
                </small>
            </div>";
    }
    
    $details .= "</div>";
    
    // ========================================
    // 6. Vérifications et messages d'alerte
    // ========================================
    $warnings = [];
    
    if ($availableToReturn <= 0) {
        $warnings[] = "⚠️ Aucune quantité disponible pour retour.";
    }
    
    if ($originalQty > 1 && $alreadyReturned > 0) {
        $warnings[] = "ℹ️ Retour partiel déjà effectué.";
    }
    
    // Vérifier l'âge de la vente (exemple: plus de 30 jours)
    $saleAge = (time() - strtotime($saleData['SaleDate'])) / (24 * 3600);
    if ($saleAge > 30) {
        $warnings[] = "⏰ Vente ancienne (" . round($saleAge) . " jours). Vérifiez la politique de retour.";
    }
    
    // Alerte pour vente à crédit avec solde
    if ($isCredit && $saleData['Dues'] > 0) {
        $warnings[] = "💳 Attention: Vente à crédit avec solde restant. Vérifiez les conditions de retour.";
    }
    
    if (!empty($warnings)) {
        $details .= "<div class='alert alert-warning' style='margin-top: 10px;'>" 
                 . implode("<br>", $warnings) . "</div>";
    }
    
    // ========================================
    // 7. Retourner la réponse JSON
    // ========================================
    jsonResponse([
        'success' => true,
        'details' => $details,
        'data' => [
            'productName' => $saleData['ProductName'],
            'originalQty' => $originalQty,
            'alreadyReturned' => $alreadyReturned,
            'maxReturn' => $availableToReturn,
            'originalPrice' => $originalPrice,
            'currentStock' => intval($saleData['CurrentStock']),
            'saleDate' => $saleData['SaleDate'],
            'customerName' => $saleData['CustomerName'],
            'canReturn' => $availableToReturn > 0,
            'saleType' => $saleType,
            'useTable' => $useTable,
            'isCredit' => $isCredit,
            'modelNumber' => $saleData['ModelNumber'],
            'companyName' => $saleData['CompanyName']
        ]
    ]);
    
} catch (Exception $e) {
    // Log l'erreur (vous devriez avoir un système de logging)
    error_log("Erreur dans get-product-details.php: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'message' => 'Erreur interne du serveur. Veuillez réessayer.',
        'debug' => $e->getMessage()
    ]);
}

// Fermer la connexion si elle est encore ouverte
if (isset($con) && $con) {
    mysqli_close($con);
}
?>