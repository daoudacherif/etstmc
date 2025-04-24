<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Security headers
header("Content-Security-Policy: default-src 'self'");
header("X-Content-Type-Options: nosniff");

// Initialize session variables
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$discount = $_SESSION['discount'] ?? 0;

// Fetch product names for datalist
$productNames = [];
$stmt = mysqli_prepare($con, "SELECT DISTINCT ProductName FROM tblproducts");
if ($stmt && mysqli_stmt_execute($stmt)) {
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $productNames[] = htmlspecialchars($row['ProductName']);
    }
}

// Security functions
function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

function validatePhone($number) {
    return preg_match('/^\+224\d{9}$/', $number);
}

// SMS API Functions (improved security)
function getAccessToken() {
    $url = "https://api.nimbasms.com/v1/oauth/token";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => "1608e90e20415c7edf0226bf86e7effd:kokICa68N6NJESoJt09IAFXjO05tYwdVV-Xjrql7o8pTi29ssdPJyNgPBdRIeLx6_690b_wzM27foyDRpvmHztN7ep6ICm36CgNggEzGxRs",
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("SMS API Error ($httpCode): $response");
        return false;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

function sendSmsNotification($to, $message) {
    if (!validatePhone($to)) return false;
    
    $token = getAccessToken();
    if (!$token) return false;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.nimbasms.com/v1/messages",
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "to" => [$to],
            "message" => $message,
            "sender_name" => "SMS 9080"
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 201;
}

// Session validation
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }

    if (isset($_POST['addtocart'])) {
        // Add to cart processing
        $productId = filter_input(INPUT_POST, 'productid', FILTER_VALIDATE_INT);
        $quantity = max(1, filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT));
        $price = max(0, filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT));

        // Stock validation using prepared statement
        $stmt = mysqli_prepare($con, "SELECT Stock FROM tblproducts WHERE ID = ?");
        mysqli_stmt_bind_param($stmt, 'i', $productId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 0) {
            die("<script>alert('Produit introuvable'); window.location.href='cart.php';</script>");
        }

        $stock = mysqli_fetch_assoc($result)['Stock'];
        if ($quantity > $stock) {
            die("<script>alert('Quantité demandée ($quantity) supérieure au stock disponible ($stock)'); window.location.href='cart.php';</script>");
        }

        // Update cart with prepared statement
        $stmt = mysqli_prepare($con, "SELECT ID, ProductQty FROM tblcart WHERE ProductId = ? AND IsCheckOut = 0 LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $productId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $newQty = $row['ProductQty'] + $quantity;
            $stmt = mysqli_prepare($con, "UPDATE tblcart SET ProductQty = ?, Price = ? WHERE ID = ?");
            mysqli_stmt_bind_param($stmt, 'idi', $newQty, $price, $row['ID']);
        } else {
            $stmt = mysqli_prepare($con, "INSERT INTO tblcart (ProductId, ProductQty, Price, IsCheckOut) VALUES (?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt, 'iid', $productId, $quantity, $price);
        }

        mysqli_stmt_execute($stmt);
        die("<script>alert('Produit ajouté au panier!');window.location.href='cart.php';</script>");
    }

    if (isset($_POST['applyDiscount'])) {
        $_SESSION['discount'] = max(0, filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT));
        header('Location: cart.php');
        exit;
    }

    if (isset($_POST['submit'])) {
        // Checkout processing
        $custname = sanitizeInput($_POST['customername']);
        $custmobile = preg_replace('/[^0-9+]/', '', $_POST['mobilenumber']);
        $modepayment = sanitizeInput($_POST['modepayment']);

        if (!validatePhone($custmobile)) {
            die("<script>alert('Format de numéro mobile invalide'); window.history.back();</script>");
        }

        // Calculate totals with prepared statement
        $stmt = mysqli_prepare($con, "SELECT ProductQty, Price FROM tblcart WHERE IsCheckOut = 0");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $grandTotal = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $grandTotal += $row['ProductQty'] * $row['Price'];
        }
        $netTotal = max(0, $grandTotal - $discount);
        $billNum = mt_rand(100000000, 999999999);

        // Database transaction
        mysqli_begin_transaction($con);
        try {
            // Update cart
            $stmt = mysqli_prepare($con, "UPDATE tblcart SET BillingId = ?, IsCheckOut = 1 WHERE IsCheckOut = 0");
            mysqli_stmt_bind_param($stmt, 'i', $billNum);
            mysqli_stmt_execute($stmt);

            // Create customer record
            $stmt = mysqli_prepare($con, "INSERT INTO tblcustomer (BillingNumber, CustomerName, MobileNumber, ModeofPayment, FinalAmount) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isssd', $billNum, $custname, $custmobile, $modepayment, $netTotal);
            mysqli_stmt_execute($stmt);

            mysqli_commit($con);
            
            // Send SMS
            $_SESSION['invoiceid'] = $billNum;
            unset($_SESSION['discount']);
            $smsMessage = "Bonjour $custname, votre commande (Facture No: $billNum) a été validée.";
            $smsSent = sendSmsNotification($custmobile, $smsMessage);

            $alert = $smsSent ? 'SMS envoyé avec succès' : 'Échec d\'envoi SMS';
            die("<script>alert('Facture créée: $billNum\\n$alert');window.location.href='invoice.php';</script>");
        } catch (Exception $e) {
            mysqli_rollback($con);
            error_log("Transaction error: " . $e->getMessage());
            die("<script>alert('Erreur lors du paiement'); window.location.href='cart.php';</script>");
        }
    }
}

// Handle cart deletion
if (isset($_GET['delid'])) {
    $rid = filter_input(INPUT_GET, 'delid', FILTER_VALIDATE_INT);
    if ($rid) {
        $stmt = mysqli_prepare($con, "DELETE FROM tblcart WHERE ID = ?");
        mysqli_stmt_bind_param($stmt, 'i', $rid);
        mysqli_stmt_execute($stmt);
    }
    die("<script>alert('Produit retiré du panier');window.location.href='cart.php';</script>");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Système de gestion des stocks | Panier</title>
    <?php include_once('includes/cs.php'); ?>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a href="cart.php" class="current">Panier</a>
        </div>
        <h1>Gestion du Panier</h1>
    </div>

    <div class="container-fluid">
        <!-- Search Form -->
        <div class="row-fluid">
            <div class="span12">
                <form method="get" action="cart.php" class="form-inline">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="text" name="searchTerm" class="span3" placeholder="Rechercher produit..." 
                           list="productsList" value="<?= htmlspecialchars($_GET['searchTerm'] ?? '') ?>">
                    <datalist id="productsList">
                        <?php foreach ($productNames as $pname): ?>
                            <option value="<?= $pname ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <button type="submit" class="btn btn-primary">Rechercher</button>
                </form>
            </div>
        </div>

        <!-- Search Results -->
        <?php if (!empty($_GET['searchTerm'])): ?>
        <?php
        $searchTerm = mysqli_real_escape_string($con, $_GET['searchTerm']);
        $stmt = mysqli_prepare($con, "
            SELECT p.ID, p.ProductName, p.BrandName, p.ModelNumber, p.Price,
                   c.CategoryName, s.SubCategoryName
            FROM tblproducts p
            LEFT JOIN tblcategory c ON c.ID = p.CatID
            LEFT JOIN tblsubcategory s ON s.ID = p.SubcatID
            WHERE p.ProductName LIKE CONCAT('%', ?, '%') 
               OR p.ModelNumber LIKE CONCAT('%', ?, '%')
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $searchTerm, $searchTerm);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        ?>
        <div class="row-fluid">
            <div class="span12">
                <h4>Résultats pour "<?= htmlspecialchars($searchTerm) ?>"</h4>
                <?php if (mysqli_num_rows($result) > 0): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produit</th>
                            <th>Catégorie</th>
                            <th>Sous-catégorie</th>
                            <th>Marque</th>
                            <th>Modèle</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['ProductName']) ?></td>
                            <td><?= htmlspecialchars($row['CategoryName']) ?></td>
                            <td><?= htmlspecialchars($row['SubCategoryName']) ?></td>
                            <td><?= htmlspecialchars($row['BrandName']) ?></td>
                            <td><?= htmlspecialchars($row['ModelNumber']) ?></td>
                            <td><?= number_format($row['Price'], 2) ?></td>
                            <td>
                                <form method="post" class="form-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="productid" value="<?= $row['ID'] ?>">
                                    <input type="number" name="quantity" value="1" min="1" class="input-mini">
                                    <button type="submit" name="addtocart" class="btn btn-success btn-small">
                                        <i class="icon-plus"></i> Ajouter
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="alert alert-info">Aucun produit trouvé</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cart Management -->
        <div class="row-fluid">
            <div class="span12">
                <!-- Discount Form -->
                <form method="post" class="form-inline text-right">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <label>Remise:</label>
                    <input type="number" name="discount" step="0.01" value="<?= $discount ?>" class="input-small">
                    <button type="submit" name="applyDiscount" class="btn btn-info">Appliquer</button>
                </form>

                <!-- Cart Items -->
                <div class="widget-box">
                    <div class="widget-title">
                        <h5>Contenu du Panier</h5>
                    </div>
                    <div class="widget-content">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Prix Unitaire</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = mysqli_prepare($con, "
                                    SELECT c.ID, c.ProductQty, c.Price, p.ProductName 
                                    FROM tblcart c
                                    JOIN tblproducts p ON p.ID = c.ProductId
                                    WHERE c.IsCheckOut = 0
                                ");
                                mysqli_stmt_execute($stmt);
                                $result = mysqli_stmt_get_result($stmt);
                                $total = 0;
                                ?>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php $cnt = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                $lineTotal = $row['ProductQty'] * $row['Price'];
                                $total += $lineTotal;
                                ?>
                                <tr>
                                    <td><?= $cnt++ ?></td>
                                    <td><?= htmlspecialchars($row['ProductName']) ?></td>
                                    <td><?= $row['ProductQty'] ?></td>
                                    <td><?= number_format($row['Price'], 2) ?></td>
                                    <td><?= number_format($lineTotal, 2) ?></td>
                                    <td>
                                        <a href="cart.php?delid=<?= $row['ID'] ?>" 
                                           onclick="return confirm('Supprimer cet article?')"
                                           class="btn btn-danger btn-mini">
                                            <i class="icon-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <tr class="info">
                                    <td colspan="4" class="text-right"><strong>Total:</strong></td>
                                    <td colspan="2"><?= number_format($total, 2) ?></td>
                                </tr>
                                <tr class="warning">
                                    <td colspan="4" class="text-right"><strong>Remise:</strong></td>
                                    <td colspan="2"><?= number_format($discount, 2) ?></td>
                                </tr>
                                <tr class="success">
                                    <td colspan="4" class="text-right"><strong>Total Net:</strong></td>
                                    <td colspan="2"><?= number_format(max(0, $total - $discount), 2) ?></td>
                                </tr>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Panier vide</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Checkout Form -->
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="control-group">
                        <label class="control-label">Nom Client:</label>
                        <div class="controls">
                            <input type="text" name="customername" required class="span11">
                        </div>
                    </div>
                    <div class="control-group">
                        <label class="control-label">Téléphone:</label>
                        <div class="controls">
                            <input type="tel" name="mobilenumber" 
                                   pattern="^\+224\d{9}$"
                                   placeholder="+224XXXXXXXXX"
                                   required class="span11">
                        </div>
                    </div>
                    <div class="control-group">
                        <label class="control-label">Paiement:</label>
                        <div class="controls">
                            <label class="radio inline">
                                <input type="radio" name="modepayment" value="cash" checked> Espèces
                            </label>
                            <label class="radio inline">
                                <input type="radio" name="modepayment" value="card"> Carte
                            </label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit" class="btn btn-primary btn-large">
                            <i class="icon-shopping-cart"></i> Finaliser la Commande
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/matrix.js"></script>
</body>
</html>