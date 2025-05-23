<?php
// File: ajax/validate-billing.php - VERSION CORRIGÉE BASÉE SUR invoice-search.php
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
    // 1. Vérifier l'existence de la facture - MÊME LOGIQUE QUE invoice-search.php
    // ========================================
    $stmt = $con->prepare("SELECT DISTINCT 
                          tblcustomer.CustomerName,
                          tblcustomer.MobileNumber,
                          tblcustomer.ModeofPayment,
                          tblcustomer.BillingDate,
                          tblcustomer.BillingNumber,
                          tblcustomer.FinalAmount,
                          tblcustomer.Paid,
                          tblcustomer.Dues
                        FROM 
                          tblcustomer
                        LEFT JOIN 
                          tblcart ON tblcustomer.BillingNumber = tblcart.BillingId
                        LEFT JOIN 
                          tblcreditcart ON tblcustomer.BillingNumber = tblcreditcart.BillingId
                        WHERE 
                          tblcustomer.BillingNumber = ?
                        LIMIT 1");
    
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
    // 2. Déterminer quelle table utiliser - MÊME LOGIQUE QUE invoice-search.php
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
    // 3. Récupérer les produits selon la table appropriée
    // ========================================
    $stmt2 = $con->prepare("
        SELECT 
            p.ID as ProductId,
            p.ProductName,
            p.CompanyName,
            p.ModelNumber,
            p.Stock as CurrentStock,
            cart.ProductQty,
            COALESCE(cart.Price, p.Price) as Price,
            COALESCE(r.returned_qty, 0) as ReturnedQty,
            (cart.ProductQty - COALESCE(r.returned_qty, 0)) as AvailableReturn
        FROM {$useTable} cart
        INNER JOIN tblproducts p ON cart.ProductId = p.ID
        LEFT JOIN (
            SELECT 
                ProductID, 
                SUM(Quantity) as returned_qty 
            FROM tblreturns 
            WHERE BillingNumber = ? 
            GROUP BY ProductID
        ) r ON r.ProductID = cart.ProductId
        WHERE cart.BillingId = ?
        ORDER BY p.ProductName ASC
    ");
    
    $stmt2->bind_param("ss", $billingNumber, $billingNumber);
    $stmt2->execute();
    $productsResult = $stmt2->get_result();
    
    // ========================================
    // 4. Construire les options de produits
    // ========================================
    $productOptions = '<option value="">-- Sélectionner un produit --</option>';
    $totalSaleAmount = 0;
    $totalProductCount = 0;
    $returnableProductCount = 0;
    
    if ($productsResult->num_rows > 0) {
        while ($product = $productsResult->fetch_assoc()) {
            $lineTotal = $product['ProductQty'] * $product['Price'];
            $totalSaleAmount += $lineTotal;
            $totalProductCount++;
            
            $productName = htmlspecialchars($product['ProductName']);
            $brandInfo = $product['CompanyName'] ? ' - ' . htmlspecialchars($product['CompanyName']) : '';
            $modelInfo = $product['ModelNumber'] ? ' (Ref: ' . htmlspecialchars($product['ModelNumber']) . ')' : '';
            $availableReturn = intval($product['AvailableReturn']);
            
            if ($availableReturn > 0) {
                $returnableProductCount++;
                $statusBadge = '✅';
                $statusText = "Retournable: {$availableReturn}";
                $disabled = '';
                $style = '';
            } else {
                $statusBadge = '❌';
                $statusText = "Entièrement retourné";
                $disabled = 'disabled';
                $style = 'style="color: #999;"';
            }
            
            $optionText = "{$statusBadge} {$productName}{$brandInfo}{$modelInfo} | " .
                         "Vendu: {$product['ProductQty']} | " .
                         "Prix: " . number_format($product['Price'], 2) . " GNF | " .
                         $statusText;
            
            $productOptions .= sprintf(
                '<option value="%d" %s %s data-original-qty="%d" data-returned-qty="%d" data-available="%d" data-price="%.2f">%s</option>',
                $product['ProductId'],
                $disabled,
                $style,
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
    // 5. Construire l'affichage des informations client
    // ========================================
    $saleDate = date('d/m/Y à H:i', strtotime($customer['BillingDate']));
    $daysSinceSale = round((time() - strtotime($customer['BillingDate'])) / (24 * 3600));
    
    // Déterminer si c'est une facture à terme
    $isCredit = ($customer['Dues'] > 0 || $customer['ModeofPayment'] == 'credit');
    
    $customerInfo = "
        <div style='padding: 12px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #28a745;'>
            <h5 style='margin-top: 0; color: #28a745;'>
                <i class='icon-ok-circle'></i> Facture Validée
            </h5>
            
            <div class='row-fluid'>
                <div class='span6'>
                    <strong>👤 Client:</strong> " . htmlspecialchars($customer['CustomerName']) . "<br>
                    <strong>📞 Téléphone:</strong> " . htmlspecialchars($customer['MobileNumber']) . "<br>
                    <strong>🧾 N° Facture:</strong> " . htmlspecialchars($customer['BillingNumber']) . "<br>
                    <strong>💳 Type:</strong> {$saleType}
                </div>
                <div class='span6'>
                    <strong>📅 Date de vente:</strong> {$saleDate}<br>
                    <strong>⏰ Ancienneté:</strong> {$daysSinceSale} jour(s)<br>
                    <strong>💰 Montant total:</strong> " . number_format($customer['FinalAmount'] ?: $totalSaleAmount, 2) . " GNF";
    
    // Ajouter les informations de crédit si applicable
    if ($isCredit) {
        $customerInfo .= "<br><strong>💵 Payé:</strong> " . number_format($customer['Paid'], 2) . " GNF";
        $customerInfo .= "<br><strong>🔴 Reste dû:</strong> " . number_format($customer['Dues'], 2) . " GNF";
    }
    
    $customerInfo .= "
                </div>
            </div>
            
            <div style='margin-top: 10px; padding: 8px; background: white; border-radius: 3px;'>
                <strong>📊 Résumé:</strong> 
                {$totalProductCount} produit(s) vendus • 
                {$returnableProductCount} produit(s) retournable(s) • 
                Table utilisée: {$useTable}
            </div>";
    
    // ========================================
    // 6. Ajouter des alertes si nécessaire
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
    
    // Alerter pour les ventes à crédit avec dues
    if ($isCredit && $customer['Dues'] > 0) {
        $alerts[] = "💳 Vente à crédit avec un solde restant de " . number_format($customer['Dues'], 2) . " GNF.";
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
    // 7. Gérer le cas où aucun produit n'est trouvé
    // ========================================
    if ($productsResult->num_rows == 0) {
        jsonResponse([
            'valid' => false,
            'message' => 'Aucun produit trouvé pour cette facture dans les tables tblcart et tblcreditcart.',
            'customerInfo' => $customerInfo,
            'debug' => [
                'creditItems' => $creditItems,
                'regularItems' => $regularItems,
                'useTable' => $useTable
            ]
        ]);
    }
    
    // ========================================
    // 8. Retourner la réponse de succès
    // ========================================
    jsonResponse([
        'valid' => true,
        'customerInfo' => $customerInfo,
        'productOptions' => $productOptions,
        'statistics' => [
            'totalProducts' => $totalProductCount,
            'returnableProducts' => $returnableProductCount,
            'totalAmount' => $customer['FinalAmount'] ?: $totalSaleAmount,
            'daysSinceSale' => $daysSinceSale,
            'customerName' => $customer['CustomerName'],
            'saleType' => $saleType,
            'useTable' => $useTable,
            'isCredit' => $isCredit
        ]
    ]);
    
} catch (Exception $e) {
    // Log l'erreur pour debugging
    error_log("Erreur dans validate-billing.php: " . $e->getMessage() . " | Billing: " . $billingNumber);
    
    jsonResponse([
        'valid' => false,
        'message' => 'Erreur interne du serveur. Veuillez réessayer ou contacter l\'administrateur.',
        'debug' => $e->getMessage()
    ]);
}

// Fermer la connexion à la base de données
if (isset($con) && $con) {
    mysqli_close($con);
}
?>