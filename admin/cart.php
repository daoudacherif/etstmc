<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// =======================================================
// 0) SMS Function: Send SMS via Nimba SMS API
// =======================================================
function sendSmsNotification($to, $message) {
    $url = "https://api.nimbasms.com/v1/messages";
    $apiKey = "1608e90e20415c7edf0226bf86e7effd"; // Replace with your actual Nimba SMS API key

    $postData = json_encode([
        "to"      => $to,
        "message" => $message
    ]);

    $headers = [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 201) {
        return true; // SMS sent successfully
    } else {
        error_log("Failed to send SMS. Response: $response");
        return false;
    }
}

// =======================================================
// Vérifier si l'admin est connecté
// =======================================================
if (strlen($_SESSION['imsaid'] == 0)) {
  header('location:logout.php');
  exit;
}

// =======================================================
// 1) Préparer la liste de tous les produits pour le <datalist>
// =======================================================
$allProdQuery = mysqli_query($con, "SELECT ProductName FROM tblproducts ORDER BY ProductName ASC");
$productNames = [];
while ($rowProd = mysqli_fetch_assoc($allProdQuery)) {
  $productNames[] = $rowProd['ProductName'];
}

// =======================================================
// 2) Ajouter un produit au panier
// =======================================================
if (isset($_POST['addtocart'])) {
  $productId = intval($_POST['productid']);
  $quantity  = intval($_POST['quantity']);
  $price     = floatval($_POST['price']);  // prix saisi manuellement

  if ($quantity <= 0)  $quantity = 1;
  if ($price    < 0)  $price    = 0;

  // Vérifier si ce produit est déjà dans le panier
  $checkCart = mysqli_query($con, "
    SELECT ID, ProductQty 
    FROM tblcart 
    WHERE ProductId='$productId' AND IsCheckOut=0 
    LIMIT 1
  ");
  if (mysqli_num_rows($checkCart) > 0) {
    // Mise à jour de la quantité
    $row    = mysqli_fetch_assoc($checkCart);
    $cartId = $row['ID'];
    $oldQty = $row['ProductQty'];
    $newQty = $oldQty + $quantity;

    mysqli_query($con, "
      UPDATE tblcart 
      SET ProductQty='$newQty', Price='$price'
      WHERE ID='$cartId'
    ");
  } else {
    // Insérer un nouvel article dans le panier
    mysqli_query($con, "
      INSERT INTO tblcart(ProductId, ProductQty, Price, IsCheckOut) 
      VALUES('$productId', '$quantity', '$price', '0')
    ");
  }
  echo "<script>alert('Produit ajouté au panier!');</script>";
  echo "<script>window.location.href='cart.php'</script>";
  exit;
}

// =======================================================
// 3) Retirer un produit du panier
// =======================================================
if (isset($_GET['delid'])) {
  $rid = intval($_GET['delid']);
  mysqli_query($con, "DELETE FROM tblcart WHERE ID='$rid'");
  echo "<script>alert('Produit retiré du panier');</script>";
  echo "<script>window.location.href = 'cart.php'</script>";
  exit;
}

// =======================================================
// 4) Gérer la remise (discount) en session
// =======================================================
if (isset($_POST['applyDiscount'])) {
  $_SESSION['discount'] = floatval($_POST['discount']);
  echo "<script>window.location.href='cart.php'</script>";
  exit;
}
$discount = isset($_SESSION['discount']) ? $_SESSION['discount'] : 0;

// =======================================================
// 5) Validation du panier (checkout) & création de facture
// =======================================================
if (isset($_POST['submit'])) {
  $custname      = $_POST['customername'];
  $custmobilenum = $_POST['mobilenumber'];
  $modepayment   = $_POST['modepayment'];

  // Recalculer le total du panier
  $cartQuery = mysqli_query($con, "
    SELECT ProductQty, Price 
    FROM tblcart
    WHERE IsCheckOut=0
  ");
  $grandTotal = 0;
  while ($row = mysqli_fetch_assoc($cartQuery)) {
    $grandTotal += ($row['ProductQty'] * $row['Price']);
  }

  // Appliquer la remise
  $netTotal = $grandTotal - $discount;
  if ($netTotal < 0) $netTotal = 0;

  // Générer un numéro de facture unique
  $billingnum = mt_rand(100000000, 999999999);

  // Marquer le panier comme validé et insérer dans tblcustomer
  $query  = "UPDATE tblcart 
             SET BillingId='$billingnum', IsCheckOut=1 
             WHERE IsCheckOut=0;";
  $query .= "INSERT INTO tblcustomer(
               BillingNumber,
               CustomerName,
               MobileNumber,
               ModeofPayment,
               FinalAmount
             ) VALUES(
               '$billingnum',
               '$custname',
               '$custmobilenum',
               '$modepayment',
               '$netTotal'
             );";

  $result = mysqli_multi_query($con, $query);
  if ($result) {
    $_SESSION['invoiceid'] = $billingnum;
    unset($_SESSION['discount']); // on réinitialise la remise

    // Préparer les détails du SMS
    // Assurez-vous que le numéro de téléphone est au format international (ex: "+221776543210")
    $customerPhone = $custmobilenum;
    $smsMessage = "Dear $custname, your order (Invoice No: $billingnum) has been successfully placed. Thank you for shopping with us.";

    // Envoyer la notification SMS
    if (sendSmsNotification($customerPhone, $smsMessage)) {
      echo "<script>alert('Facture créée avec succès. Numéro : $billingnum. SMS envoyé.');</script>";
    } else {
      echo "<script>alert('Facture créée avec succès. Numéro : $billingnum. Échec de l\'envoi du SMS.');</script>";
    }

    echo "<script>window.location.href='invoice.php'</script>";
    exit;
  } else {
    echo "<script>alert('Erreur lors du paiement');</script>";
  }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Système de gestion des stocks | Panier</title>
  <?php include_once('includes/cs.php'); ?>
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
      <a href="cart.php" class="current">Panier de produits</a>
    </div>
    <h1>Panier de produits</h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- ========== FORMULAIRE DE RECHERCHE (avec datalist) ========== -->
    <div class="row-fluid">
      <div class="span12">
        <form method="get" action="cart.php" class="form-inline">
          <label>Rechercher des produits :</label>
          <!-- Champ de saisie relié à une datalist -->
          <input type="text" name="searchTerm" class="span3"
                 placeholder="Nom du produit..." list="productsList" />

          <!-- La datalist contenant tous les noms de produits -->
          <datalist id="productsList">
            <?php
            // Générer <option> pour chaque nom de produit
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

    <!-- ========== AFFICHAGE DES RÉSULTATS DE RECHERCHE ========== -->
    <?php
    if (!empty($_GET['searchTerm'])) {
      $searchTerm = mysqli_real_escape_string($con, $_GET['searchTerm']);
      $sql = "
        SELECT p.ID, p.ProductName, p.BrandName, p.ModelNumber, p.Price,
               c.CategoryName, s.SubCategoryName
        FROM tblproducts p
        LEFT JOIN tblcategory c ON c.ID = p.CatID
        LEFT JOIN tblsubcategory s ON s.ID = p.SubcatID
        WHERE (p.ProductName LIKE '%$searchTerm%' 
          OR p.ModelNumber LIKE '%$searchTerm%')
      ";
      $res = mysqli_query($con, $sql);
      $count = mysqli_num_rows($res);
      ?>
      <div class="row-fluid">
        <div class="span12">
          <h4>Résultats de recherche pour "<em><?php echo htmlentities($searchTerm); ?></em>"</h4>
          <?php if ($count > 0) { ?>
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nom du produit</th>
                  <th>Catégorie</th>
                  <th>Sous-catégorie</th>
                  <th>Marque</th>
                  <th>Modèle</th>
                  <th>Prix par défaut</th>
                  <th>Prix personnalisé</th>
                  <th>Quantité</th>
                  <th>Ajouter</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $i=1;
                while ($row = mysqli_fetch_assoc($res)) {
                  ?>
                  <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo $row['ProductName']; ?></td>
                    <td><?php echo $row['CategoryName']; ?></td>
                    <td><?php echo $row['SubCategoryName']; ?></td>
                    <td><?php echo $row['BrandName']; ?></td>
                    <td><?php echo $row['ModelNumber']; ?></td>
                    <td><?php echo $row['Price']; ?></td>
                    <td>
                      <!-- Form pour ajouter au panier -->
                      <form method="post" action="cart.php" style="margin:0;">
                        <input type="hidden" name="productid" value="<?php echo $row['ID']; ?>" />
                        <input type="number" name="price" step="any" 
                               value="<?php echo $row['Price']; ?>" style="width:80px;" />
                    </td>
                    <td>
                        <input type="number" name="quantity" value="1" min="1" style="width:60px;" />
                    </td>
                    <td>
                        <button type="submit" name="addtocart" class="btn btn-success btn-small">
                          <i class="icon-plus"></i> Ajouter
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
            <p style="color:red;">Aucun produit correspondant trouvé.</p>
          <?php } ?>
        </div>
      </div>
      <hr>
    <?php } // end if searchTerm ?>

    <!-- ========== PANIER + REMISE + PAIEMENT ========== -->
    <div class="row-fluid">
      <div class="span12">

        <!-- Formulaire pour la remise -->
        <form method="post" class="form-inline" style="text-align:right;">
          <label>Remise :</label>
          <input type="number" name="discount" step="any" 
                 value="<?php echo $discount; ?>" style="width:80px;" />
          <button class="btn btn-info" type="submit" name="applyDiscount">Appliquer</button>
        </form>
        <hr>

        <!-- Formulaire checkout (infos client) -->
        <form method="post" class="form-horizontal" name="submit">
          <div class="control-group">
            <label class="control-label">Nom du client :</label>
            <div class="controls">
              <input type="text" class="span11" id="customername" name="customername" required />
            </div>
          </div>
          <div class="control-group">
            <label class="control-label">Numéro de mobile du client :</label>
            <div class="controls">
              <input type="text" class="span11" id="mobilenumber" name="mobilenumber" required
                     maxlength="10" pattern="[0-9]+" />
            </div>
          </div>
          <div class="control-group">
            <label class="control-label">Mode de paiement :</label>
            <div class="controls">
              <label><input type="radio" name="modepayment" value="cash" checked> Espèces</label>
              <label><input type="radio" name="modepayment" value="card"> Carte</label>
            </div>
          </div>
          <div class="text-center">
            <button class="btn btn-primary" type="submit" name="submit">
              Paiement & Créer une facture
            </button>
          </div>
        </form>

        <!-- Tableau du panier -->
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Produits dans le panier</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered" style="font-size: 15px">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom du produit</th>
                  <th>Quantité</th>
                  <th>Prix (par unité)</th>
                  <th>Total</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Récupérer les articles du panier (IsCheckOut=0)
                $ret = mysqli_query($con, "
                  SELECT 
                    tblcart.ID as cid,
                    tblcart.ProductQty,
                    tblcart.Price as cartPrice,
                    tblproducts.ProductName
                  FROM tblcart
                  LEFT JOIN tblproducts ON tblproducts.ID = tblcart.ProductId
                  WHERE tblcart.IsCheckOut = 0
                  ORDER BY tblcart.ID ASC
                ");
                $cnt=1; 
                $grandTotal=0; 
                $num=mysqli_num_rows($ret);
                if($num>0){
                  while ($row=mysqli_fetch_array($ret)) {
                    $pq    = $row['ProductQty'];
                    $ppu   = $row['cartPrice'];
                    $lineTotal = $pq * $ppu;
                    $grandTotal += $lineTotal;
                    ?>
                    <tr class="gradeX">
                      <td><?php echo $cnt; ?></td>
                      <td><?php echo $row['ProductName']; ?></td>
                      <td><?php echo $pq; ?></td>
                      <td><?php echo number_format($ppu,2); ?></td>
                      <td><?php echo number_format($lineTotal,2); ?></td>
                      <td>
                        <a href="cart.php?delid=<?php echo $row['cid'];?>"
                           onclick="return confirm('Voulez-vous vraiment retirer cet article?');">
                           <i class="icon-trash"></i>
                        </a>
                      </td>
                    </tr>
                    <?php
                    $cnt++;
                  }
                  // Affichage du total + remise + net
                  $netTotal = $grandTotal - $discount;
                  if ($netTotal < 0) $netTotal = 0;
                  ?>
                  <tr>
                    <th colspan="4" style="text-align: right; font-weight: bold;">
                      Total général
                    </th>
                    <th colspan="2" style="text-align: center; font-weight: bold;">
                      <?php echo number_format($grandTotal,2); ?>
                    </th>
                  </tr>
                  <tr>
                    <th colspan="4" style="text-align: right; font-weight: bold;">
                      Remise
                    </th>
                    <th colspan="2" style="text-align: center; font-weight: bold;">
                      <?php echo number_format($discount,2); ?>
                    </th>
                  </tr>
                  <tr>
                    <th colspan="4" style="text-align: right; font-weight: bold; color: green;">
                      Total net
                    </th>
                    <th colspan="2" style="text-align: center; font-weight: bold; color: green;">
                      <?php echo number_format($netTotal,2); ?>
                    </th>
                  </tr>
                  <?php
                } else {
                  ?>
                  <tr>
                    <td colspan="6" style="color:red; text-align:center">
                      Aucun article trouvé dans le panier
                    </td>
                  </tr>
                  <?php
                }
                ?>
              </tbody>
            </table>
          </div><!-- widget-content -->
        </div><!-- widget-box -->
      </div>
    </div><!-- row-fluid -->

  </div><!-- container-fluid -->
</div><!-- content -->

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
