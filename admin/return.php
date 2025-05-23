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
// Traitement du formulaire de retour avec VALIDATION SÉCURISÉE
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

            // Déterminer quelle table utiliser - MÊME LOGIQUE QUE invoice-search.php
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
    <title>Gestion des stocks | Retours de Article</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    
    <!-- jQuery et plugins -->
    <script src="js/jquery.min.js"></script>
    
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
         .input-group {
            display: flex;
            align-items: stretch;
        }
        
        .input-group .form-control {
            flex: 1;
            border-radius: 4px 0 0 4px;
        }
        
        .input-group-btn {
            display: flex;
        }
        
        .input-group-btn .btn {
            border-radius: 0 4px 4px 0;
            border-left: none;
        }
        
        #invoiceVerificationResult {
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert h4 {
            margin-bottom: 10px;
        }
        
        .label {
            padding: 2px 6px;
            border-radius: 3px;
            color: white;
            font-size: 11px;
        }
        
        .label-info {
            background-color: #3a87ad;
        }
        
        .verification-details {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
            margin-top: 10px;
        }
        
        .verification-details h5 {
            color: #2c5aa0;
            margin-top: 0;
        }
        
        .return-history {
            margin-top: 15px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 3px;
            border: 1px solid #ffeeba;
        }
        
        .disabled-section {
            opacity: 0.5;
            pointer-events: none;
        }
    </style>
</head>
<body>

<?php
session_start();
include('includes/dbconnection.php');

// Vérification de session (votre code existant)
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
} else {
?>

<div class="wrapper">
    <!-- Votre navigation existante -->
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span12">
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5>Gestion des Retours Produits</h5>
                        </div>
                        
                        <div class="widget-content">
                            <form method="post" id="returnForm">
                                
                                <!-- SECTION 1: VÉRIFICATION DE FACTURE -->
                                <fieldset>
                                    <legend>1. Vérification de la Facture</legend>
                                    
                                    <div class="control-group">
                                        <label class="control-label" for="billingnumber">Numéro de Facture <span style="color:red;">*</span></label>
                                        <div class="controls">
                                            <div class="input-group">
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="billingnumber" 
                                                       name="billingnumber" 
                                                       placeholder="Entrez le numéro de facture (ex: 123456789)"
                                                       required
                                                       autocomplete="off">
                                                <div class="input-group-btn">
                                                    <button type="button" 
                                                            class="btn btn-info" 
                                                            id="verifyInvoiceBtn" 
                                                            onclick="verifyInvoice()">
                                                        <i class="icon-search"></i> Vérifier
                                                    </button>
                                                </div>
                                            </div>
                                            <span class="help-block">Saisissez le numéro de facture pour vérifier son existence</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Zone d'affichage du résultat -->
                                    <div id="invoiceVerificationResult"></div>
                                    
                                </fieldset>
                                
                                <!-- SECTION 2: SÉLECTION DE PRODUIT (Désactivée par défaut) -->
                                <fieldset id="productSelection" class="disabled-section">
                                    <legend>2. Sélection du Produit à Retourner</legend>
                                    
                                    <div class="control-group">
                                        <label class="control-label" for="productid">Produit <span style="color:red;">*</span></label>
                                        <div class="controls">
                                            <select class="form-control" id="productid" name="productid" disabled onchange="getProductDetails()">
                                                <option value="">-- Sélectionnez un produit --</option>
                                            </select>
                                            <span class="help-block">Sélectionnez le produit à retourner</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Zone d'affichage des détails du produit -->
                                    <div id="productDetails"></div>
                                    
                                </fieldset>
                                
                                <!-- SECTION 3: DÉTAILS DU RETOUR (Désactivée par défaut) -->
                                <fieldset id="returnDetails" class="disabled-section">
                                    <legend>3. Détails du Retour</legend>
                                    
                                    <div class="control-group">
                                        <label class="control-label" for="returnqty">Quantité à Retourner <span style="color:red;">*</span></label>
                                        <div class="controls">
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="returnqty" 
                                                   name="returnqty" 
                                                   min="1" 
                                                   max="1"
                                                   disabled
                                                   placeholder="Quantité">
                                            <span class="help-block" id="qtyHelp">Quantité maximale disponible: 0</span>
                                        </div>
                                    </div>
                                    
                                    <div class="control-group">
                                        <label class="control-label" for="returnreason">Raison du Retour <span style="color:red;">*</span></label>
                                        <div class="controls">
                                            <select class="form-control" id="returnreason" name="returnreason" disabled>
                                                <option value="">-- Sélectionnez une raison --</option>
                                                <option value="Produit défectueux">Produit défectueux</option>
                                                <option value="Erreur de commande">Erreur de commande</option>
                                                <option value="Client insatisfait">Client insatisfait</option>
                                                <option value="Produit endommagé">Produit endommagé</option>
                                                <option value="Autre">Autre</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="control-group">
                                        <label class="control-label" for="returncomments">Commentaires</label>
                                        <div class="controls">
                                            <textarea class="form-control" 
                                                      id="returncomments" 
                                                      name="returncomments" 
                                                      rows="3"
                                                      disabled
                                                      placeholder="Commentaires supplémentaires (optionnel)"></textarea>
                                        </div>
                                    </div>
                                    
                                </fieldset>
                                
                                <!-- BOUTONS D'ACTION -->
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success" id="submitReturn" disabled>
                                        <i class="icon-ok"></i> Enregistrer le Retour
                                    </button>
                                    <button type="reset" class="btn" onclick="resetForm()">
                                        <i class="icon-refresh"></i> Réinitialiser
                                    </button>
                                </div>
                                
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts JavaScript -->
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>

<script>
// ============================================
// VARIABLES GLOBALES
// ============================================
let currentInvoiceData = null;
let currentProductData = null;
let invoiceTimer;

// ============================================
// FONCTION DE VÉRIFICATION DE FACTURE
// ============================================
function verifyInvoice() {
    const billingNumber = document.getElementById('billingnumber').value.trim();
    const resultDiv = document.getElementById('invoiceVerificationResult');
    const verifyBtn = document.getElementById('verifyInvoiceBtn');
    
    // Validation du champ
    if (!billingNumber) {
        showMessage(resultDiv, 'warning', 'Veuillez saisir un numéro de facture.');
        return;
    }
    
    // Validation de la longueur
    if (billingNumber.length < 3) {
        showMessage(resultDiv, 'warning', 'Le numéro de facture doit contenir au moins 3 caractères.');
        return;
    }
    
    // Désactiver le bouton pendant la vérification
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> Vérification...';
    
    // Afficher un message de chargement
    showMessage(resultDiv, 'info', 'Vérification en cours...');
    
    // Réinitialiser les données
    currentInvoiceData = null;
    resetProductSelection();
    
    // Requête AJAX
    $.ajax({
        url: 'ajax/verify-invoice.php',
        type: 'POST',
        data: {
            billingnumber: billingNumber
        },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.success) {
                // Facture trouvée
                currentInvoiceData = response.data;
                
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h4><i class="icon-ok"></i> Facture trouvée!</h4>
                        ${response.details}
                    </div>
                `;
                
                // Charger les produits de cette facture
                loadInvoiceProducts(billingNumber);
                
                // Activer la sélection de produits
                enableProductSelection();
                
            } else {
                // Facture non trouvée
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <h4><i class="icon-remove"></i> Facture non trouvée</h4>
                        <p>${response.message}</p>
                        <small class="muted">Vérifiez le numéro de facture et réessayez.</small>
                    </div>
                `;
                
                // Désactiver le reste du formulaire
                disableProductSelection();
            }
        },
        error: function(xhr, status, error) {
            let errorMessage = 'Erreur lors de la vérification. Veuillez réessayer.';
            
            if (status === 'timeout') {
                errorMessage = 'Délai d\'attente dépassé. Vérifiez votre connexion.';
            } else if (xhr.status === 500) {
                errorMessage = 'Erreur du serveur. Contactez l\'administrateur.';
            }
            
            resultDiv.innerHTML = `
                <div class="alert alert-error">
                    <h4><i class="icon-warning-sign"></i> Erreur de connexion</h4>
                    <p>${errorMessage}</p>
                </div>
            `;
            
            console.error('Erreur AJAX:', error);
            disableProductSelection();
        },
        complete: function() {
            // Réactiver le bouton
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="icon-search"></i> Vérifier';
        }
    });
}

// ============================================
// CHARGEMENT DES PRODUITS DE LA FACTURE
// ============================================
function loadInvoiceProducts(billingNumber) {
    const productSelect = document.getElementById('productid');
    
    // Vider la liste
    productSelect.innerHTML = '<option value="">-- Chargement des produits... --</option>';
    
    $.ajax({
        url: 'ajax/get-invoice-products.php',
        type: 'POST',
        data: {
            billingnumber: billingNumber
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.products.length > 0) {
                // Remplir la liste des produits
                let options = '<option value="">-- Sélectionnez un produit --</option>';
                
                response.products.forEach(function(product) {
                    const availableQty = product.originalQty - product.alreadyReturned;
                    const status = availableQty > 0 ? '✅' : '❌';
                    
                    options += `<option value="${product.productId}" 
                                        data-available="${availableQty}"
                                        ${availableQty <= 0 ? 'disabled' : ''}>
                                    ${status} ${product.productName} (${product.companyName}) - 
                                    Vendu: ${product.originalQty}, Disponible: ${availableQty}
                                </option>`;
                });
                
                productSelect.innerHTML = options;
            } else {
                productSelect.innerHTML = '<option value="">-- Aucun produit disponible pour retour --</option>';
            }
        },
        error: function() {
            productSelect.innerHTML = '<option value="">-- Erreur de chargement --</option>';
        }
    });
}

// ============================================
// RÉCUPÉRATION DES DÉTAILS DU PRODUIT
// ============================================
function getProductDetails() {
    const productSelect = document.getElementById('productid');
    const productId = productSelect.value;
    const billingNumber = document.getElementById('billingnumber').value;
    const detailsDiv = document.getElementById('productDetails');
    
    if (!productId || !billingNumber) {
        detailsDiv.innerHTML = '';
        disableReturnDetails();
        return;
    }
    
    // Afficher un message de chargement
    detailsDiv.innerHTML = '<div class="alert alert-info">Chargement des détails du produit...</div>';
    
    $.ajax({
        url: 'ajax/get-product-details.php',
        type: 'POST',
        data: {
            productid: productId,
            billingnumber: billingNumber
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                currentProductData = response.data;
                detailsDiv.innerHTML = response.details;
                
                // Configurer les champs de retour
                setupReturnFields(response.data);
                
                // Activer la section des détails de retour
                enableReturnDetails();
                
            } else {
                detailsDiv.innerHTML = `
                    <div class="alert alert-error">
                        <h4><i class="icon-warning-sign"></i> Erreur</h4>
                        <p>${response.message}</p>
                    </div>
                `;
                disableReturnDetails();
            }
        },
        error: function() {
            detailsDiv.innerHTML = `
                <div class="alert alert-error">
                    <h4><i class="icon-warning-sign"></i> Erreur</h4>
                    <p>Impossible de charger les détails du produit.</p>
                </div>
            `;
            disableReturnDetails();
        }
    });
}

// ============================================
// CONFIGURATION DES CHAMPS DE RETOUR
// ============================================
function setupReturnFields(productData) {
    const qtyInput = document.getElementById('returnqty');
    const qtyHelp = document.getElementById('qtyHelp');
    
    // Configurer la quantité maximale
    qtyInput.max = productData.maxReturn;
    qtyInput.value = '';
    qtyInput.placeholder = `Max: ${productData.maxReturn}`;
    
    // Mettre à jour le texte d'aide
    qtyHelp.textContent = `Quantité maximale disponible: ${productData.maxReturn}`;
    qtyHelp.className = productData.maxReturn > 0 ? 'help-block' : 'help-block text-error';
    
    // Valider en temps réel
    qtyInput.oninput = function() {
        const value = parseInt(this.value);
        const max = parseInt(this.max);
        
        if (value > max) {
            this.value = max;
        }
        
        // Activer/désactiver le bouton de soumission
        updateSubmitButton();
    };
}

// ============================================
// GESTION DES SECTIONS
// ============================================
function enableProductSelection() {
    const section = document.getElementById('productSelection');
    section.classList.remove('disabled-section');
    
    const fields = section.querySelectorAll('input, select, button');
    fields.forEach(field => field.disabled = false);
}

function disableProductSelection() {
    const section = document.getElementById('productSelection');
    section.classList.add('disabled-section');
    
    const fields = section.querySelectorAll('input, select, button');
    fields.forEach(field => field.disabled = true);
    
    // Réinitialiser
    resetProductSelection();
    disableReturnDetails();
}

function enableReturnDetails() {
    const section = document.getElementById('returnDetails');
    section.classList.remove('disabled-section');
    
    const fields = section.querySelectorAll('input, select, textarea');
    fields.forEach(field => field.disabled = false);
    
    updateSubmitButton();
}

function disableReturnDetails() {
    const section = document.getElementById('returnDetails');
    section.classList.add('disabled-section');
    
    const fields = section.querySelectorAll('input, select, textarea');
    fields.forEach(field => field.disabled = true);
    
    document.getElementById('submitReturn').disabled = true;
}

function resetProductSelection() {
    document.getElementById('productid').innerHTML = '<option value="">-- Sélectionnez un produit --</option>';
    document.getElementById('productDetails').innerHTML = '';
    currentProductData = null;
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================
function showMessage(element, type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-error' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    element.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
}

function updateSubmitButton() {
    const billingNumber = document.getElementById('billingnumber').value;
    const productId = document.getElementById('productid').value;
    const returnQty = document.getElementById('returnqty').value;
    const returnReason = document.getElementById('returnreason').value;
    const submitBtn = document.getElementById('submitReturn');
    
    const isValid = billingNumber && productId && returnQty && returnReason && 
                   currentInvoiceData && currentProductData;
    
    submitBtn.disabled = !isValid;
}

function resetForm() {
    // Réinitialiser tous les champs
    document.getElementById('returnForm').reset();
    
    // Réinitialiser les données
    currentInvoiceData = null;
    currentProductData = null;
    
    // Réinitialiser l'affichage
    document.getElementById('invoiceVerificationResult').innerHTML = '';
    document.getElementById('productDetails').innerHTML = '';
    
    // Désactiver les sections
    disableProductSelection();
    disableReturnDetails();
}

// ============================================
// ÉVÉNEMENTS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Vérification automatique lors de la saisie
    document.getElementById('billingnumber').addEventListener('input', function() {
        clearTimeout(invoiceTimer);
        document.getElementById('invoiceVerificationResult').innerHTML = '';
        
        // Désactiver la sélection de produits pendant la saisie
        disableProductSelection();
        
        // Auto-vérification après 2 secondes d'inactivité
        if (this.value.trim().length >= 3) {
            invoiceTimer = setTimeout(() => {
                verifyInvoice();
            }, 2000);
        }
    });
    
    // Validation en temps réel pour les champs de retour
    ['returnqty', 'returnreason'].forEach(fieldId => {
        document.getElementById(fieldId).addEventListener('change', updateSubmitButton);
    });
    
    // Validation du formulaire avant soumission
    document.getElementById('returnForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!currentInvoiceData || !currentProductData) {
            alert('Veuillez d\'abord vérifier la facture et sélectionner un produit.');
            return;
        }
        
        const returnQty = parseInt(document.getElementById('returnqty').value);
        const maxReturn = currentProductData.maxReturn;
        
        if (returnQty > maxReturn) {
            alert(`La quantité ne peut pas dépasser ${maxReturn}.`);
            return;
        }
        
        // Confirmation avant soumission
        const confirmMsg = `Confirmer le retour de ${returnQty} unité(s) de "${currentProductData.productName}" ?`;
        if (confirm(confirmMsg)) {
            // Ici vous pouvez ajouter votre logique de soumission
            // Par exemple, envoyer les données via AJAX
            submitReturn();
        }
    });
});

// ============================================
// SOUMISSION DU RETOUR
// ============================================
function submitReturn() {
    const formData = {
        billingnumber: document.getElementById('billingnumber').value,
        productid: document.getElementById('productid').value,
        returnqty: document.getElementById('returnqty').value,
        returnreason: document.getElementById('returnreason').value,
        returncomments: document.getElementById('returncomments').value
    };
    
    $.ajax({
        url: 'ajax/process-return.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Retour enregistré avec succès!');
                resetForm();
            } else {
                alert('Erreur: ' + response.message);
            }
        },
        error: function() {
            alert('Erreur lors de l\'enregistrement du retour.');
        }
    });
}
</script>

</body>
</html>

<?php } ?>

<?php
// ============================================
// 2. FICHIER ajax/verify-invoice.php
// ============================================
?>

<?php
// File: ajax/verify-invoice.php
session_start();
include('../includes/dbconnection.php');

// Définir le type de contenu JSON
header('Content-Type: application/json; charset=utf-8');

// Fonction pour retourner une réponse JSON et terminer
function verifyInvoiceJsonResponse($data) {
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
if (!isset($_POST['billingnumber'])) {
    jsonResponse([
        'success' => false,
        'message' => 'Numéro de facture manquant.'
    ]);
}

// Validation et nettoyage des entrées
$billingNumber = trim($_POST['billingnumber']);

if (empty($billingNumber)) {
    jsonResponse([
        'success' => false,
        'message' => 'Numéro de facture invalide.'
    ]);
}

// Protection contre l'injection SQL
$billingNumber = mysqli_real_escape_string($con, $billingNumber);

try {
    // ========================================
    // 1. Vérifier l'existence dans les deux tables
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
    
    $totalItems = $creditItems + $regularItems;
    
    if ($totalItems == 0) {
        jsonResponse([
            'success' => false,
            'message' => "Aucune facture trouvée avec le numéro: $billingNumber"
        ]);
    }
    
    // ========================================
    // 2. Récupérer les détails de la facture
    // ========================================
    $useTable = ($creditItems > 0) ? 'tblcreditcart' : 'tblcart';
    $saleType = ($creditItems > 0) ? 'Vente à Terme' : 'Vente Cash';
    
    // Requête pour obtenir les détails du client et de la facture
    $stmt = $con->prepare("
        SELECT DISTINCT
            cust.CustomerName,
            cust.CustomerContactNo,
            cust.BillingDate,
            cust.ModeofPayment,
            cust.Dues,
            cust.Paid,
            COUNT(cart.ProductId) as TotalProducts,
            SUM(cart.ProductQty) as TotalQuantity,
            SUM(cart.ProductQty * COALESCE(cart.Price, p.Price)) as TotalAmount
        FROM {$useTable} cart
        INNER JOIN tblcustomer cust ON cust.BillingNumber = cart.BillingId
        LEFT JOIN tblproducts p ON p.ID = cart.ProductId
        WHERE cart.BillingId = ?
        GROUP BY cust.BillingNumber
    ");
    
    $stmt->bind_param("s", $billingNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $stmt->close();
        jsonResponse([
            'success' => false,
            'message' => "Détails de la facture non trouvés."
        ]);
    }
    
    $invoiceData = $result->fetch_assoc();
    $stmt->close();
    
    // ========================================
    // 3. Vérifier les retours déjà effectués
    // ========================================
    $stmt2 = $con->prepare("
        SELECT 
            COUNT(DISTINCT ProductID) as ProductsReturned,
            SUM(Quantity) as TotalReturned,
            MAX(ReturnDate) as LastReturnDate
        FROM tblreturns 
        WHERE BillingNumber = ?
    ");
    
    $stmt2->bind_param("s", $billingNumber);
    $stmt2->execute();
    $returnResult = $stmt2->get_result();
    $returnData = $returnResult->fetch_assoc();
    $stmt2->close();
    
    // ========================================
    // 4. Construire l'affichage des détails
    // ========================================
    $isCredit = ($invoiceData['Dues'] > 0 || $invoiceData['ModeofPayment'] == 'credit');
    $totalReturned = intval($returnData['TotalReturned']);
    $hasReturns = $totalReturned > 0;
    
    $details = "
        <div class='verification-details'>
            <div class='row-fluid'>
                <div class='span6'>
                    <h5>📄 Facture: {$billingNumber}</h5>
                    <strong>👤 Client:</strong> " . htmlspecialchars($invoiceData['CustomerName']) . "<br>
                    <strong>📞 Téléphone:</strong> " . htmlspecialchars($invoiceData['CustomerContactNo'] ?: 'Non renseigné') . "<br>
                    <strong>📅 Date:</strong> " . date('d/m/Y', strtotime($invoiceData['BillingDate'])) . "<br>
                    <strong>💳 Type:</strong> <span class='label label-info'>{$saleType}</span>
                </div>
                <div class='span6'>
                    <strong>📦 Produits:</strong> " . intval($invoiceData['TotalProducts']) . "<br>
                    <strong>📊 Quantité totale:</strong> " . intval($invoiceData['TotalQuantity']) . "<br>
                    <strong>💰 Montant total:</strong> " . number_format($invoiceData['TotalAmount'], 2) . " GNF<br>";
    
    if ($isCredit) {
        $details .= "<strong>💳 Payé:</strong> " . number_format($invoiceData['Paid'], 2) . " GNF<br>";
        $details .= "<strong>💸 Reste dû:</strong> " . number_format($invoiceData['Dues'], 2) . " GNF";
    }
    
    $details .= "
                </div>
            </div>";
    
    // Informations sur les retours
    if ($hasReturns) {
        $details .= "
            <div class='return-history'>
                <strong>↩️ Retours effectués:</strong><br>
                <small>
                    • {$returnData['ProductsReturned']} produit(s) retourné(s)<br>
                    • {$totalReturned} unité(s) au total<br>
                    • Dernier retour: " . date('d/m/Y', strtotime($returnData['LastReturnDate'])) . "
                </small>
            </div>";
    }
    
    $details .= "</div>";
    
    // ========================================
    // 5. Retourner la réponse JSON
    // ========================================
    jsonResponse([
        'success' => true,
        'message' => 'Facture trouvée avec succès.',
        'details' => $details,
        'data' => [
            'billingNumber' => $billingNumber,
            'customerName' => $invoiceData['CustomerName'],
            'saleDate' => $invoiceData['BillingDate'],
            'totalProducts' => intval($invoiceData['TotalProducts']),
            'totalQuantity' => intval($invoiceData['TotalQuantity']),
            'totalAmount' => floatval($invoiceData['TotalAmount']),
            'saleType' => $saleType,
            'useTable' => $useTable,
            'isCredit' => $isCredit,
            'hasReturns' => $hasReturns,
            'totalReturned' => $totalReturned,
            'canReturn' => true
        ]
    ]);
    
} catch (Exception $e) {
    // Log l'erreur
    error_log("Erreur dans verify-invoice.php: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'message' => 'Erreur interne du serveur. Veuillez réessayer.',
        'debug' => $e->getMessage()
    ]);
}

// Fermer la connexion
if (isset($con) && $con) {
    mysqli_close($con);
}
?>

<?php
// ============================================
// 3. FICHIER ajax/get-invoice-products.php
// ============================================
?>

<?php
// File: ajax/get-invoice-products.php
session_start();
include('../includes/dbconnection.php');

header('Content-Type: application/json; charset=utf-8');

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
    jsonResponse(['success' => false, 'message' => 'Session expirée']);
}

if (!isset($_POST['billingnumber'])) {
    jsonResponse(['success' => false, 'message' => 'Numéro de facture manquant']);
}

$billingNumber = trim($_POST['billingnumber']);
$billingNumber = mysqli_real_escape_string($con, $billingNumber);

try {
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
    
    // Récupérer les produits de la facture
    $stmt = $con->prepare("
        SELECT 
            cart.ProductId as productId,
            p.ProductName as productName,
            p.CompanyName as companyName,
            p.ModelNumber as modelNumber,
            cart.ProductQty as originalQty,
            COALESCE(
                (SELECT SUM(Quantity) FROM tblreturns WHERE BillingNumber = ? AND ProductID = cart.ProductId), 
                0
            ) as alreadyReturned
        FROM {$useTable} cart
        INNER JOIN tblproducts p ON p.ID = cart.ProductId
        WHERE cart.BillingId = ?
        ORDER BY p.ProductName
    ");
    
    $stmt->bind_param("ss", $billingNumber, $billingNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'productId' => $row['productId'],
            'productName' => $row['productName'],
            'companyName' => $row['companyName'] ?: 'Non spécifiée',
            'modelNumber' => $row['modelNumber'] ?: '',
            'originalQty' => intval($row['originalQty']),
            'alreadyReturned' => intval($row['alreadyReturned'])
        ];
    }
    
    $stmt->close();
    
    jsonResponse([
        'success' => true,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    error_log("Erreur dans get-invoice-products.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Erreur lors du chargement des produits'
    ]);
}

if (isset($con) && $con) {
    mysqli_close($con);
}
?>

<?php
// ============================================
// 4. FICHIER ajax/process-return.php (OPTIONNEL)
// ============================================
?>

<?php
// File: ajax/process-return.php
session_start();
include('../includes/dbconnection.php');

header('Content-Type: application/json; charset=utf-8');

function jsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
    jsonResponse(['success' => false, 'message' => 'Session expirée']);
}

// Validation des données
$requiredFields = ['billingnumber', 'productid', 'returnqty', 'returnreason'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        jsonResponse(['success' => false, 'message' => "Champ requis manquant: $field"]);
    }
}

$billingNumber = trim($_POST['billingnumber']);
$productId = intval($_POST['productid']);
$returnQty = intval($_POST['returnqty']);
$returnReason = trim($_POST['returnreason']);
$returnComments = trim($_POST['returncomments']) ?: null;
$returnDate = date('Y-m-d H:i:s');
$adminId = $_SESSION['imsaid'];

try {
    // Vérifier la disponibilité pour retour
    // ... (logique de validation similaire à get-product-details.php)
    
    // Insérer le retour
    $stmt = $con->prepare("
        INSERT INTO tblreturns (BillingNumber, ProductID, Quantity, Reason, Comments, ReturnDate, AdminID) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("siisssi", $billingNumber, $productId, $returnQty, $returnReason, $returnComments, $returnDate, $adminId);
    
    if ($stmt->execute()) {
        // Mettre à jour le stock du produit
        $updateStmt = $con->prepare("UPDATE tblproducts SET Stock = Stock + ? WHERE ID = ?");
        $updateStmt->bind_param("ii", $returnQty, $productId);
        $updateStmt->execute();
        $updateStmt->close();
        
        jsonResponse([
            'success' => true,
            'message' => 'Retour enregistré avec succès',
            'returnId' => $stmt->insert_id
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Erreur dans process-return.php: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Erreur interne du serveur'
    ]);
}

if (isset($con) && $con) {
    mysqli_close($con);
}
?>