<?php
// ============== PAGE return-simple.php SANS DÉPENDANCES CSS ==============
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Vérification de la session admin
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
    exit;
}

// ==========================
// Traitement du formulaire de retour
// ==========================
if (isset($_POST['submit'])) {
    // Nettoyage et validation des entrées
    $billingNumber = trim($_POST['billingnumber']);
    $productID     = intval($_POST['productid']);
    $quantity      = intval($_POST['quantity']);
    $returnPrice   = floatval($_POST['price']);
    $returnDate    = $_POST['returndate'];
    $reason        = trim($_POST['reason']);

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
            $stmt = $con->prepare("SELECT ID FROM tblcustomer WHERE BillingNumber = ?");
            $stmt->bind_param("s", $billingNumber);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $errors[] = "Numéro de facture invalide. Cette facture n'existe pas.";
            }
            $stmt->close();

            // Déterminer quelle table utiliser
            if (empty($errors)) {
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
                
                // Récupérer les détails de la vente originale selon la table appropriée
                $stmt = $con->prepare("
                    SELECT ProductQty, COALESCE(Price, 0) as Price 
                    FROM {$useTable} 
                    WHERE BillingId = ? AND ProductId = ?
                ");
                $stmt->bind_param("si", $billingNumber, $productID);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    $errors[] = "Ce produit n'a pas été vendu dans cette facture (table vérifiée: {$useTable}).";
                } else {
                    $saleData = $result->fetch_assoc();
                    $originalQty = $saleData['ProductQty'];
                    $originalPrice = $saleData['Price'];
                    
                    // Si le prix n'est pas dans la table cart, récupérer depuis tblproducts
                    if ($originalPrice == 0) {
                        $priceStmt = $con->prepare("SELECT Price FROM tblproducts WHERE ID = ?");
                        $priceStmt->bind_param("i", $productID);
                        $priceStmt->execute();
                        $priceResult = $priceStmt->get_result();
                        if ($priceRow = $priceResult->fetch_assoc()) {
                            $originalPrice = $priceRow['Price'];
                        }
                        $priceStmt->close();
                    }
                    
                    // Vérifier les quantités déjà retournées
                    $stmt2 = $con->prepare("
                        SELECT COALESCE(SUM(Quantity), 0) as TotalReturned 
                        FROM tblreturns 
                        WHERE BillingNumber = ? AND ProductID = ?
                    ");
                    $stmt2->bind_param("si", $billingNumber, $productID);
                    $stmt2->execute();
                    $returnResult = $stmt2->get_result();
                    $returnData = $returnResult->fetch_assoc();
                    $alreadyReturned = $returnData['TotalReturned'];
                    
                    $availableToReturn = $originalQty - $alreadyReturned;
                    
                    // Validation des quantités
                    if ($quantity > $availableToReturn) {
                        $errors[] = "Quantité invalide. Vendu: $originalQty, Déjà retourné: $alreadyReturned, Maximum retournable: $availableToReturn (Table: {$useTable})";
                    }
                    
                    // Validation du prix
                    if ($returnPrice > $originalPrice) {
                        $errors[] = "Le prix de retour ({$returnPrice}€) ne peut pas dépasser le prix de vente original ({$originalPrice}€).";
                    }
                    
                    $stmt2->close();
                }
                $stmt->close();
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
            $stmt = $con->prepare("
                INSERT INTO tblreturns(BillingNumber, ReturnDate, ProductID, Quantity, Reason, ReturnPrice, CreatedAt) 
                VALUES(?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("ssiiss", $billingNumber, $returnDate, $productID, $quantity, $reason, $returnPrice);
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'enregistrement du retour.");
            }
            
            // Mettre à jour le stock
            $stmt2 = $con->prepare("UPDATE tblproducts SET Stock = Stock + ? WHERE ID = ?");
            $stmt2->bind_param("ii", $quantity, $productID);
            
            if (!$stmt2->execute()) {
                throw new Exception("Erreur lors de la mise à jour du stock.");
            }
            
            // Valider la transaction
            mysqli_commit($con);
            
            $stmt->close();
            $stmt2->close();
            
            echo "<script>
                    alert('Retour enregistré avec succès!');
                    window.location.href='return-simple.php';
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
    <title>Gestion des stocks | Retours de Article</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Styles intégrés pour éviter les dépendances externes -->
    <style>
        /* Reset et styles de base */
        * { box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
            line-height: 1.6;
        }
        
        /* Layout principal */
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1, h2, h3 { color: #333; margin-top: 0; }
        
        /* Cartes de statistiques */
        .stats-row { display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap; }
        .stats-card { 
            flex: 1; 
            background: linear-gradient(135deg, #007bff, #0056b3); 
            color: white; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center;
            min-width: 200px;
        }
        .stats-card h4 { margin: 0 0 10px 0; font-size: 1.1em; }
        .stats-card p { margin: 0; font-size: 1.5em; font-weight: bold; }
        
        /* Formulaire */
        .form-section { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold; 
            color: #333; 
        }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 16px;
        }
        .form-group input:focus, .form-group select:focus { 
            border-color: #007bff; 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25); 
        }
        
        .required { color: #dc3545; }
        .help-text { font-size: 0.9em; color: #666; margin-top: 5px; }
        
        /* Boutons */
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        
        /* Alertes */
        .alert { 
            padding: 15px; 
            margin: 15px 0; 
            border-radius: 4px; 
            border: 1px solid transparent; 
        }
        .alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        
        /* Tableaux */
        .table-section { margin-top: 30px; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
            background: white;
        }
        th, td { 
            padding: 12px 8px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background: #f8f9fa; 
            font-weight: bold; 
            color: #333;
        }
        tr:hover { background: #f8f9fa; }
        
        /* Spinner */
        .spinner { 
            display: inline-block; 
            width: 20px; 
            height: 20px; 
            border: 3px solid #f3f3f3; 
            border-top: 3px solid #007bff; 
            border-radius: 50%; 
            animation: spin 1s linear infinite; 
        }
        @keyframes spin { 
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); } 
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-row { flex-direction: column; }
            .container { padding: 10px; }
            table { font-size: 14px; }
            th, td { padding: 8px 4px; }
        }
        
        /* Debug console */
        .debug-console { 
            background: #2d3748; 
            color: #68d391; 
            padding: 15px; 
            border-radius: 4px; 
            font-family: monospace; 
            font-size: 12px; 
            height: 200px; 
            overflow-y: scroll; 
            margin: 15px 0;
        }
    </style>
    
    <!-- jQuery depuis CDN fiable -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
<div class="container">
    <h1>🔄 Gestion des Retours de Articles</h1>
    
    <!-- Statistiques du jour -->
    <div class="stats-row">
        <div class="stats-card">
            <h4>Retours aujourd'hui</h4>
            <p><?php echo $stats['total_returns'] ?: 0; ?></p>
        </div>
        <div class="stats-card">
            <h4>Quantité totale</h4>
            <p><?php echo $stats['total_quantity'] ?: 0; ?></p>
        </div>
        <div class="stats-card">
            <h4>Valeur totale</h4>
            <p><?php echo number_format($stats['total_value'] ?: 0, 2); ?> GNF</p>
        </div>
    </div>
    
    <!-- Formulaire de nouveau retour -->
    <div class="form-section">
        <h2>➕ Ajouter un nouveau retour</h2>
        
        <form method="post" id="returnForm">
            <div class="form-group">
                <label for="billingnumber">Numéro de facture <span class="required">*</span></label>
                <input type="text" id="billingnumber" name="billingnumber" 
                       placeholder="ex. 385973758" required maxlength="50" autocomplete="off">
                <div id="billing-info" class="alert" style="display:none;"></div>
            </div>
            
            <div class="form-group">
                <label for="returndate">Date de retour <span class="required">*</span></label>
                <input type="date" id="returndate" name="returndate" 
                       value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                <div class="help-text">La date ne peut pas être dans le futur</div>
            </div>
            
            <div class="form-group">
                <label for="productid">Produit <span class="required">*</span></label>
                <select id="productid" name="productid" required disabled>
                    <option value="">-- Entrez d'abord le numéro de facture --</option>
                </select>
                <div id="product-details" class="alert" style="display:none;"></div>
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantité <span class="required">*</span></label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                <div class="help-text">Maximum basé sur la quantité disponible pour retour</div>
            </div>
            
            <div class="form-group">
                <label for="price">Prix de retour <span class="required">*</span></label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="0" required>
                <div class="help-text">Prix maximum basé sur le prix de vente original</div>
            </div>
            
            <div class="form-group">
                <label for="reason">Raison</label>
                <select id="reason" name="reason">
                    <option value="">-- Sélectionner une raison --</option>
                    <option value="Défaut produit">Défaut produit</option>
                    <option value="Mauvaise taille">Mauvaise taille</option>
                    <option value="Ne correspond pas à la description">Ne correspond pas à la description</option>
                    <option value="Changement d'avis">Changement d'avis</option>
                    <option value="Erreur de commande">Erreur de commande</option>
                    <option value="Autre">Autre</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" name="submit" class="btn btn-success" id="submitBtn">
                    ✅ Enregistrer le retour
                </button>
                <button type="reset" class="btn btn-warning" onclick="resetForm()">
                    🔄 Réinitialiser
                </button>
            </div>
        </form>
    </div>
    
    <!-- Debug console -->
    <div class="form-section">
        <h3>🐛 Console de Debug</h3>
        <div id="debug-console" class="debug-console"></div>
        <button class="btn btn-primary" onclick="clearDebugConsole()">Effacer Console</button>
        <button class="btn btn-primary" onclick="testSystem()">Test Système</button>
    </div>
    
    <!-- Liste des retours récents -->
    <div class="table-section">
        <h2>📋 Retours récents</h2>
        <table>
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
                    LIMIT 20
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
                        </tr>
                        <?php
                        $cnt++;
                    }
                } else {
                    echo '<tr><td colspan="8" style="text-align: center;">Aucun retour trouvé</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Variables globales
let currentBillingData = null;
let currentProductData = null;
let validationTimeout = null;

// Console de debug
function addDebugLog(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : type === 'warning' ? '⚠️' : '📝';
    const logEntry = `[${timestamp}] ${prefix} ${message}\n`;
    
    $('#debug-console').append(logEntry);
    $('#debug-console').scrollTop($('#debug-console')[0].scrollHeight);
    console.log(logEntry);
}

function clearDebugConsole() {
    $('#debug-console').empty();
}

// ========================================
// Fonctions principales
// ========================================

function validateBilling() {
    const billNum = $('#billingnumber').val().trim();
    
    addDebugLog(`validateBilling appelée avec: ${billNum}`);
    
    if (billNum.length === 0) {
        resetBillingValidation();
        return;
    }
    
    if (billNum.length < 3) {
        showBillingMessage('Numéro de facture trop court (minimum 3 caractères)', 'warning');
        return;
    }
    
    showBillingMessage('<div class="spinner"></div> Vérification de la facture en cours...', 'info');
    $('#productid').prop('disabled', true).html('<option value="">-- Validation en cours --</option>');
    
    addDebugLog('Envoi requête AJAX vers validate-billing-simple.php');
    
    $.ajax({
        url: 'ajax/validate-billing-simple.php',
        type: 'POST',
        data: { billingnumber: billNum },
        timeout: 15000,
        dataType: 'json',
        success: function(response) {
            addDebugLog(`Réponse AJAX reçue: ${JSON.stringify(response)}`, 'success');
            
            if (response.valid) {
                addDebugLog('Validation réussie', 'success');
                currentBillingData = response;
                showBillingMessage(response.customerInfo, 'success');
                updateProductDropdown(response.productOptions);
            } else {
                addDebugLog(`Validation échouée: ${response.message}`, 'error');
                showBillingMessage(response.message, 'error');
                resetProductSelection();
            }
        },
        error: function(xhr, status, error) {
            addDebugLog(`Erreur AJAX: ${status} - ${error}`, 'error');
            
            let errorMessage = 'Erreur de connexion: ' + status;
            if (xhr.status === 404) {
                errorMessage = 'Fichier AJAX non trouvé. Créez ajax/validate-billing-simple.php';
            }
            
            showBillingMessage(errorMessage, 'error');
            resetProductSelection();
        }
    });
}

function loadProductDetails() {
    const productId = $('#productid').val();
    const billNum = $('#billingnumber').val().trim();
    
    addDebugLog(`loadProductDetails appelée avec: produit=${productId}, facture=${billNum}`);
    
    if (!productId || !billNum) {
        addDebugLog('Paramètres manquants pour loadProductDetails', 'warning');
        resetProductDetails();
        return;
    }
    
    $('#product-details').html('<div class="spinner"></div> Chargement des détails...')
                         .removeClass('alert-success alert-error')
                         .addClass('alert-info')
                         .show();
    
    addDebugLog('Envoi requête AJAX vers get-product-details-simple.php');
    
    $.ajax({
        url: 'ajax/get-product-details-simple.php',
        type: 'POST',
        data: {
            productid: productId,
            billingnumber: billNum
        },
        timeout: 15000,
        dataType: 'json',
        success: function(response) {
            addDebugLog(`Détails produit reçus: ${JSON.stringify(response)}`, 'success');
            
            if (response.success) {
                addDebugLog('Détails chargés avec succès', 'success');
                currentProductData = response.data;
                $('#product-details').html(response.details)
                                     .removeClass('alert-error alert-info')
                                     .addClass('alert-success');
                
                updateFormConstraints(response.data);
                toggleSubmitButton(response.data.canReturn);
            } else {
                addDebugLog(`Erreur détails: ${response.message}`, 'error');
                $('#product-details').html('<strong>Erreur:</strong> ' + response.message)
                                     .removeClass('alert-success alert-info')
                                     .addClass('alert-error');
            }
        },
        error: function(xhr, status, error) {
            addDebugLog(`Erreur AJAX détails: ${status} - ${error}`, 'error');
            
            let errorMessage = 'Impossible de charger les détails du produit: ' + status;
            if (xhr.status === 404) {
                errorMessage = 'Fichier AJAX non trouvé. Créez ajax/get-product-details-simple.php';
            }
            
            $('#product-details').html('<strong>Erreur:</strong> ' + errorMessage)
                                 .removeClass('alert-success alert-info')
                                 .addClass('alert-error');
        }
    });
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

function updateProductDropdown(productOptions) {
    addDebugLog('Mise à jour dropdown produits');
    const $productSelect = $('#productid');
    $productSelect.html(productOptions).prop('disabled', false);
    
    const returnableCount = $productSelect.find('option:not([disabled])').length - 1;
    addDebugLog(`Produits retournables: ${returnableCount}`, returnableCount > 0 ? 'success' : 'warning');
    
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
    
    addDebugLog(`Contraintes formulaire mises à jour: max_qty=${productData.maxReturn}, max_price=${productData.originalPrice}`);
}

function toggleSubmitButton(canReturn) {
    const $submitBtn = $('#submitBtn');
    
    if (canReturn) {
        $submitBtn.prop('disabled', false)
                  .removeClass('btn-warning')
                  .addClass('btn-success')
                  .html('✅ Enregistrer le retour');
    } else {
        $submitBtn.prop('disabled', true)
                  .removeClass('btn-success')
                  .addClass('btn-warning')
                  .html('🚫 Retour impossible');
    }
    
    addDebugLog(`Bouton submit ${canReturn ? 'activé' : 'désactivé'}`);
}

function resetBillingValidation() {
    currentBillingData = null;
    $('#billing-info').hide();
    resetProductSelection();
}

function resetProductSelection() {
    $('#productid').prop('disabled', true)
                   .html('<option value="">-- Entrez d\'abord le numéro de facture --</option>');
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
    addDebugLog('Formulaire réinitialisé');
}

function testSystem() {
    addDebugLog('🧪 Test du système démarré');
    
    // Test jQuery
    if (typeof $ !== 'undefined') {
        addDebugLog('✅ jQuery disponible, version: ' + $.fn.jquery, 'success');
    } else {
        addDebugLog('❌ jQuery non disponible', 'error');
        return;
    }
    
    // Test éléments DOM
    const elements = ['#billingnumber', '#productid', '#billing-info', '#product-details'];
    elements.forEach(function(selector) {
        if ($(selector).length > 0) {
            addDebugLog(`✅ Élément trouvé: ${selector}`, 'success');
        } else {
            addDebugLog(`❌ Élément manquant: ${selector}`, 'error');
        }
    });
    
    // Test accès fichiers AJAX
    $.ajax({
        url: 'ajax/validate-billing-simple.php',
        type: 'HEAD',
        success: function() {
            addDebugLog('✅ ajax/validate-billing-simple.php accessible', 'success');
        },
        error: function() {
            addDebugLog('❌ ajax/validate-billing-simple.php NON accessible', 'error');
        }
    });
    
    $.ajax({
        url: 'ajax/get-product-details-simple.php',
        type: 'HEAD',
        success: function() {
            addDebugLog('✅ ajax/get-product-details-simple.php accessible', 'success');
        },
        error: function() {
            addDebugLog('❌ ajax/get-product-details-simple.php NON accessible', 'error');
        }
    });
}

// ========================================
// Initialisation
// ========================================
$(document).ready(function() {
    addDebugLog('🚀 Initialisation du système de retours');
    
    // Test système initial
    testSystem();
    
    // Événements
    $('#billingnumber').on('input', function() {
        const value = $(this).val().trim();
        addDebugLog(`Saisie facture: ${value}`);
        
        if (validationTimeout) {
            clearTimeout(validationTimeout);
        }
        
        if (value.length === 0) {
            resetBillingValidation();
            return;
        }
        
        validationTimeout = setTimeout(() => {
            if (value.length >= 3) {
                addDebugLog('Déclenchement validation automatique');
                validateBilling();
            }
        }, 800);
    });
    
    $('#productid').on('change', function() {
        const selectedValue = $(this).val();
        const selectedText = $(this).find('option:selected').text();
        addDebugLog(`Sélection produit changée: ${selectedValue} - ${selectedText}`);
        
        if (selectedValue) {
            loadProductDetails();
        } else {
            resetProductDetails();
        }
    });
    
    // Focus initial
    $('#billingnumber').focus();
    
    addDebugLog('🎉 Initialisation terminée');
});
</script>

</body>
</html>