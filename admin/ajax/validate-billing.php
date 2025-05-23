<?php
// File: ajax/validate-billing.php - VERSION SÉCURISÉE ET AMÉLIORÉE
session_start();
include('../includes/dbconnection.php');

// Définir le type de contenu JSON avec encodage UTF-8
header('Content-Type: application/json; charset=utf-8');

// Fonction utilitaire pour retourner une réponse JSON et terminer
function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Vérification de la session admin
if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
    jsonResponse([
        'valid' => false, 
        'message' => 'Session expirée. Veuillez vous reconnecter.',
        'redirect' => 'logout.php'
    ]);
}

// Vérification du paramètre billingnumber
if (!isset($_POST['billingnumber'])) {
    jsonResponse([
        'valid' => false,
        'message' => 'Numéro de facture requis.'
    ]);
}

// Nettoyage et validation de l'entrée
$billingNumber = trim($_POST['billingnumber']);

if (empty($billingNumber)) {
    jsonResponse([
        'valid' => false,
        'message' => 'Numéro de facture ne peut pas être vide.'
    ]);
}

// Validation du format (ajustez selon vos besoins)
if (strlen($billingNumber) < 3 || strlen($billingNumber) > 50) {
    jsonResponse([
        'valid' => false,
        'message' => 'Format de numéro de facture invalide (3-50 caractères attendus).'
    ]);
}

try {
    // ========================================
    // 1. Vérifier l'existence de la facture et récupérer les infos client
    // ========================================
    $stmt = $con->prepare("
        SELECT 
            CustomerName,
            MobileNumber,
            BillingNumber,
            PostingDate,
            ID as CustomerID
        FROM tblcustomer 
        WHERE BillingNumber = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $billingNumber);
    $stmt->execute();
    $customerResult = $stmt->get_result();
    
    if ($customerResult->num_rows == 0) {
        $stmt->close();
        jsonResponse([
            'valid' => false,
            'message' => 'Numéro de facture introuvable dans le système.'
        ]);
    }
    
    $customer = $customerResult->fetch_assoc();
    $stmt->close();
    
    // ========================================
    // 2. Récupérer les produits vendus et calculer le montant total
    // ========================================
    $stmt2 = $con->prepare("
        SELECT 
            c.ProductId,
            c.ProductQty,
            c.Price,
            p.ProductName,
            p.CompanyName,
            p.Stock as CurrentStock,
            (c.ProductQty * c.Price) as LineTotal,
            COALESCE(r.returned_qty, 0) as ReturnedQty,
            (c.ProductQty - COALESCE(r.returned_qty, 0)) as AvailableReturn
        FROM tblcart c
        INNER JOIN tblproducts p ON p.ID = c.ProductId
        LEFT JOIN (
            SELECT 
                ProductID, 
                SUM(Quantity) as returned_qty 
            FROM tblreturns 
            WHERE BillingNumber = ? 
            GROUP BY ProductID
        ) r ON r.ProductID = c.ProductId
        WHERE c.BillingId = ? AND c.IsCheckOut = 1
        ORDER BY p.ProductName ASC
    ");
    
    $stmt2->bind_param("ss", $billingNumber, $billingNumber);
    $stmt2->execute();
    $productsResult = $stmt2->get_result();
    
    // ========================================
    // 3. Construire les options de produits et calculer les totaux
    // ========================================
    $productOptions = '<option value="">-- Sélectionner un produit --</option>';
    $totalSaleAmount = 0;
    $totalProductCount = 0;
    $returnableProductCount = 0;
    
    if ($productsResult->num_rows > 0) {
        while ($product = $productsResult->fetch_assoc()) {
            $totalSaleAmount += $product['LineTotal'];
            $totalProductCount++;
            
            $productName = htmlspecialchars($product['ProductName']);
            $brandInfo = $product['CompanyName'] ? ' - ' . htmlspecialchars($product['CompanyName']) : '';
            $availableReturn = intval($product['AvailableReturn']);
            
            if ($availableReturn > 0) {
                $returnableProductCount++;
                $statusBadge = '✅';
                $statusText = "Retournable: {$availableReturn}";
            } else {
                $statusBadge = '❌';
                $statusText = "Entièrement retourné";
            }
            
            $optionText = "{$statusBadge} {$productName}{$brandInfo} | " .
                         "Vendu: {$product['ProductQty']} | " .
                         "Prix: " . number_format($product['Price'], 2) . " GNF | " .
                         $statusText;
            
            // Ajouter l'option même si non retournable (pour information)
            $productOptions .= sprintf(
                '<option value="%d" %s data-original-qty="%d" data-returned-qty="%d" data-available="%d" data-price="%.2f">%s</option>',
                $product['ProductId'],
                $availableReturn <= 0 ? 'disabled style="color: #999;"' : '',
                $product['ProductQty'],
                $product['ReturnedQty'],
                $availableReturn,
                $product['Price'],
                $optionText
            );
        }
    }
    
    $stmt2->close();
    
    // ========================================
    // 4. Construire l'affichage des informations client
    // ========================================
    $saleDate = date('d/m/Y à H:i', strtotime($customer['PostingDate']));
    $daysSinceSale = round((time() - strtotime($customer['PostingDate'])) / (24 * 3600));
    
    $customerInfo = "
        <div style='padding: 12px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #28a745;'>
            <h5 style='margin-top: 0; color: #28a745;'>
                <i class='icon-ok-circle'></i> Facture Validée
            </h5>
            
            <div class='row-fluid'>
                <div class='span6'>
                    <strong>👤 Client:</strong> " . htmlspecialchars($customer['CustomerName']) . "<br>
                    <strong>📞 Téléphone:</strong> " . htmlspecialchars($customer['MobileNumber']) . "<br>
                    <strong>🧾 N° Facture:</strong> " . htmlspecialchars($customer['BillingNumber']) . "
                </div>
                <div class='span6'>
                    <strong>📅 Date de vente:</strong> {$saleDate}<br>
                    <strong>⏰ Ancienneté:</strong> {$daysSinceSale} jour(s)<br>
                    <strong>💰 Montant total:</strong> " . number_format($totalSaleAmount, 2) . " GNF
                </div>
            </div>
            
            <div style='margin-top: 10px; padding: 8px; background: white; border-radius: 3px;'>
                <strong>📊 Résumé:</strong> 
                {$totalProductCount} produit(s) vendus • 
                {$returnableProductCount} produit(s) retournable(s)
            </div>";
    
    // ========================================
    // 5. Ajouter des alertes si nécessaire
    // ========================================
    $alerts = [];
    
    // Vérifier l'âge de la vente
    if ($daysSinceSale > 30) {
        $alerts[] = "⚠️ Vente ancienne ({$daysSinceSale} jours). Vérifiez la politique de retour.";
    }
    
    // Vérifier s'il y a des produits retournables
    if ($returnableProductCount == 0 && $totalProductCount > 0) {
        $alerts[] = "ℹ️ Tous les produits de cette facture ont déjà été retournés.";
    }
    
    // Ajouter les alertes s'il y en a
    if (!empty($alerts)) {
        $customerInfo .= "<div style='margin-top: 10px;'>";
        foreach ($alerts as $alert) {
            $customerInfo .= "<div class='alert alert-warning' style='margin-bottom: 5px; padding: 5px 10px;'>{$alert}</div>";
        }
        $customerInfo .= "</div>";
    }
    
    $customerInfo .= "</div>";
    
    // ========================================
    // 6. Gérer le cas où aucun produit n'est trouvé
    // ========================================
    if ($productsResult->num_rows == 0) {
        jsonResponse([
            'valid' => false,
            'message' => 'Aucun produit trouvé pour cette facture. Elle pourrait être une vente crédit ou incomplète.',
            'customerInfo' => $customerInfo
        ]);
    }
    
    // ========================================
    // 7. Retourner la réponse de succès
    // ========================================
    jsonResponse([
        'valid' => true,
        'customerInfo' => $customerInfo,
        'productOptions' => $productOptions,
        'statistics' => [
            'totalProducts' => $totalProductCount,
            'returnableProducts' => $returnableProductCount,
            'totalAmount' => $totalSaleAmount,
            'daysSinceSale' => $daysSinceSale,
            'customerName' => $customer['CustomerName']
        ]
    ]);
    
} catch (Exception $e) {
    // Log l'erreur pour debugging (adaptez selon votre système de logging)
    error_log("Erreur dans validate-billing.php: " . $e->getMessage() . " | Billing: " . $billingNumber);
    
    jsonResponse([
        'valid' => false,
        'message' => 'Erreur interne du serveur. Veuillez réessayer ou contacter l\'administrateur.'
    ]);
}

// Fermer la connexion à la base de données
if (isset($con) && $con) {
    mysqli_close($con);
}
?>