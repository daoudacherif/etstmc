<?php
// ============== PAGE return.php COMPLÈTE ET AMÉLIORÉE ==============
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Vérification de la session admin
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
    exit;
}

// ==========================
// TRAITEMENT AJAX POUR LA VALIDATION DE FACTURE
// ==========================
if (isset($_POST['ajax_validate_billing'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $billingNumber = mysqli_real_escape_string($con, trim($_POST['billingnumber']));
    $response = array('valid' => false);
    
    if (empty($billingNumber)) {
        $response['message'] = 'Numéro de facture requis.';
        echo json_encode($response);
        exit;
    }
    
    // Vérifier l'existence de la facture - même logique que invoice-search.php
    $stmt = mysqli_prepare($con, "SELECT DISTINCT 
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
    
    mysqli_stmt_bind_param($stmt, "s", $billingNumber);
    mysqli_stmt_execute($stmt);
    $customerResult = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($customerResult) == 0) {
        $response['message'] = 'Numéro de facture introuvable dans le système.';
        echo json_encode($response);
        exit;
    }
    
    $customer = mysqli_fetch_assoc($customerResult);
    mysqli_stmt_close($stmt);
    
    // Déterminer quelle table utiliser
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
    
    $useTable = ($creditItems > 0) ? 'tblcreditcart' : 'tblcart';
    $saleType = ($creditItems > 0) ? 'Vente à Terme' : 'Vente Cash';
    
    // Récupérer les produits
    $stmt2 = mysqli_prepare($con, "
        SELECT 
            p.ID as ProductId,
            p.ProductName,
            p.CompanyName,
            p.ModelNumber,
            p.Stock as CurrentStock,
            cart.ProductQty,
            COALESCE(cart.Price, p.Price) as Price
        FROM $useTable cart
        INNER JOIN tblproducts p ON cart.ProductId = p.ID
        WHERE cart.BillingId = ?
        ORDER BY p.ProductName ASC
    ");
    
    mysqli_stmt_bind_param($stmt2, "s", $billingNumber);
    mysqli_stmt_execute($stmt2);
    $productsResult = mysqli_stmt_get_result($stmt2);
    
    // Construire les options de produits
    $productOptions = '<option value="">-- Sélectionner un produit --</option>';
    $totalProductCount = 0;
    $returnableProductCount = 0;
    
    while ($product = mysqli_fetch_assoc($productsResult)) {
        // Vérifier les retours existants
        $returnQuery = mysqli_query($con, "SELECT COALESCE(SUM(Quantity), 0) as returned_qty FROM tblreturns WHERE BillingNumber='$billingNumber' AND ProductID='{$product['ProductId']}'");
        $returnData = mysqli_fetch_assoc($returnQuery);
        $returnedQty = $returnData['returned_qty'];
        $availableReturn = $product['ProductQty'] - $returnedQty;
        
        $totalProductCount++;
        
        $productName = htmlspecialchars($product['ProductName']);
        $disabled = '';
        $statusBadge = '✅';
        $statusText = "Retournable: {$availableReturn}";
        
        if ($availableReturn <= 0) {
            $disabled = 'disabled';
            $statusBadge = '❌';
            $statusText = "Entièrement retourné";
        } else {
            $returnableProductCount++;
        }
        
        $productOptions .= sprintf(
            '<option value="%d" %s data-qty="%d" data-returned="%d" data-available="%d" data-price="%.2f">%s %s | Vendu: %d | Prix: %.2f GNF | %s</option>',
            $product['ProductId'],
            $disabled,
            $product['ProductQty'],
            $returnedQty,
            $availableReturn,
            $product['Price'],
            $statusBadge,
            $productName,
            $product['ProductQty'],
            $product['Price'],
            $statusText
        );
    }
    
    mysqli_stmt_close($stmt2);
    
    // Construire les informations client
    $saleDate = date('d/m/Y à H:i', strtotime($customer['BillingDate']));
    $daysSinceSale = round((time() - strtotime($customer['BillingDate'])) / (24 * 3600));
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
                    <strong>💰 Montant total:</strong> " . number_format($customer['FinalAmount'], 2) . " GNF";
    
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
            </div>
        </div>";
    
    $response = array(
        'valid' => true,
        'customerInfo' => $customerInfo,
        'productOptions' => $productOptions,
        'statistics' => array(
            'totalProducts' => $totalProductCount,
            'returnableProducts' => $returnableProductCount,
            'totalAmount' => $customer['FinalAmount'],
            'customerName' => $customer['CustomerName']
        )
    );
    
    echo json_encode($response);
    exit;
}

// ==========================
// TRAITEMENT AJAX POUR LES DÉTAILS PRODUIT
// ==========================
if (isset($_POST['ajax_get_product_details'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $productID = intval($_POST['productid']);
    $billingNumber = mysqli_real_escape_string($con, trim($_POST['billingnumber']));
    
    $response = array('success' => false);
    
    if ($productID <= 0 || empty($billingNumber)) {
        $response['message'] = 'Paramètres invalides.';
        echo json_encode($response);
        exit;
    }
    
    // Déterminer quelle table utiliser
    $checkCreditCart = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcreditcart WHERE BillingId='$billingNumber'");
    $creditItems = mysqli_fetch_assoc($checkCreditCart)['count'];
    $useTable = ($creditItems > 0) ? 'tblcreditcart' : 'tblcart';
    
    // Récupérer les détails
    $query = "
        SELECT 
            p.ProductName,
            p.CompanyName,
            p.ModelNumber,
            p.Stock as CurrentStock,
            cart.ProductQty as OriginalQty,
            COALESCE(cart.Price, p.Price) as OriginalPrice,
            cust.CustomerName,
            cust.BillingDate as SaleDate
        FROM $useTable cart
        INNER JOIN tblproducts p ON p.ID = cart.ProductId
        INNER JOIN tblcustomer cust ON cust.BillingNumber = cart.BillingId
        WHERE cart.BillingId = '$billingNumber' AND cart.ProductId = $productID
        LIMIT 1
    ";
    
    $result = mysqli_query($con, $query);
    
    if (mysqli_num_rows($result) == 0) {
        $response['message'] = "Ce produit n'a pas été vendu dans cette facture.";
        echo json_encode($response);
        exit;
    }
    
    $saleData = mysqli_fetch_assoc($result);
    
    // Calculer les quantités retournées
    $returnQuery = mysqli_query($con, "SELECT COALESCE(SUM(Quantity), 0) as TotalReturned FROM tblreturns WHERE BillingNumber='$billingNumber' AND ProductID=$productID");
    $returnData = mysqli_fetch_assoc($returnQuery);
    
    $originalQty = intval($saleData['OriginalQty']);
    $alreadyReturned = intval($returnData['TotalReturned']);
    $availableToReturn = $originalQty - $alreadyReturned;
    $originalPrice = floatval($saleData['OriginalPrice']);
    
    $details = "
        <div style='padding: 10px; border-left: 4px solid #2c5aa0;'>
            <h5 style='margin-top: 0; color: #2c5aa0;'>" . htmlspecialchars($saleData['ProductName']) . "</h5>
            
            <div class='row-fluid'>
                <div class='span6'>
                    <strong>📦 Marque:</strong> " . htmlspecialchars($saleData['CompanyName'] ?: 'Non spécifiée') . "<br>
                    <strong>🔖 Référence:</strong> " . htmlspecialchars($saleData['ModelNumber'] ?: 'Non spécifiée') . "<br>
                    <strong>💰 Prix unitaire:</strong> " . number_format($originalPrice, 2) . " GNF
                </div>
                <div class='span6'>
                    <strong>📊 Vendu:</strong> <span class='badge badge-info'>{$originalQty}</span><br>
                    <strong>↩️ Retourné:</strong> <span class='badge badge-warning'>{$alreadyReturned}</span><br>
                    <strong>✅ Disponible:</strong> <span class='badge badge-success'>{$availableToReturn}</span>
                </div>
            </div>
        </div>";
    
    $response = array(
        'success' => true,
        'details' => $details,
        'data' => array(
            'productName' => $saleData['ProductName'],
            'originalQty' => $originalQty,
            'alreadyReturned' => $alreadyReturned,
            'maxReturn' => $availableToReturn,
            'originalPrice' => $originalPrice,
            'canReturn' => $availableToReturn > 0
        )
    );
    
    echo json_encode($response);
    exit;
}

// ==========================
// Traitement du formulaire de retour avec VALIDATION SÉCURISÉE
// ==========================
if (isset($_POST['submit'])) {
    // Nettoyage et validation des entrées
    $billingNumber = mysqli_real_escape_string($con, trim($_POST['billingnumber']));
    $productID     = intval($_POST['productid']);
    $quantity      = intval($_POST['quantity']);
    $returnPrice   = floatval($_POST['price']);
    $returnDate    = mysqli_real_escape_string($con, $_POST['returndate']);
    $reason        = mysqli_real_escape_string($con, trim($_POST['reason']));

    // Tableau pour collecter les erreurs
    $errors = [];

    // Validation de base
    if (empty($billingNumber)) {
        $errors[] = "Le numéro de facture est requis.";
    }
    if ($productID <= 0) {
        $errors[] = "Veuillez sélectionner un produit valide.";
    }
    if ($quantity <= 0) {
        $errors[] = "La quantité doit être supérieure à zéro.";
    }
    if ($returnPrice < 0) {
        $errors[] = "Le prix de retour ne peut pas être négatif.";
    }
    if (empty($returnDate)) {
        $errors[] = "La date de retour est requise.";
    }

    // Validation avancée si pas d'erreurs de base
    if (empty($errors)) {
        try {
            // Vérifier l'existence de la facture
            $checkInvoice = mysqli_query($con, "SELECT ID FROM tblcustomer WHERE BillingNumber = '$billingNumber'");
            
            if (mysqli_num_rows($checkInvoice) == 0) {
                $errors[] = "Numéro de facture invalide. Cette facture n'existe pas.";
            }

            // Déterminer quelle table utiliser
            if (empty($errors)) {
                $checkCreditCart = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcreditcart WHERE BillingId='$billingNumber'");
                $creditItems = mysqli_fetch_assoc($checkCreditCart)['count'];
                $useTable = ($creditItems > 0) ? 'tblcreditcart' : 'tblcart';
                
                // Récupérer les détails de la vente originale
                $saleQuery = mysqli_query($con, "
                    SELECT ProductQty, COALESCE(Price, 0) as Price 
                    FROM $useTable 
                    WHERE BillingId = '$billingNumber' AND ProductId = $productID
                ");
                
                if (mysqli_num_rows($saleQuery) == 0) {
                    $errors[] = "Ce produit n'a pas été vendu dans cette facture.";
                } else {
                    $saleData = mysqli_fetch_assoc($saleQuery);
                    $originalQty = $saleData['ProductQty'];
                    $originalPrice = $saleData['Price'];
                    
                    // Si le prix n'est pas dans la table cart, récupérer depuis tblproducts
                    if ($originalPrice == 0) {
                        $priceQuery = mysqli_query($con, "SELECT Price FROM tblproducts WHERE ID = $productID");
                        if ($priceRow = mysqli_fetch_assoc($priceQuery)) {
                            $originalPrice = $priceRow['Price'];
                        }
                    }
                    
                    // Vérifier les quantités déjà retournées
                    $returnQuery = mysqli_query($con, "
                        SELECT COALESCE(SUM(Quantity), 0) as TotalReturned 
                        FROM tblreturns 
                        WHERE BillingNumber = '$billingNumber' AND ProductID = $productID
                    ");
                    $returnData = mysqli_fetch_assoc($returnQuery);
                    $alreadyReturned = $returnData['TotalReturned'];
                    
                    $availableToReturn = $originalQty - $alreadyReturned;
                    
                    // Validation des quantités
                    if ($quantity > $availableToReturn) {
                        $errors[] = "Quantité invalide. Maximum retournable: $availableToReturn";
                    }
                    
                    // Validation du prix
                    if ($returnPrice > $originalPrice) {
                        $errors[] = "Le prix de retour ne peut pas dépasser le prix de vente original.";
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = "Erreur de validation: " . $e->getMessage();
        }
    }

    // Traitement si aucune erreur
    if (empty($errors)) {
        try {
            // Démarrer une transaction
            mysqli_autocommit($con, FALSE);
            
            // Insérer le retour
            $insertQuery = "
                INSERT INTO tblreturns(BillingNumber, ReturnDate, ProductID, Quantity, Reason, ReturnPrice, CreatedAt) 
                VALUES('$billingNumber', '$returnDate', $productID, $quantity, '$reason', $returnPrice, NOW())
            ";
            
            if (!mysqli_query($con, $insertQuery)) {
                throw new Exception("Erreur lors de l'enregistrement du retour.");
            }
            
            // Mettre à jour le stock
            $updateStockQuery = "UPDATE tblproducts SET Stock = Stock + $quantity WHERE ID = $productID";
            
            if (!mysqli_query($con, $updateStockQuery)) {
                throw new Exception("Erreur lors de la mise à jour du stock.");
            }
            
            // Valider la transaction
            mysqli_commit($con);
            
            echo "<script>
                    alert('Retour enregistré avec succès!');
                    window.location.href='return.php';
                  </script>";
            exit;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            mysqli_rollback($con);
            $errors[] = $e->getMessage();
        }
        
        // Réactiver l'autocommit
        mysqli_autocommit($con, TRUE);
    }
    
    // Afficher les erreurs s'il y en a
    if (!empty($errors)) {
        $errorMessage = implode("\\n", $errors);
        echo "<script>alert('Erreurs de validation:\\n$errorMessage');</script>";
    }
}

// ==========================
// Récupération des statistiques du jour
// ==========================
$statsQuery = "SELECT 
                COUNT(*) as total_returns,
                SUM(Quantity) as total_quantity,
                SUM(ReturnPrice * Quantity) as total_value
               FROM tblreturns 
               WHERE DATE(ReturnDate) = CURDATE()";
$statsResult = mysqli_query($con, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Gestion des stocks | Retours de produits</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    
    <!-- Styles personnalisés pour les retours -->
    <style>
        /* ==================== STYLES POUR LA GESTION DES RETOURS ==================== */
        .stats-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #28a745, #ffc107);
        }

        .stats-card h4 {
            color: #495057;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .stats-card p {
            margin: 0;
            font-size: 1.2em;
        }

        #billing-info {
            border-radius: 6px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        #billing-info.alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 4px solid #28a745;
            color: #155724;
        }

        #billing-info.alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        #billing-info.alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid #ffc107;
            color: #856404;
        }

        #billing-info.alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .icon-spinner.icon-spin {
            animation: spin 1s linear infinite;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 0.875em;
            font-weight: bold;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 4px;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-important {
            background-color: #dc3545;
            color: white;
        }

        .badge-info {
            background-color: #17a2b8;
            color: white;
        }

        .control-label span[style*="color:red"] {
            color: #dc3545 !important;
            font-weight: bold;
        }

        .btn {
            transition: all 0.3s ease;
            border-radius: 4px;
            font-weight: 500;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 500px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 6px;
            animation: slideInRight 0.3s ease;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .input-append .btn {
            margin-left: -1px;
            border-radius: 0 4px 4px 0;
        }
        
        .input-append input {
            border-radius: 4px 0 0 4px;
        }

        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 10px;
                padding: 15px;
            }
            
            .toast-notification {
                left: 10px;
                right: 10px;
                min-width: auto;
                max-width: none;
            }
        }
    </style>
</head>

<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
                <i class="icon-home"></i> Accueil
            </a>
            <a href="return.php" class="current">Retours de produits</a>
        </div>
        <h1>Gérer les retours de produits</h1>
    </div>

    <div class="container-fluid">
        <!-- =========== STATISTIQUES DU JOUR =========== -->
        <div class="row-fluid">
            <div class="span4">
                <div class="stats-card">
                    <h4><i class="icon-retweet"></i> Retours aujourd'hui</h4>
                    <p><strong><?php echo $stats['total_returns'] ?: 0; ?></strong> retours</p>
                </div>
            </div>
            <div class="span4">
                <div class="stats-card">
                    <h4><i class="icon-shopping-cart"></i> Quantité totale</h4>
                    <p><strong><?php echo $stats['total_quantity'] ?: 0; ?></strong> articles</p>
                </div>
            </div>
            <div class="span4">
                <div class="stats-card">
                    <h4><i class="icon-money"></i> Valeur totale</h4>
                    <p><strong><?php echo number_format($stats['total_value'] ?: 0, 2); ?> GNF</strong></p>
                </div>
            </div>
        </div>

        <hr>

        <!-- =========== FORMULAIRE DE NOUVEAU RETOUR =========== -->
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-plus"></i></span>
                        <h5>Ajouter un nouveau retour</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <form method="post" class="form-horizontal" id="returnForm">

                            <!-- Numéro de facture avec bouton de vérification -->
                            <div class="control-group">
                                <label class="control-label">Numéro de facture <span style="color:red;">*</span>:</label>
                                <div class="controls">
                                    <div class="input-append">
                                        <input type="text" id="billingnumber" name="billingnumber" 
                                               placeholder="ex. 123456789" required maxlength="50" 
                                               autocomplete="off" style="width: 250px;" />
                                        <button type="button" id="verifyBtn" class="btn btn-primary" onclick="validateBilling()">
                                            <i class="icon-search"></i> Vérifier
                                        </button>
                                    </div>
                                    <div id="billing-info" class="alert" style="display:none; margin-top:10px;"></div>
                                </div>
                            </div>

                            <!-- Date de retour -->
                            <div class="control-group">
                                <label class="control-label">Date de retour <span style="color:red;">*</span>:</label>
                                <div class="controls">
                                    <input type="date" name="returndate" value="<?php echo date('Y-m-d'); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required />
                                    <span class="help-inline">La date ne peut pas être dans le futur</span>
                                </div>
                            </div>

                            <!-- Sélection du produit -->
                            <div class="control-group">
                                <label class="control-label">Produit <span style="color:red;">*</span>:</label>
                                <div class="controls">
                                    <select id="productid" name="productid" required disabled>
                                        <option value="">-- Vérifiez d'abord le numéro de facture --</option>
                                    </select>
                                    <div id="product-details" class="alert alert-info" style="display:none; margin-top:10px;"></div>
                                </div>
                            </div>

                            <!-- Quantité -->
                            <div class="control-group">
                                <label class="control-label">Quantité <span style="color:red;">*</span>:</label>
                                <div class="controls">
                                    <input type="number" id="quantity" name="quantity" min="1" value="1" required />
                                    <span class="help-inline">Maximum basé sur la quantité disponible pour retour</span>
                                </div>
                            </div>

                            <!-- Prix de retour -->
                            <div class="control-group">
                                <label class="control-label">Prix de retour <span style="color:red;">*</span>:</label>
                                <div class="controls">
                                    <input type="number" id="price" name="price" step="0.01" min="0" value="0" required />
                                    <span class="help-inline">Prix maximum basé sur le prix de vente original</span>
                                </div>
                            </div>

                            <!-- Raison -->
                            <div class="control-group">
                                <label class="control-label">Raison :</label>
                                <div class="controls">
                                    <select name="reason">
                                        <option value="">-- Sélectionner une raison --</option>
                                        <option value="Défaut produit">Défaut produit</option>
                                        <option value="Mauvaise taille">Mauvaise taille</option>
                                        <option value="Ne correspond pas à la description">Ne correspond pas à la description</option>
                                        <option value="Changement d'avis">Changement d'avis</option>
                                        <option value="Erreur de commande">Erreur de commande</option>
                                        <option value="Autre">Autre</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="submit" class="btn btn-success" id="submitBtn">
                                    <i class="icon-ok"></i> Enregistrer le retour
                                </button>
                                <button type="reset" class="btn btn-warning" onclick="resetForm()">
                                    <i class="icon-refresh"></i> Réinitialiser
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <!-- =========== LISTE DES RETOURS RÉCENTS =========== -->
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-th"></i></span>
                        <h5>Retours récents</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <table class="table table-bordered table-striped data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Numéro de facture</th>
                                    <th>Date de retour</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire</th>
                                    <th>Total</th>
                                    <th>Raison</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sqlReturns = "
                                    SELECT r.ID as returnID,
                                           r.BillingNumber,
                                           r.ReturnDate,
                                           r.Quantity,
                                           r.Reason,
                                           r.ReturnPrice,
                                           r.CreatedAt,
                                           p.ProductName
                                    FROM tblreturns r
                                    LEFT JOIN tblproducts p ON p.ID = r.ProductID
                                    ORDER BY r.ID DESC
                                    LIMIT 50
                                ";
                                $returnsQuery = mysqli_query($con, $sqlReturns);
                                $cnt = 1;
                                
                                if (mysqli_num_rows($returnsQuery) > 0) {
                                    while ($row = mysqli_fetch_assoc($returnsQuery)) {
                                        $totalPrice = $row['ReturnPrice'] * $row['Quantity'];
                                        ?>
                                        <tr>
                                            <td><?php echo $cnt; ?></td>
                                            <td><?php echo htmlspecialchars($row['BillingNumber']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($row['ReturnDate'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                            <td><?php echo $row['Quantity']; ?></td>
                                            <td><?php echo number_format($row['ReturnPrice'], 2); ?> GNF</td>
                                            <td><?php echo number_format($totalPrice, 2); ?> GNF</td>
                                            <td><?php echo htmlspecialchars($row['Reason'] ?: 'Non spécifiée'); ?></td>
                                            <td>
                                                <a href="view-return.php?id=<?php echo $row['returnID']; ?>" 
                                                   class="btn btn-mini btn-info" title="Voir détails">
                                                    <i class="icon-eye-open"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php
                                        $cnt++;
                                    }
                                } else {
                                    echo '<tr><td colspan="9" class="text-center">Aucun retour trouvé</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>

<!-- Scripts JavaScript -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.tables.js"></script>

<script>
// ========================================
// JAVASCRIPT POUR LA GESTION DES RETOURS
// ========================================

// Variables globales
let currentBillingData = null;
let currentProductData = null;

// ========================================
// Fonction de validation de facture
// ========================================
function validateBilling() {
    console.log('validateBilling called');
    
    const billNum = $('#billingnumber').val().trim();
    
    if (billNum.length === 0) {
        showBillingMessage('Veuillez entrer un numéro de facture', 'warning');
        return;
    }
    
    if (billNum.length < 3) {
        showBillingMessage('Numéro de facture trop court (minimum 3 caractères)', 'warning');
        return;
    }
    
    // Désactiver le bouton pendant la vérification
    const $verifyBtn = $('#verifyBtn');
    $verifyBtn.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Vérification...');
    
    showBillingMessage('<i class="icon-spinner icon-spin"></i> Vérification de la facture en cours...', 'info');
    $('#productid').prop('disabled', true).html('<option value="">-- Validation en cours --</option>');
    
    // Utiliser la même page pour traiter l'AJAX
    $.ajax({
        url: 'return.php',
        type: 'POST',
        data: { 
            ajax_validate_billing: true,
            billingnumber: billNum 
        },
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            console.log('Réponse reçue:', response);
            
            if (response.valid) {
                currentBillingData = response;
                showBillingMessage(response.customerInfo, 'success');
                updateProductDropdown(response.productOptions);
                
                if (response.statistics) {
                    updateDashboardStats(response.statistics);
                }
                
                showToast('success', 'Facture validée avec succès');
            } else {
                showBillingMessage(response.message, 'error');
                resetProductSelection();
                showToast('error', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erreur AJAX:', status, error);
            console.error('Response:', xhr.responseText);
            
            let errorMessage = 'Erreur de connexion';
            if (status === 'timeout') {
                errorMessage = 'Délai d\'attente dépassé. Vérifiez votre connexion.';
            } else if (xhr.status === 500) {
                errorMessage = 'Erreur interne du serveur.';
            } else if (xhr.responseText) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    errorMessage = errorData.message || errorMessage;
                } catch (e) {
                    console.error('Erreur parsing:', e);
                }
            }
            
            showBillingMessage(errorMessage, 'error');
            resetProductSelection();
            showToast('error', errorMessage);
        },
        complete: function() {
            // Réactiver le bouton
            $verifyBtn.prop('disabled', false).html('<i class="icon-search"></i> Vérifier');
        }
    });
}

// ========================================
// Fonction de chargement des détails produit
// ========================================
function loadProductDetails() {
    const productId = $('#productid').val();
    const billNum = $('#billingnumber').val().trim();
    
    if (!productId || !billNum) {
        resetProductDetails();
        return;
    }
    
    $('#product-details').html('<i class="icon-spinner icon-spin"></i> Chargement des détails...')
                         .removeClass('alert-success alert-error')
                         .addClass('alert-info')
                         .show();
    
    $.ajax({
        url: 'return.php',
        type: 'POST',
        data: {
            ajax_get_product_details: true,
            productid: productId,
            billingnumber: billNum
        },
        dataType: 'json',
        timeout: 15000,
        success: function(response) {
            console.log('Détails produit reçus:', response);
            
            if (response.success) {
                currentProductData = response.data;
                $('#product-details').html(response.details)
                                     .removeClass('alert-error alert-info')
                                     .addClass('alert-success');
                
                updateFormConstraints(response.data);
                toggleSubmitButton(response.data.canReturn);
            } else {
                $('#product-details').html('<strong>Erreur:</strong> ' + response.message)
                                     .removeClass('alert-success alert-info')
                                     .addClass('alert-error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Erreur AJAX détails:', status, error);
            showError('Impossible de charger les détails du produit.');
        }
    });
}

// ========================================
// Fonctions utilitaires
// ========================================
function updateProductDropdown(productOptions) {
    console.log('Mise à jour du dropdown produit');
    const $productSelect = $('#productid');
    $productSelect.html(productOptions).prop('disabled', false);
    
    const returnableCount = $productSelect.find('option:not([disabled])').length - 1;
    if (returnableCount === 0) {
        $productSelect.prop('disabled', true);
        showToast('warning', 'Aucun produit retournable trouvé dans cette facture');
    } else {
        showToast('success', `${returnableCount} produit(s) retournable(s) trouvé(s)`);
    }
    
    resetProductDetails();
}

function updateFormConstraints(productData) {
    const $quantity = $('#quantity');
    const $price = $('#price');
    
    $quantity.attr('max', productData.maxReturn).val(Math.min(1, productData.maxReturn));
    $price.attr('max', productData.originalPrice).val(productData.originalPrice);
    
    if (productData.maxReturn <= 0) {
        $quantity.prop('disabled', true);
        $price.prop('disabled', true);
    } else {
        $quantity.prop('disabled', false);
        $price.prop('disabled', false);
    }
    
    updateHelpText(productData);
}

function updateHelpText(productData) {
    const quantityHelp = `Maximum retournable: ${productData.maxReturn} sur ${productData.originalQty} vendu(s)`;
    const priceHelp = `Prix maximum: ${productData.originalPrice} GNF (prix de vente original)`;
    
    $('#quantity').siblings('.help-inline').text(quantityHelp);
    $('#price').siblings('.help-inline').text(priceHelp);
}

function toggleSubmitButton(canReturn) {
    const $submitBtn = $('#submitBtn');
    
    if (canReturn) {
        $submitBtn.prop('disabled', false)
                  .removeClass('btn-warning')
                  .addClass('btn-success')
                  .html('<i class="icon-ok"></i> Enregistrer le retour');
    } else {
        $submitBtn.prop('disabled', true)
                  .removeClass('btn-success')
                  .addClass('btn-warning')
                  .html('<i class="icon-ban-circle"></i> Retour impossible');
    }
}

function showBillingMessage(message, type) {
    const $billingInfo = $('#billing-info');
    $billingInfo.removeClass('alert-success alert-error alert-warning alert-info');
    
    switch (type) {
        case 'success': $billingInfo.addClass('alert-success'); break;
        case 'error': $billingInfo.addClass('alert-error'); break;
        case 'warning': $billingInfo.addClass('alert-warning'); break;
        default: $billingInfo.addClass('alert-info'); break;
    }
    
    $billingInfo.html(message).show();
}

function showError(message) {
    $('#product-details').html('<strong>Erreur:</strong> ' + message)
                         .removeClass('alert-success alert-info')
                         .addClass('alert-error')
                         .show();
}

function showToast(type, message, duration = 4000) {
    const toastClass = {
        'success': 'alert-success',
        'error': 'alert-error',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    const $toast = $(`
        <div class="alert ${toastClass} toast-notification">
            <button type="button" class="close" onclick="$(this).parent().remove()">&times;</button>
            ${message}
        </div>
    `);
    
    $('body').append($toast);
    setTimeout(() => $toast.fadeOut(() => $toast.remove()), duration);
}

function resetBillingValidation() {
    currentBillingData = null;
    $('#billing-info').hide();
    resetProductSelection();
}

function resetProductSelection() {
    $('#productid').prop('disabled', true)
                   .html('<option value="">-- Vérifiez d\'abord le numéro de facture --</option>');
    resetProductDetails();
}

function resetProductDetails() {
    currentProductData = null;
    $('#product-details').hide();
    $('#quantity').val(1).removeAttr('max').prop('disabled', false);
    $('#price').val(0).removeAttr('max').prop('disabled', false);
    toggleSubmitButton(true);
}

function resetForm() {
    resetBillingValidation();
    $('#billingnumber').val('').focus();
    $('#returnForm')[0].reset();
}

function updateDashboardStats(stats) {
    if (!$('#billing-stats').length) {
        const statsHtml = `
            <div id="billing-stats" class="alert alert-info" style="margin-top: 10px;">
                <strong>📊 Statistiques de cette facture:</strong><br>
                Client: ${stats.customerName} • 
                ${stats.totalProducts} produit(s) • 
                ${stats.returnableProducts} retournable(s) • 
                ${stats.totalAmount.toLocaleString()} GNF
            </div>
        `;
        $('#billing-info').after(statsHtml);
    }
}

function validateQuantity() {
    const $quantity = $('#quantity');
    const quantity = parseInt($quantity.val());
    const maxReturn = currentProductData ? currentProductData.maxReturn : 0;
    
    if (quantity > maxReturn) {
        $quantity.val(maxReturn);
        showToast('warning', `Quantité ajustée au maximum retournable: ${maxReturn}`);
    }
    
    if (quantity <= 0) {
        $quantity.val(1);
    }
}

function validatePrice() {
    const $price = $('#price');
    const price = parseFloat($price.val());
    const maxPrice = currentProductData ? currentProductData.originalPrice : 0;
    
    if (price > maxPrice) {
        $price.val(maxPrice);
        showToast('warning', `Prix ajusté au maximum autorisé: ${maxPrice} GNF`);
    }
    
    if (price < 0) {
        $price.val(0);
    }
}

function validateCompleteForm() {
    const errors = [];
    
    if (!$('#billingnumber').val().trim()) {
        errors.push('Le numéro de facture est requis');
    }
    
    if (!$('#productid').val()) {
        errors.push('Veuillez sélectionner un produit');
    }
    
    if (currentProductData) {
        const quantity = parseInt($('#quantity').val());
        const price = parseFloat($('#price').val());
        
        if (quantity <= 0 || quantity > currentProductData.maxReturn) {
            errors.push(`Quantité invalide (max: ${currentProductData.maxReturn})`);
        }
        
        if (price < 0 || price > currentProductData.originalPrice) {
            errors.push(`Prix invalide (max: ${currentProductData.originalPrice} GNF)`);
        }
        
        if (!currentProductData.canReturn) {
            errors.push('Aucun retour possible pour ce produit');
        }
    }
    
    if (errors.length > 0) {
        alert('Erreurs de validation:\n• ' + errors.join('\n• '));
        return false;
    }
    
    return true;
}

// ========================================
// Initialisation
// ========================================
$(document).ready(function() {
    console.log('Document ready - Système de retour initialisé');
    
    // Vérifier que jQuery est chargé
    if (typeof $ === 'undefined') {
        alert('jQuery n\'est pas chargé!');
        return;
    }
    
    // Validation sur pression de la touche Enter
    $('#billingnumber').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            validateBilling();
        }
    });
    
    // Événements pour les champs du formulaire
    $('#quantity').on('change keyup', validateQuantity);
    $('#price').on('change keyup', validatePrice);
    $('#productid').on('change', loadProductDetails);
    
    // Validation avant soumission
    $('#returnForm').on('submit', function(e) {
        if (!validateCompleteForm()) {
            e.preventDefault();
            return false;
        }
    });
    
    // Focus initial
    $('#billingnumber').focus();
});
</script>

</body>
</html>