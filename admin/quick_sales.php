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
        .quick-sale-form {
            background: #f8f9fa;
            border: 2px solid #27a9e3;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .quick-sale-item {
            border-left: 4px solid #5cb85c;
            padding-left: 10px;
            margin-bottom: 5px;
            background-color: #f0fff0;
        }
        
        .price-highlight {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
            padding: 2px 5px;
            font-weight: bold;
        }
        
        .user-cart-indicator {
            background-color: #f8f8f8;
            border-left: 4px solid #27a9e3;
            padding: 10px;
            margin-bottom: 15px;
        }
        .user-cart-indicator i {
            margin-right: 5px;
            color: #27a9e3;
        }
        
        .form-row {
            display: flex;
            gap: 10px;
            align-items: end;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 150px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-quick-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            min-width: 120px;
        }
        
        .actions-panel {
            background: #f1f1f1;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .btn-action {
            margin: 0 5px;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-finalize {
            background: #5cb85c;
            color: white;
        }
        
        .btn-clear {
            background: #d9534f;
            color: white;
        }
        
        .stock-info {
            font-size: 11px;
            color: #666;
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
                <span style="margin-left: 20px; color: #27a9e3;">
                    <i class="icon-info-sign"></i> 
                    Version simplifiée - Fonctionne avec votre base existante
                </span>
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