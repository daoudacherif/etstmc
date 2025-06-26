<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('includes/dbconnection.php');

// Check if user is logged in
if (!isset($_SESSION['imsaid']) || empty($_SESSION['imsaid'])) {
    header("Location: login.php");
    exit;
}

$currentAdminID = $_SESSION['imsaid'];

// Get admin name
$adminQuery = mysqli_query($con, "SELECT AdminName FROM tbladmin WHERE ID = '$currentAdminID'");
$adminData = mysqli_fetch_assoc($adminQuery);
$currentAdminName = $adminData['AdminName'];

// Traitement de l'ajout d'une vente rapide (VERSION SIMPLIFIÉE)
if (isset($_POST['addQuickSale'])) {
    $productId = intval($_POST['productid']);
    $quantity = max(1, intval($_POST['quantity']));
    $price = max(0, floatval($_POST['price']));
    
    // Vérifier le stock disponible
    $stockRes = mysqli_query($con, "
        SELECT Stock, ProductName 
        FROM tblproducts 
        WHERE ID = '$productId' 
        LIMIT 1
    ");
    
    if (!$stockRes || mysqli_num_rows($stockRes) === 0) {
        echo "<script>alert('Produit introuvable.'); window.location.href='quick_sales.php';</script>";
        exit;
    }
    
    $row = mysqli_fetch_assoc($stockRes);
    $currentStock = intval($row['Stock']);
    $productName = $row['ProductName'];
    
    if ($currentStock < $quantity) {
        echo "<script>
                alert('Stock insuffisant pour \"" . addslashes($productName) . "\". Stock disponible: " . $currentStock . "');
                window.location.href='quick_sales.php';
              </script>";
        exit;
    }
    
    // AJOUT SIMPLE : Toujours ajouter une nouvelle ligne (même pour le même produit)
    $insertQuery = mysqli_query($con, "
        INSERT INTO tblcart(
            ProductId, 
            ProductQty, 
            Price, 
            IsCheckOut, 
            AdminID
        ) VALUES(
            '$productId', 
            '$quantity', 
            '$price', 
            '0', 
            '$currentAdminID'
        )
    ");
    
    if ($insertQuery) {
        echo "<script>
                alert('Vente rapide ajoutée : " . addslashes($productName) . " (Prix: " . $price . " GNF)');
                window.location.href='quick_sales.php';
              </script>";
    } else {
        echo "<script>alert('Erreur lors de l\\'ajout.'); window.location.href='quick_sales.php';</script>";
    }
    exit;
}

// Suppression d'une vente rapide
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    $deleteQuery = mysqli_query($con, "
        DELETE FROM tblcart 
        WHERE ID = $delid AND IsCheckOut = 0 AND AdminID = '$currentAdminID'
    ");
    if ($deleteQuery) {
        echo "<script>
                alert('Vente rapide supprimée');
                window.location.href='quick_sales.php';
              </script>";
    }
    exit;
}

// Finaliser toutes les ventes rapides (rediriger vers cart.php)
if (isset($_POST['finalizeQuickSales'])) {
    header("Location: cart.php");
    exit;
}

// Vider toutes les ventes rapides
if (isset($_POST['clearQuickSales'])) {
    mysqli_query($con, "
        DELETE FROM tblcart 
        WHERE IsCheckOut = 0 AND AdminID = '$currentAdminID'
    ");
    echo "<script>
            alert('Toutes les ventes rapides ont été supprimées');
            window.location.href='quick_sales.php';
          </script>";
    exit;
}

// Récupérer tous les produits pour la liste déroulante
$productsQuery = mysqli_query($con, "
    SELECT 
        p.ID, 
        p.ProductName, 
        p.Price, 
        p.Stock, 
        p.BrandName,
        p.ModelNumber
    FROM tblproducts p
    WHERE p.Stock > 0
    ORDER BY p.ProductName ASC
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion des stocks | Ventes Rapides</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    <style>
        /* Formulaire de vente rapide - Design amélioré */
        .quick-sale-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #27a9e3;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(39, 169, 227, 0.1);
        }
        
        .quick-sale-form h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #27a9e3;
            font-size: 18px;
            border-bottom: 2px solid #27a9e3;
            padding-bottom: 8px;
        }
        
        .quick-sale-form p {
            margin-bottom: 20px;
            padding: 10px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
        }
        
        /* Ligne de formulaire responsive */
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 120px;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (max-width: 1024px) and (min-width: 769px) {
            .form-row {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
        }
        
        /* Groupes de champs */
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
            font-size: 14px;
        }
        
        .form-group input, 
        .form-group select {
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-group input:focus, 
        .form-group select:focus {
            outline: none;
            border-color: #27a9e3;
            box-shadow: 0 0 0 3px rgba(39, 169, 227, 0.1);
        }
        
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        /* Bouton d'ajout */
        .btn-quick-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-quick-add:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-quick-add:active {
            transform: translateY(0);
        }
        
        /* Informations de stock */
        .stock-info {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #28a745;
        }
        
        /* Indicateur utilisateur */
        .user-cart-indicator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.2);
        }
        
        .user-cart-indicator i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        /* Éléments du tableau */
        .quick-sale-item {
            border-left: 4px solid #5cb85c;
            background-color: #f0fff0 !important;
            transition: all 0.3s ease;
        }
        
        .quick-sale-item:hover {
            background-color: #e8f5e8 !important;
        }
        
        .price-highlight {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 3px 8px;
            font-weight: 600;
            color: #856404;
        }
        
        /* Panel d'actions */
        .actions-panel {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .actions-panel h4 {
            margin-bottom: 15px;
            color: #495057;
        }
        
        .btn-action {
            margin: 0 8px 10px 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .btn-finalize {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-finalize:hover {
            background: linear-gradient(135deg, #218838 0%, #1aa085 100%);
            transform: translateY(-1px);
        }
        
        .btn-clear {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
        }
        
        .btn-clear:hover {
            background: linear-gradient(135deg, #c82333 0%, #dc2f2f 100%);
            transform: translateY(-1px);
        }
        
        /* Responsive pour mobile */
        @media (max-width: 576px) {
            .quick-sale-form {
                padding: 15px;
                margin: 10px;
            }
            
            .form-row {
                gap: 10px;
            }
            
            .btn-action {
                display: block;
                width: 100%;
                margin: 5px 0;
            }
            
            .user-cart-indicator {
                padding: 12px;
                margin: 10px;
            }
            
            .user-cart-indicator span {
                display: block;
                margin-top: 8px;
                font-size: 12px;
            }
        }
        
        /* Animation pour les alertes de stock */
        .stock-warning {
            animation: pulse-warning 2s infinite;
        }
        
        @keyframes pulse-warning {
            0% { background-color: #fff3cd; }
            50% { background-color: #ffeaa7; }
            100% { background-color: #fff3cd; }
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
                <a href="quick_sales.php" class="current">Ventes Rapides</a>
            </div>
            <h1>🚀 Ventes Rapides - Version Simplifiée</h1>
        </div>

        <div class="container-fluid">
            <!-- Indicateur utilisateur -->
            <div class="user-cart-indicator">
                <i class="icon-user"></i> 
                <strong>Ventes gérées par: <?php echo htmlspecialchars($currentAdminName); ?></strong>
                
            </div>

            <!-- Formulaire de vente rapide -->
            <div class="quick-sale-form">
                <h3><i class="icon-plus-sign"></i> Ajouter une vente rapide</h3>
                <p style="color: #666; margin-bottom: 15px;">
                    <i class="icon-lightbulb"></i> 
                    <strong>Astuce :</strong> Chaque ajout crée une nouvelle ligne, même pour le même produit.
                </p>
                
                <form method="post" action="quick_sales.php">
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="productSelect">Produit :</label>
                            <select name="productid" id="productSelect" required onchange="updateProductInfo()">
                                <option value="">-- Sélectionner un produit --</option>
                                <?php
                                while ($product = mysqli_fetch_assoc($productsQuery)) {
                                    $displayName = $product['ProductName'];
                                    if (!empty($product['BrandName'])) {
                                        $displayName .= " - " . $product['BrandName'];
                                    }
                                    if (!empty($product['ModelNumber'])) {
                                        $displayName .= " (" . $product['ModelNumber'] . ")";
                                    }
                                    
                                    echo "<option value='{$product['ID']}' 
                                            data-price='{$product['Price']}' 
                                            data-stock='{$product['Stock']}'>
                                            {$displayName} - Stock: {$product['Stock']}
                                          </option>";
                                }
                                ?>
                            </select>
                            <div id="stockInfo" class="stock-info"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="priceInput">Prix de vente :</label>
                            <input type="number" name="price" id="priceInput" step="0.01" min="0" required />
                        </div>
                        
                        <div class="form-group">
                            <label for="quantityInput">Quantité :</label>
                            <input type="number" name="quantity" id="quantityInput" value="1" min="1" required />
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="addQuickSale" class="btn-quick-add">
                                <i class="icon-plus"></i> Ajouter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Liste des ventes rapides -->
            <div class="widget-box">
                <div class="widget-title">
                    <span class="icon"><i class="icon-shopping-cart"></i></span>
                    <h5>Ventes Rapides Accumulées</h5>
                </div>
                <div class="widget-content nopadding">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Produit</th>
                                <th>Prix Unitaire</th>
                                <th>Quantité</th>
                                <th>Total Ligne</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // REQUÊTE SIMPLIFIÉE sans les colonnes qui n'existent pas
                            $salesQuery = mysqli_query($con, "
                                SELECT 
                                    c.ID,
                                    c.ProductQty,
                                    c.Price,
                                    p.ProductName,
                                    p.Price as BasePrice
                                FROM tblcart c
                                LEFT JOIN tblproducts p ON p.ID = c.ProductId
                                WHERE c.IsCheckOut = 0 AND c.AdminID = '$currentAdminID'
                                ORDER BY c.ID DESC
                            ");
                            
                            $cnt = 1;
                            $grandTotal = 0;
                            $totalItems = 0;
                            
                            if (mysqli_num_rows($salesQuery) > 0) {
                                while ($sale = mysqli_fetch_assoc($salesQuery)) {
                                    $lineTotal = $sale['ProductQty'] * $sale['Price'];
                                    $grandTotal += $lineTotal;
                                    $totalItems += $sale['ProductQty'];
                                    
                                    $priceChanged = ($sale['Price'] != $sale['BasePrice']);
                                    $priceClass = $priceChanged ? 'price-highlight' : '';
                                    ?>
                                    <tr class="quick-sale-item">
                                        <td><?php echo $cnt++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sale['ProductName']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="<?php echo $priceClass; ?>">
                                                <?php echo number_format($sale['Price'], 2); ?> GNF
                                            </span>
                                            <?php if ($priceChanged): ?>
                                                <br><small style="color: #666;">
                                                    (Base: <?php echo number_format($sale['BasePrice'], 2); ?> GNF)
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $sale['ProductQty']; ?></td>
                                        <td><strong><?php echo number_format($lineTotal, 2); ?> GNF</strong></td>
                                        <td>
                                            <a href="quick_sales.php?delid=<?php echo $sale['ID']; ?>" 
                                               onclick="return confirm('Supprimer cette vente ?');" 
                                               class="btn btn-danger btn-small">
                                                <i class="icon-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                <tr style="background: #f0f8ff;">
                                    <td colspan="4" style="text-align: right; font-weight: bold;">
                                        TOTAL GÉNÉRAL:
                                    </td>
                                    <td style="font-weight: bold; font-size: 16px;">
                                        <?php echo number_format($grandTotal, 2); ?> GNF
                                    </td>
                                    <td style="text-align: center;">
                                        <strong><?php echo $totalItems; ?> articles</strong>
                                    </td>
                                </tr>
                                <?php
                            } else {
                                ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #999; padding: 30px;">
                                        Aucune vente rapide en cours.
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions -->
            <?php if (mysqli_num_rows($salesQuery) > 0): ?>
            <div class="actions-panel">
                <h4><i class="icon-cogs"></i> Actions</h4>
                <form method="post" style="display: inline-block; margin-right: 20px;">
                    <button type="submit" name="finalizeQuickSales" class="btn-action btn-finalize">
                        <i class="icon-ok-circle"></i> Finaliser & Créer Facture
                    </button>
                </form>
                
                <form method="post" style="display: inline-block;" 
                      onsubmit="return confirm('Supprimer TOUTES les ventes ?');">
                    <button type="submit" name="clearQuickSales" class="btn-action btn-clear">
                        <i class="icon-remove-circle"></i> Tout Supprimer
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once('includes/footer.php'); ?>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/matrix.js"></script>
    
    <script>
        function updateProductInfo() {
            const select = document.getElementById('productSelect');
            const priceInput = document.getElementById('priceInput');
            const quantityInput = document.getElementById('quantityInput');
            const stockInfo = document.getElementById('stockInfo');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const price = option.getAttribute('data-price');
                const stock = option.getAttribute('data-stock');
                
                priceInput.value = price;
                quantityInput.max = stock;
                
                stockInfo.innerHTML = `
                    <i class="icon-info-sign"></i> 
                    Stock: <strong>${stock}</strong> | 
                    Prix de base: <strong>${parseFloat(price).toFixed(2)} GNF</strong>
                `;
                
                quantityInput.value = 1;
            } else {
                priceInput.value = '';
                quantityInput.max = '';
                stockInfo.innerHTML = '';
            }
        }
    </script>
</body>
</html>