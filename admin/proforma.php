<?php
session_start();
// Affiche toutes les erreurs (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('includes/dbconnection.php');

// Check if user is logged in
if (!isset($_SESSION['imsaid']) || empty($_SESSION['imsaid'])) {
    header("Location: login.php");
    exit;
}

// Get the current admin ID from session
$currentAdminID = $_SESSION['imsaid'];

// Get the current admin name
$adminQuery = mysqli_query($con, "SELECT AdminName FROM tbladmin WHERE ID = '$currentAdminID'");
$adminData = mysqli_fetch_assoc($adminQuery);
$currentAdminName = $adminData['AdminName'];

// CLEANUP FUNCTION - Remove any orphaned items on page load
$cleanupQuery = mysqli_query($con, "DELETE FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID' AND (BillingId IS NOT NULL AND BillingId != '' AND BillingId != '0')");

// DEBUG CLEANUP - Force cleanup if requested
if (isset($_GET['force_cleanup']) && $_GET['force_cleanup'] == '1') {
    mysqli_query($con, "DELETE FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
    unset($_SESSION['proforma_discount']);
    unset($_SESSION['proforma_discountType']);
    unset($_SESSION['proforma_discountValue']);
    echo "<script>alert('Panier proforma forcé à vide'); window.location.href='proforma.php';</script>";
    exit;
}

// CLEAR CART FUNCTION
if (isset($_POST['clearProformaCart'])) {
    $deleteQuery = mysqli_query($con, "DELETE FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
    if ($deleteQuery) {
        // Nettoyer aussi les variables de session
        unset($_SESSION['proforma_discount']);
        unset($_SESSION['proforma_discountType']);
        unset($_SESSION['proforma_discountValue']);
        
        echo "<script>
                alert('Panier proforma vidé avec succès');
                window.location.href='proforma.php';
              </script>";
        exit;
    }
}

// Appliquer une remise (pour proforma)
if (isset($_POST['applyDiscount'])) {
    $discountValue = max(0, floatval($_POST['discount']));
    
    // Calculer le grand total avant d'appliquer la remise
    $grandTotal = 0;
    $cartQuery = mysqli_query($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
    while ($row = mysqli_fetch_assoc($cartQuery)) {
        $grandTotal += $row['ProductQty'] * $row['Price'];
    }
    
    // Déterminer si c'est un pourcentage ou une valeur absolue
    $isPercentage = isset($_POST['discountType']) && $_POST['discountType'] === 'percentage';
    
    if ($isPercentage) {
        // Limiter le pourcentage à 100% maximum
        $discountValue = min(100, $discountValue);
        // Calculer la remise en valeur absolue basée sur le pourcentage
        $actualDiscount = ($discountValue / 100) * $grandTotal;
    } else {
        // Remise en valeur absolue (limiter à la valeur du panier)
        $actualDiscount = min($grandTotal, $discountValue);
    }
    
    // Stocker les informations de remise dans la session (pour proforma)
    $_SESSION['proforma_discount'] = $actualDiscount;
    $_SESSION['proforma_discountType'] = $isPercentage ? 'percentage' : 'absolute';
    $_SESSION['proforma_discountValue'] = $discountValue;
    
    header("Location: proforma.php");
    exit;
}

// Récupérer les informations de remise de la session
$discount = $_SESSION['proforma_discount'] ?? 0;
$discountType = $_SESSION['proforma_discountType'] ?? 'absolute';
$discountValue = $_SESSION['proforma_discountValue'] ?? 0;

// Traitement de la suppression d'un élément du panier proforma
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    $deleteQuery = mysqli_query($con, "DELETE FROM tblcart WHERE ID = $delid AND IsCheckOut = 2 AND AdminID = '$currentAdminID'");
    if ($deleteQuery) {
        echo "<script>
                alert('Article retiré du panier proforma');
                window.location.href='proforma.php';
              </script>";
        exit;
    }
}

// GESTION DE L'AJOUT AU PANIER PROFORMA (sans vérification stricte du stock)
if (isset($_POST['addtocart'])) {
    $productId = intval($_POST['productid']);
    $quantity  = max(1, intval($_POST['quantity']));
    $price     = max(0, floatval($_POST['price']));

    // Récupérer les informations du produit
    $productRes = mysqli_query($con, "
        SELECT ProductName, ID
        FROM tblproducts
        WHERE ID = '$productId'
        LIMIT 1
    ");
    
    if (!$productRes || mysqli_num_rows($productRes) === 0) {
        echo "<script>
                alert('Article introuvable.');
                window.location.href='proforma.php';
              </script>";
        exit;
    }
    
    $row = mysqli_fetch_assoc($productRes);
    $productName = $row['ProductName'];

    // Vérifier si l'article existe déjà dans le panier proforma (IsCheckOut=2)
    $checkCart = mysqli_query($con, "
        SELECT ID, ProductQty 
        FROM tblcart 
        WHERE ProductId='$productId' AND IsCheckOut=2 AND AdminID='$currentAdminID'
        LIMIT 1
    ");
    
    if (mysqli_num_rows($checkCart) > 0) {
        $c = mysqli_fetch_assoc($checkCart);
        $newQty = $c['ProductQty'] + $quantity;
        
        // Mettre à jour la quantité (sans vérification de stock pour proforma)
        mysqli_query($con, "
            UPDATE tblcart 
            SET ProductQty='$newQty', Price='$price' 
            WHERE ID='{$c['ID']}'
        ") or die(mysqli_error($con));
    } else {
        // Ajouter nouveau produit au panier proforma (IsCheckOut=2)
        mysqli_query($con, "
            INSERT INTO tblcart(ProductId, ProductQty, Price, IsCheckOut, AdminID) 
            VALUES('$productId', '$quantity', '$price', '2', '$currentAdminID')
        ") or die(mysqli_error($con));
    }

    echo "<script>
            alert('Article \"" . htmlspecialchars($productName) . "\" ajouté au panier proforma !');
            window.location.href='proforma.php';
          </script>";
    exit;
}

// GÉNÉRATION DE LA FACTURE PROFORMA - VERSION CORRIGÉE
if (isset($_POST['generateProforma'])) {
    // Vérifier qu'il y a des articles dans le panier proforma
    $checkCartQuery = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
    $cartCount = mysqli_fetch_assoc($checkCartQuery)['count'];
    
    if ($cartCount == 0) {
        echo "<script>alert('Aucun article dans le panier proforma. Veuillez ajouter des articles avant de générer la proforma.'); window.location.href='proforma.php';</script>";
        exit;
    }

    // Récupération des infos client
    $custname     = mysqli_real_escape_string($con, trim($_POST['customername']));
    $custmobile   = preg_replace('/[^0-9+]/','', $_POST['mobilenumber']);
    $custemail    = mysqli_real_escape_string($con, trim($_POST['customeremail']));
    $custaddress  = mysqli_real_escape_string($con, trim($_POST['customeraddress']));
    $validitydays = max(1, intval($_POST['validitydays']));
    $discount     = $_SESSION['proforma_discount'] ?? 0;

    // Calcul du total
    $cartQ = mysqli_query($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
    $grand = 0;
    while ($r = mysqli_fetch_assoc($cartQ)) {
        $grand += $r['ProductQty'] * $r['Price'];
    }
    $netTotal = max(0, $grand - $discount);

    // Générer un numéro de facture proforma unique
    do {
        $proformaNumber = 'PF-' . date('Y') . '-' . mt_rand(1000, 9999);
        $checkExist = mysqli_query($con, "SELECT ID FROM tblproforma WHERE ProformaNumber = '$proformaNumber'");
    } while (mysqli_num_rows($checkExist) > 0);

    // Date de validité
    $validUntil = date('Y-m-d', strtotime("+$validitydays days"));

    // Désactiver l'autocommit pour transaction
    mysqli_autocommit($con, false);

    try {
        // Créer l'enregistrement proforma
        $query = "INSERT INTO tblproforma 
                  (ProformaNumber, CustomerName, CustomerMobile, CustomerEmail, CustomerAddress, 
                   FinalAmount, ValidUntil, AdminID, CreatedAt)
                  VALUES 
                  ('$proformaNumber', '$custname', '$custmobile', '$custemail', '$custaddress', 
                   '$netTotal', '$validUntil', '$currentAdminID', NOW())";
        
        if (!mysqli_query($con, $query)) {
            throw new Exception("Erreur lors de la création de la proforma: " . mysqli_error($con));
        }
        
        $proformaId = mysqli_insert_id($con);
        
        // ÉTAPE CRITIQUE: Mettre à jour le panier avec le numéro proforma et changer le statut
        $updateCartQuery = "UPDATE tblcart SET BillingId='$proformaNumber', IsCheckOut=3 WHERE IsCheckOut=2 AND AdminID='$currentAdminID'";
        if (!mysqli_query($con, $updateCartQuery)) {
            throw new Exception("Erreur lors de la mise à jour du panier: " . mysqli_error($con));
        }
        
        // Vérifier que la mise à jour a bien fonctionné
        $verifyUpdate = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcart WHERE BillingId='$proformaNumber' AND IsCheckOut=3 AND AdminID='$currentAdminID'");
        $updatedCount = mysqli_fetch_assoc($verifyUpdate)['count'];
        
        if ($updatedCount == 0) {
            throw new Exception("Aucun article n'a été associé à la proforma");
        }
        
        // DOUBLE VÉRIFICATION: S'assurer qu'il n'y a plus d'articles avec IsCheckOut=2
        $remainingItemsQuery = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
        $remainingCount = mysqli_fetch_assoc($remainingItemsQuery)['count'];
        
        if ($remainingCount > 0) {
            // FORCE CLEANUP: Supprimer tous les articles restants avec IsCheckOut=2 pour cet admin
            $forceCleanup = mysqli_query($con, "DELETE FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
            if (!$forceCleanup) {
                throw new Exception("Erreur lors du nettoyage du panier");
            }
        }
        
        // Valider la transaction
        mysqli_commit($con);
        
        $_SESSION['proforma_id'] = $proformaId;
        $_SESSION['proforma_number'] = $proformaNumber;
        
        // Nettoyer TOUTES les variables de session liées à la proforma
        unset($_SESSION['proforma_discount']);
        unset($_SESSION['proforma_discountType']);
        unset($_SESSION['proforma_discountValue']);

        echo "<script>
                alert('✅ Facture proforma créée avec succès !\\n\\nNuméro: $proformaNumber\\nArticles: $updatedCount\\nMontant: " . number_format($netTotal, 2) . " GNF\\n\\n✅ Panier proforma vidé pour nouveau devis.');
                window.location.href='proforma_invoice.php?number=$proformaNumber&refresh=1';
              </script>";
        exit;
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        mysqli_rollback($con);
        echo "<script>alert('❌ Erreur: " . addslashes($e->getMessage()) . "');</script>";
    }
    
    // Remettre l'autocommit à true
    mysqli_autocommit($con, true);
}

// Récupérer les noms de produits pour le datalist
$productNamesQuery = mysqli_query($con, "SELECT DISTINCT ProductName FROM tblproducts ORDER BY ProductName ASC");
$productNames = array();
if ($productNamesQuery) {
    while ($row = mysqli_fetch_assoc($productNamesQuery)) {
        $productNames[] = $row['ProductName'];
    }
}

// DEBUG SECTION - temporaire pour diagnostiquer les problèmes
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<div class='alert alert-info'><h4>🔍 DEBUG INFO:</h4>";
    
    // Vérifier tous les éléments du panier pour l'admin actuel
    $debugQuery = mysqli_query($con, "
        SELECT 
            ID, ProductId, ProductQty, Price, IsCheckOut, BillingId, AdminID,
            (SELECT ProductName FROM tblproducts WHERE ID = tblcart.ProductId) as ProductName
        FROM tblcart 
        WHERE AdminID = '$currentAdminID' 
        ORDER BY IsCheckOut, ID
    ");
    
    echo "<strong>Articles dans tblcart pour AdminID $currentAdminID:</strong><br>";
    echo "<table class='table table-bordered table-condensed'>";
    echo "<tr><th>ID</th><th>Product</th><th>Qty</th><th>Price</th><th>IsCheckOut</th><th>BillingId</th><th>Status</th></tr>";
    
    $hasData = false;
    while ($debugRow = mysqli_fetch_assoc($debugQuery)) {
        $hasData = true;
        $rowClass = '';
        $status = '';
        if ($debugRow['IsCheckOut'] == 2) {
            $rowClass = 'style="background-color: #ffffcc;"'; // Yellow for proforma cart
            $status = 'Panier Proforma';
        }
        if ($debugRow['IsCheckOut'] == 3) {
            $rowClass = 'style="background-color: #ccffcc;"'; // Green for completed proforma
            $status = 'Proforma Générée';
        }
        if ($debugRow['IsCheckOut'] == 1) {
            $rowClass = 'style="background-color: #ffcccc;"'; // Red for sales
            $status = 'Vente';
        }
        if ($debugRow['IsCheckOut'] == 0) {
            $rowClass = 'style="background-color: #ccccff;"'; // Blue for normal cart
            $status = 'Panier Normal';
        }
        
        echo "<tr $rowClass>";
        echo "<td>{$debugRow['ID']}</td>";
        echo "<td>{$debugRow['ProductName']}</td>";
        echo "<td>{$debugRow['ProductQty']}</td>";
        echo "<td>{$debugRow['Price']}</td>";
        echo "<td>{$debugRow['IsCheckOut']}</td>";
        echo "<td>{$debugRow['BillingId']}</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    
    if (!$hasData) {
        echo "<tr><td colspan='7'>Aucun article trouvé dans tblcart pour cet admin</td></tr>";
    }
    
    echo "</table>";
    
    // Afficher les variables de session
    echo "<h5>Variables de Session:</h5>";
    echo "<pre>";
    echo "Admin ID: " . $currentAdminID . "\n";
    echo "Admin Name: " . $currentAdminName . "\n";
    echo "Proforma Discount: " . ($_SESSION['proforma_discount'] ?? 'Not set') . "\n";
    echo "Discount Type: " . ($_SESSION['proforma_discountType'] ?? 'Not set') . "\n";
    echo "Discount Value: " . ($_SESSION['proforma_discountValue'] ?? 'Not set') . "\n";
    echo "</pre>";
    
    echo "<p><a href='proforma.php?force_cleanup=1' class='btn btn-danger btn-small'>🧹 Force Clean Cart</a></p>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion des stocks | Factures Proforma</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    <style>
        .user-cart-indicator {
            background-color: #f0f8ff;
            border-left: 4px solid #4169E1;
            padding: 10px;
            margin-bottom: 15px;
        }
        .user-cart-indicator i {
            margin-right: 5px;
            color: #4169E1;
        }
        .proforma-info {
            background-color: #e8f5e8;
            border: 1px solid #c3e6c3;
            color: #2d5016;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .proforma-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .price-modified {
            color: #f89406;
            font-weight: bold;
        }
        .cart-actions {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: right;
        }
        .debug-panel {
            background-color: #fff9c4;
            border: 1px solid #f1c40f;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Header + Sidebar -->
    <?php include_once('includes/header.php'); ?>
    <?php include_once('includes/sidebar.php'); ?>

    <div id="content">
        <div id="content-header">
            <div id="breadcrumb">
                <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
                    <i class="icon-home"></i> Accueil
                </a>
                <a href="proforma.php" class="current">Factures Proforma</a>
            </div>
            <h1>Factures Proforma</h1>
        </div>

        <div class="container-fluid">
            <hr>
            
            <!-- Debug Panel (if debug mode is on) -->
            <?php if (isset($_GET['debug'])): ?>
            <div class="debug-panel">
                <strong>🔧 Mode Debug Activé</strong> - 
                <a href="proforma.php" class="btn btn-small">Désactiver Debug</a>
                <a href="verify_proforma_database.php" class="btn btn-info btn-small">Vérifier DB</a>
                <a href="proforma.php?force_cleanup=1" class="btn btn-danger btn-small">Force Clean</a>
            </div>
            <?php endif; ?>
            
            <!-- Indicateur de panier proforma -->
            <div class="user-cart-indicator">
                <i class="icon-user"></i> <strong>Proforma gérée par: <?php echo htmlspecialchars($currentAdminName); ?></strong>
                <?php if (!isset($_GET['debug'])): ?>
                <span class="pull-right">
                    <a href="proforma.php?debug=1" class="btn btn-mini">🔍 Debug</a>
                </span>
                <?php endif; ?>
            </div>
            
            <!-- Information sur les factures proforma -->
            <div class="proforma-info">
                <strong><i class="icon-info-sign"></i> Information :</strong> 
                Les factures proforma permettent de présenter des devis à vos clients sans affecter le stock ou créer de commandes réelles.
            </div>
            
            <!-- ========== FORMULAIRE DE RECHERCHE ========== -->
            <div class="row-fluid">
                <div class="span12">
                    <form method="get" action="proforma.php" class="form-inline">
                        <label>Rechercher des Articles :</label>
                        <input type="text" name="searchTerm" class="span3" placeholder="Nom du Article..." list="productsList" />
                        <datalist id="productsList">
                            <?php
                            foreach ($productNames as $pname) {
                                echo '<option value="' . htmlspecialchars($pname) . '"></option>';
                            }
                            ?>
                        </datalist>
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </form>
                </div>
            </div>
            <hr>

            <?php
            if (!empty($_GET['searchTerm'])) {
                $searchTerm = mysqli_real_escape_string($con, $_GET['searchTerm']);
                $sql = "
                    SELECT 
                        p.ID,
                        p.ProductName,
                        p.ModelNumber,
                        p.Price,
                        p.Stock,
                        c.CategoryName
                    FROM tblproducts p
                    LEFT JOIN tblcategory c ON c.ID = p.CatID
                    WHERE 
                        p.ProductName LIKE '%$searchTerm%'
                        OR p.ModelNumber LIKE '%$searchTerm%'
                ";

                $res = mysqli_query($con, $sql);
                $count = mysqli_num_rows($res);
            ?>

            <div class="row-fluid">
                <div class="span12">
                    <h4>Résultats de recherche pour "<em><?= htmlspecialchars($_GET['searchTerm']) ?></em>"</h4>

                    <?php if ($count > 0) { ?>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nom du Article</th>
                                        <th>Catégorie</th>
                                        <th>Modèle</th>
                                        <th>Prix par Défaut</th>
                                        <th>Stock Actuel</th>
                                        <th>Prix Personnalisé</th>
                                        <th>Quantité</th>
                                        <th>Ajouter</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $i = 1;
                                while ($row = mysqli_fetch_assoc($res)) {
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo $row['ProductName']; ?></td>
                                        <td><?php echo $row['CategoryName']; ?></td>
                                        <td><?php echo $row['ModelNumber']; ?></td>
                                        <td><?php echo $row['Price']; ?> GNF</td>
                                        <td><?php echo $row['Stock']; ?> <small class="text-muted">(info seulement)</small></td>
                                        <td>
                                            <form method="post" action="proforma.php" style="margin:0;">
                                                <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>" />
                                                <input type="number" name="price" step="any" 
                                                       value="<?php echo $row['Price']; ?>" style="width:80px;" />
                                        </td>
                                        <td>
                                            <input type="number" name="quantity" value="1" min="1" style="width:60px;" />
                                        </td>
                                        <td>
                                            <button type="submit" name="addtocart" class="btn btn-info btn-small">
                                                <i class="icon-plus"></i> Ajouter au Devis
                                            </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                </tbody>
                            </table>
                        <?php } else { ?>
                            <p style="color:red;">Aucun Article correspondant trouvé.</p>
                        <?php } ?>
                    </div>
                </div>
                <hr>
            <?php } ?>

            <!-- ========== PANIER PROFORMA + REMISE ========== -->
            <div class="row-fluid">
                <div class="span12">
                    
                    <!-- Actions du panier -->
                    <div class="cart-actions">
                        <!-- FORMULAIRE DE REMISE -->
                        <form method="post" class="form-inline" style="display:inline;">
                            <label>Remise :</label>
                            <input type="number" name="discount" step="any" value="<?php echo $discountValue; ?>" style="width:80px;" />
                            
                            <select name="discountType" style="width:120px; margin-left:5px;">
                                <option value="absolute" <?php echo ($discountType == 'absolute') ? 'selected' : ''; ?>>Valeur absolue</option>
                                <option value="percentage" <?php echo ($discountType == 'percentage') ? 'selected' : ''; ?>>Pourcentage (%)</option>
                            </select>
                            
                            <button class="btn btn-info" type="submit" name="applyDiscount" style="margin-left:5px;">Appliquer</button>
                        </form>
                        
                        <!-- BOUTON VIDER PANIER -->
                        <?php 
                        $cartCheckQuery = mysqli_query($con, "SELECT COUNT(*) as count FROM tblcart WHERE IsCheckOut=2 AND AdminID='$currentAdminID'");
                        $cartItemCount = mysqli_fetch_assoc($cartCheckQuery)['count'];
                        
                        if ($cartItemCount > 0): 
                        ?>
                            <form method="post" style="display: inline; margin-left: 10px;">
                                <button type="submit" name="clearProformaCart" class="btn btn-warning btn-small" 
                                        onclick="return confirm('Êtes-vous sûr de vouloir vider le panier proforma? Cette action ne peut pas être annulée.');">
                                    <i class="icon-trash"></i> Vider le Panier
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Tableau du panier proforma -->
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-list-alt"></i></span>
                            <h5>Articles dans le devis proforma (<?php echo $cartItemCount; ?> articles)</h5>
                        </div>
                        <div class="widget-content nopadding">
                            <table class="table table-bordered" style="font-size: 15px">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Nom du Article</th>
                                        <th>Quantité</th>
                                        <th>Prix de base</th>
                                        <th>Prix appliqué</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $ret = mysqli_query($con, "
                                      SELECT 
                                        tblcart.ID as cid,
                                        tblcart.ProductQty,
                                        tblcart.Price as cartPrice,
                                        tblproducts.ProductName,
                                        tblproducts.Price as basePrice
                                      FROM tblcart
                                      LEFT JOIN tblproducts ON tblproducts.ID = tblcart.ProductId
                                      WHERE tblcart.IsCheckOut = 2 AND tblcart.AdminID = '$currentAdminID'
                                      ORDER BY tblcart.ID ASC
                                    ");
                                    $cnt = 1;
                                    $grandTotal = 0;
                                    $num = mysqli_num_rows($ret);
                                    
                                    if ($num > 0) {
                                        while ($row = mysqli_fetch_array($ret)) {
                                            $pq = $row['ProductQty'];
                                            $ppu = $row['cartPrice'];
                                            $basePrice = $row['basePrice'];
                                            $lineTotal = $pq * $ppu;
                                            $grandTotal += $lineTotal;
                                            
                                            // Déterminer si le prix a été modifié par rapport au prix de base
                                            $prixModifie = ($ppu != $basePrice);
                                            ?>
                                            <tr>
                                                <td><?php echo $cnt; ?></td>
                                                <td><?php echo $row['ProductName']; ?></td>
                                                <td><?php echo $pq; ?></td>
                                                <td><?php echo number_format($basePrice, 2); ?> GNF</td>
                                                <td class="<?php echo $prixModifie ? 'price-modified' : ''; ?>">
                                                    <?php echo number_format($ppu, 2); ?> GNF
                                                    <?php if ($prixModifie): ?>
                                                        <?php 
                                                        $variation = (($ppu / $basePrice) - 1) * 100;
                                                        $symbole = $variation >= 0 ? '+' : '';
                                                        echo '<br><small class="text-muted">(' . $symbole . number_format($variation, 1) . '%)</small>'; 
                                                        ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo number_format($lineTotal, 2); ?> GNF</td>
                                                <td>
                                                    <a href="proforma.php?delid=<?php echo $row['cid']; ?>"
                                                       onclick="return confirm('Voulez-vous vraiment retirer cet article du devis?');">
                                                        <i class="icon-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                            $cnt++;
                                        }
                                        $netTotal = $grandTotal - $discount;
                                        if ($netTotal < 0) {
                                            $netTotal = 0;
                                        }
                                        ?>
                                        <tr>
                                            <th colspan="5" style="text-align: right; font-weight: bold;">Total Général</th>
                                            <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($grandTotal, 2); ?> GNF</th>
                                        </tr>
                                        <tr>
                                            <th colspan="5" style="text-align: right; font-weight: bold;">
                                                Remise
                                                <?php if ($discountType == 'percentage'): ?>
                                                    (<?php echo $discountValue; ?>%)
                                                <?php endif; ?>
                                            </th>
                                            <th colspan="2" style="text-align: center; font-weight: bold;"><?php echo number_format($discount, 2); ?> GNF</th>
                                        </tr>
                                        <tr>
                                            <th colspan="5" style="text-align: right; font-weight: bold; color: blue;">Total Net</th>
                                            <th colspan="2" style="text-align: center; font-weight: bold; color: blue;"><?php echo number_format($netTotal, 2); ?> GNF</th>
                                        </tr>
                                    <?php
                                    } else {
                                        ?>
                                        <tr>
                                            <td colspan="7" style="color:red; text-align:center">
                                                <i class="icon-info-sign"></i> Aucun article dans le devis proforma
                                                <br><small>Utilisez la recherche ci-dessus pour ajouter des articles</small>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Formulaire de génération de proforma -->
                    <?php if ($num > 0): ?>
                    <div class="proforma-form">
                        <h4><i class="icon-file-alt"></i> Générer la Facture Proforma</h4>
                        <form method="post" class="form-horizontal" name="generateProforma">
                            <div class="control-group">
                                <label class="control-label">Nom du client :</label>
                                <div class="controls">
                                    <input type="text" class="span6" name="customername" required />
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Numéro de mobile :</label>
                                <div class="controls">
                                    <input type="tel" class="span6" name="mobilenumber" 
                                           pattern="^\+224[0-9]{9}$" placeholder="+224xxxxxxxxx" required />
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Email (optionnel) :</label>
                                <div class="controls">
                                    <input type="email" class="span6" name="customeremail" />
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Adresse :</label>
                                <div class="controls">
                                    <textarea class="span6" name="customeraddress" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="control-group">
                                <label class="control-label">Validité (jours) :</label>
                                <div class="controls">
                                    <input type="number" name="validitydays" value="30" min="1" max="365" style="width:80px;" />
                                    <span class="help-inline">Nombre de jours de validité de la proforma</span>
                                </div>
                            </div>
                            <div class="form-actions text-center">
                                <button class="btn btn-success btn-large" type="submit" name="generateProforma">
                                    <i class="icon-file-alt"></i> Générer la Facture Proforma
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once('includes/footer.php'); ?>

    <!-- SCRIPTS -->
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery.ui.custom.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.uniform.js"></script>
    <script src="js/select2.min.js"></script>
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/matrix.js"></script>
    <script src="js/matrix.tables.js"></script>
</body>
</html>